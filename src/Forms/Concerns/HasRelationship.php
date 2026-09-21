<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;
use CubeAgency\FilamentNestedList\Exceptions\ScopeNotSetException;
use CubeAgency\FilamentNestedList\Exceptions\StaleRecordsException;
use CubeAgency\FilamentNestedList\Forms\NestedList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Kalnoy\Nestedset\Collection as NestedSetCollection;
use Kalnoy\Nestedset\QueryBuilder as NestedSetQueryBuilder;
use LogicException;
use ReflectionMethod;

trait HasRelationship
{
    protected string|Closure|null $relationship = null;

    /**
     * @var Collection<int, Model>|null
     */
    protected ?Collection $cachedExistingRecords = null;

    public function relationship(string|Closure|null $name = null): static
    {
        $this->relationship = $name ?? $this->getName();

        $this->loadStateFromRelationshipsUsing(static function (NestedList $component): void {
            $component->clearCachedExistingRecords();

            $component->fillFromRelationship();
        });

        $this->saveRelationshipsUsing(static function (NestedList $component, ?array $state): void {
            if (! is_array($state)) {
                $state = [];
            }

            $scope = $component->getScope();

            if (! $scope) {
                throw ScopeNotSetException::forField($component->getStatePath() ?? $component->getName());
            }

            $relationship = $component->getRelationship();

            if (! $relationship) {
                throw new LogicException(
                    'The nested list has no record to scope the tree by. Make sure the schema is given '
                    . 'a model before its relationships are saved.'
                );
            }

            $foreignKeyName = $relationship->getForeignKeyName();

            // Loading reads through the relationship and rebuilding through the scope, so the two
            // only describe the same records while the scope is that foreign key.
            if ($scope !== $foreignKeyName) {
                throw new LogicException(
                    "The nested list scope [{$scope}] must be the foreign key of the relationship "
                    . "[{$component->getRelationshipName()}], which is [{$foreignKeyName}]."
                );
            }

            $parentKey = $relationship->getParentKey();

            // A null scope matches every unscoped row, and the rebuild deletes what it does not
            // find. Not `blank()`: a key of `0` is valid.
            if ($parentKey === null) {
                throw new LogicException(
                    "The nested list relationship [{$component->getRelationshipName()}] resolves to a "
                    . 'null local key on the record being saved, leaving the tree with no scope.'
                );
            }

            $scopeAttributes = [$scope => $parentKey];

            // `rebuildTree()` fixes `_lft`/`_rgt` only once every save is through, so a throw
            // partway leaves a tree that no longer describes itself. Filament opens no transaction.
            $relationship->getRelated()->getConnection()->transaction(
                function () use ($component, $state, $scopeAttributes): void {
                    $tree = $component->prepareTreeForRebuild(
                        $component->toNestedSetTree($state),
                        $scopeAttributes,
                    );

                    $component->assertTreeRecordsAreInScope($tree, $scopeAttributes);

                    $component->scopedNestedSetQuery($scopeAttributes)->rebuildTree($tree, true);
                },
            );

            $component->clearCachedExistingRecords();

            $component->fillFromRelationship();
        });

        $this->dehydrated(false);

        return $this;
    }

    /**
     * Only `HasMany` is supported: `scope()` names a single column, which a morph cannot be pinned by.
     *
     * @return HasMany<Model, Model>|null
     */
    public function getRelationship(): ?HasMany
    {
        $relationshipName = $this->getRelationshipName();

        if (! filled($relationshipName)) {
            return null;
        }

        $record = $this->getModelInstance();

        if (! $record) {
            return null;
        }

        if (! $record->isRelation($relationshipName)) {
            throw new LogicException(
                "The relationship [{$relationshipName}] does not exist on the model [{$this->getModel()}]."
            );
        }

        $relationship = $record->{$relationshipName}();

        if (! $relationship instanceof HasMany) {
            throw new LogicException(
                "The nested list relationship [{$relationshipName}] must be a HasMany relationship to a nested "
                . 'set model, [' . $relationship::class . '] given.'
            );
        }

        return $relationship;
    }

    public function hasRelationship(): bool
    {
        return filled($this->getRelationshipName());
    }

    public function getRelationshipName(): ?string
    {
        return $this->evaluate($this->relationship);
    }

    public function fillFromRelationship(): void
    {
        $this->state(
            $this->getStateFromRelatedRecords($this->getCachedExistingRecords()),
        );
    }

    /**
     * `Model::scoped()`, written against instance methods so a missing nested set is reported
     * clearly rather than as an "undefined method" deep inside a save.
     *
     * @param  array<string, mixed>  $scopeAttributes
     */
    public function scopedNestedSetQuery(array $scopeAttributes): NestedSetQueryBuilder
    {
        $related = $this->getRelationship()?->getRelated();

        if (! $related instanceof Model) {
            throw new LogicException('The nested list has no relationship to build a query for.');
        }

        if (! method_exists($related, 'newScopedQuery')) {
            throw new LogicException(
                'The nested list related model [' . $related::class . '] must use '
                . 'Kalnoy\Nestedset\NodeTrait.'
            );
        }

        $modelScope = $this->getModelScopeAttributes($related);

        // `newScopedQuery()` partitions by what the model scopes by, whatever is pinned here. Left
        // unscoped it covers the whole table, and the rebuild deletes every other parent's tree.
        if ($modelScope !== array_keys($scopeAttributes)) {
            throw new LogicException(
                'The nested list related model [' . $related::class . '] must scope its nested set by '
                . 'exactly [' . implode(', ', array_keys($scopeAttributes)) . ']. `getScopeAttributes()` '
                . 'returns [' . implode(', ', $modelScope) . '].'
            );
        }

        // `setRawAttributes()` so newly created nodes inherit the scope, matching `Model::scoped()`.
        $related->setRawAttributes($scopeAttributes);

        $query = $related->newScopedQuery();

        if (! $query instanceof NestedSetQueryBuilder) {
            throw new LogicException(
                'The nested list related model [' . $related::class . '] did not return a nested set query builder.'
            );
        }

        return $query;
    }

    /**
     * The columns the related model partitions its nested set by. `NodeTrait` keeps the accessor
     * protected, and defaults it to `null` — no partitioning at all.
     *
     * @return array<string>
     */
    protected function getModelScopeAttributes(Model $related): array
    {
        $attributes = (new ReflectionMethod($related, 'getScopeAttributes'))->invoke($related);

        return is_array($attributes) ? array_values($attributes) : [];
    }

    /**
     * @return Collection<int, Model>
     */
    public function getCachedExistingRecords(): Collection
    {
        if ($this->cachedExistingRecords) {
            return $this->cachedExistingRecords;
        }

        $relationshipName = $this->getRelationshipName();

        if (! filled($relationshipName)) {
            return $this->cachedExistingRecords = new Collection;
        }

        $record = $this->getModelInstance();

        if ($record?->relationLoaded($relationshipName)) {
            return $this->cachedExistingRecords = $record->getRelationValue($relationshipName);
        }

        $relationship = $this->getRelationship();

        if (! $relationship) {
            return $this->cachedExistingRecords = new Collection;
        }

        $query = $relationship->getQuery();

        // `_lft` order is the order the tree is built in.
        return $this->cachedExistingRecords = $query instanceof NestedSetQueryBuilder
            ? $query->defaultOrder()->get()
            : $query->get();
    }

    /**
     * Turn a flat collection of nested set records into the keyed, nested array the field renders.
     *
     * Keys only identify an item within the state, so hashed record keys and browser-added UUIDs mix.
     *
     * @param  Collection<int, Model>  $records
     * @return array<string, mixed>
     */
    protected function getStateFromRelatedRecords(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        if (! $records instanceof NestedSetCollection) {
            throw new LogicException(
                'The nested list relationship must return a nested set collection. Make sure the related model '
                . 'uses Kalnoy\Nestedset\NodeTrait.'
            );
        }

        $childrenKey = $this->getChildrenKey();
        $nestedSetColumns = $this->getNestedSetColumns();

        $mapRecord = function (Model $record) use (&$mapRecord, $childrenKey, $nestedSetColumns): array {
            // The nested set columns follow the shape of the tree, so the browser has no say in them.
            $data = Arr::except($record->attributesToArray(), $nestedSetColumns);

            $children = $record->getRelationValue('children');

            $data[$childrenKey] = $children instanceof Collection
                ? $children->sortBy('_lft')->mapWithKeys($mapRecord)->toArray()
                : [];

            return [md5('record-' . $record->getKey()) => $data];
        };

        return $records
            ->toTree()
            ->sortBy('_lft')
            ->mapWithKeys($mapRecord)
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function toNestedSetTree(array $state): array
    {
        $childrenKey = $this->getChildrenKey();

        if ($childrenKey === 'children') {
            return $state;
        }

        return array_map(function (mixed $item) use ($childrenKey): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $children = $item[$childrenKey] ?? [];

            unset($item[$childrenKey]);

            $item['children'] = is_array($children) ? $this->toNestedSetTree($children) : [];

            return $item;
        }, $state);
    }

    /**
     * Make the tree safe to hand to `rebuildTree()`, which `fill()`s and saves every item in it.
     *
     * The nested set columns and the scope are pinned here; guarding the rest is the related model's
     * `$fillable`.
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $scopeAttributes
     * @param  array<string>|null  $nestedSetColumns
     * @return array<string, mixed>
     */
    public function prepareTreeForRebuild(
        array $items,
        array $scopeAttributes,
        ?array $nestedSetColumns = null,
    ): array {
        $nestedSetColumns ??= $this->getNestedSetColumns();

        $prepared = [];

        foreach ($items as $key => $item) {
            // `rebuildTree()` reads every entry as an array, and a scalar describes no row to keep.
            if (! is_array($item)) {
                continue;
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];

            $prepared[$key] = [
                ...Arr::except($item, [...$nestedSetColumns, 'children']),
                ...$scopeAttributes,
                'children' => $this->prepareTreeForRebuild($children, $scopeAttributes, $nestedSetColumns),
            ];
        }

        return $prepared;
    }

    /**
     * `rebuildTree()` throws a message-less `ModelNotFoundException` for a key outside the scope,
     * whether it was deleted since the form loaded or borrowed from another tree. Named here.
     *
     * @param  array<string, mixed>  $tree
     * @param  array<string, mixed>  $scopeAttributes
     */
    public function assertTreeRecordsAreInScope(array $tree, array $scopeAttributes): void
    {
        $related = $this->getRelationship()?->getRelated();

        if (! $related instanceof Model) {
            return;
        }

        $keyName = $related->getKeyName();
        $keys = $this->collectTreeRecordKeys($tree, $keyName);

        if ($keys === []) {
            return;
        }

        // A key of the wrong type matches no row and cannot be handed to `whereIn()` either.
        $queryableKeys = array_values(array_filter($keys, 'is_scalar'));

        $query = $this->scopedNestedSetQuery($scopeAttributes);

        // `rebuildTree()` matches trashed rows too. This is what `withTrashed()` delegates to.
        if (method_exists($related, 'usesSoftDelete') && $related->usesSoftDelete()) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $found = $queryableKeys === []
            ? []
            : $query->whereIn($keyName, $queryableKeys)->pluck($keyName)->all();

        $missing = array_values(array_udiff(
            $keys,
            $found,
            static fn (mixed $a, mixed $b): int => strcmp(
                is_scalar($a) ? (string) $a : get_debug_type($a),
                is_scalar($b) ? (string) $b : get_debug_type($b),
            ),
        ));

        if ($missing !== []) {
            throw StaleRecordsException::forField($this->getStatePath() ?? $this->getName(), $missing);
        }
    }

    /**
     * Every primary key the tree claims, at any depth. An absent key marks a new record.
     *
     * @param  array<string, mixed>  $tree
     * @return array<int, mixed>
     */
    protected function collectTreeRecordKeys(array $tree, string $keyName): array
    {
        $keys = [];

        foreach ($tree as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item[$keyName])) {
                $keys[] = $item[$keyName];
            }

            $children = $item['children'] ?? null;

            if (is_array($children)) {
                $keys = [...$keys, ...$this->collectTreeRecordKeys($children, $keyName)];
            }
        }

        return $keys;
    }

    /**
     * @return array<string>
     */
    protected function getNestedSetColumns(): array
    {
        $related = $this->getRelationship()?->getRelated();

        if (
            ! $related
            || ! method_exists($related, 'getLftName')
            || ! method_exists($related, 'getRgtName')
            || ! method_exists($related, 'getParentIdName')
        ) {
            return [];
        }

        return [
            $related->getLftName(),
            $related->getRgtName(),
            $related->getParentIdName(),
        ];
    }

    public function clearCachedExistingRecords(): void
    {
        $this->cachedExistingRecords = null;

        $relationshipName = $this->getRelationshipName();

        // A loaded relation is cached knowledge too, and `rebuildTree()` writes past it.
        if (filled($relationshipName)) {
            $this->getModelInstance()?->unsetRelation($relationshipName);
        }
    }
}
