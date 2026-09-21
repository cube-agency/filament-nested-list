<?php

namespace CubeAgency\FilamentNestedList\Forms;

use Closure;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasAlignment;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasItemActions;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasItemAffixes;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasMaxDepth;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasRelationship;
use CubeAgency\FilamentNestedList\Forms\Concerns\HasSchema;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Str;
use Stringable;

class NestedList extends Field
{
    use HasAlignment;
    use HasItemActions;
    use HasItemAffixes;
    use HasMaxDepth;
    use HasRelationship;
    use HasSchema;

    /** Segments the data helpers read as an instruction rather than a key. */
    protected const INTERPRETED_PATH_SEGMENTS = ['*', '\*', '{first}', '\{first}', '{last}', '\{last}'];

    protected string $view = 'filament-nested-list::nested-list';

    protected string|Closure $childrenKey = 'children';

    protected string|Closure|null $labelKey = null;

    protected string|Closure|null $scope = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (NestedList $component, ?array $state) {
            if (! $state) {
                $component->state([]);
            }
        });

        $this->registerActions([
            fn (NestedList $component): Action => $component->getAddAction(),
            fn (NestedList $component): Action => $component->getAddChildAction(),
            fn (NestedList $component): Action => $component->getEditAction(),
            fn (NestedList $component): Action => $component->getDeleteAction(),
        ]);
    }

    /**
     * Reorder one level of the tree, called from the Alpine component after a drag.
     *
     * Not `#[Renderless]`: re-parenting changes the state paths the item actions target. A drag
     * across levels arrives as two calls, each applying a complete move, so the pair is
     * order-independent.
     *
     * @param  array<mixed>  $targetItemsStatePaths
     */
    #[ExposedLivewireMethod]
    public function sortItems(mixed $targetStatePath, array $targetItemsStatePaths): void
    {
        // A disabled field still has its relationships saved.
        if ($this->isDisabled()) {
            return;
        }

        if (! is_string($targetStatePath) || ! $this->ownsStatePath($targetStatePath)) {
            return;
        }

        $state = $this->getState();

        $relativeStatePath = $this->getRelativeStatePath($targetStatePath);

        if (! $this->isLevelPath($relativeStatePath) || ! $this->levelExists($state, $relativeStatePath)) {
            return;
        }

        $targetDepth = $this->getPathDepth($relativeStatePath);

        $items = [];
        $movedItemStatePaths = [];
        $stayedItemStatePaths = [];

        foreach ($targetItemsStatePaths as $targetItemStatePath) {
            if (! is_string($targetItemStatePath) || ! $this->ownsStatePath($targetItemStatePath)) {
                return;
            }

            $relativeItemStatePath = $this->getRelativeStatePath($targetItemStatePath);

            if (! $this->isItemPath($relativeItemStatePath)) {
                return;
            }

            $item = data_get($state, $relativeItemStatePath);

            // The path may be gone, and storing the `null` breaks the next save.
            if (! is_array($item)) {
                continue;
            }

            if ($this->pathIsWithinItem($relativeStatePath, $relativeItemStatePath)) {
                return;
            }

            if ($this->movesItemPastMaxDepth($item, $this->getPathDepth($relativeItemStatePath), $targetDepth)) {
                return;
            }

            $key = Str::afterLast($relativeItemStatePath, '.');

            // A shared key would delete the other item's row on the next save.
            if (array_key_exists($key, $items)) {
                return;
            }

            $items[$key] = $item;

            if ($this->getLevelPath($relativeItemStatePath) !== $relativeStatePath) {
                // Arriving items leave where they came from, so one inside another is left twice.
                if ($this->pathOverlapsAny($relativeItemStatePath, $movedItemStatePaths)) {
                    return;
                }

                $movedItemStatePaths[] = $relativeItemStatePath;
            } else {
                $stayedItemStatePaths[$key] = $relativeItemStatePath;
            }
        }

        foreach ($movedItemStatePaths as $movedItemStatePath) {
            data_forget($state, $movedItemStatePath);
        }

        // Re-read: an item that stayed put may be the parent an arriving item came from.
        foreach ($stayedItemStatePaths as $key => $stayedItemStatePath) {
            $item = data_get($state, $stayedItemStatePath);

            if (is_array($item)) {
                $items[$key] = $item;
            }
        }

        $items = $this->withItemsThatWouldBeOrphaned($state, $relativeStatePath, $items);

        if (! $relativeStatePath) {
            $state = $items;
        } else {
            data_set($state, $relativeStatePath, $items);
        }

        $this->state($state);

        $this->callAfterStateUpdated();
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = parent::getState();

        return is_array($state) ? $state : [];
    }

    public function childrenKey(string|Closure $childrenKey): static
    {
        $this->childrenKey = $childrenKey;

        return $this;
    }

    public function getChildrenKey(): string
    {
        return $this->evaluate($this->childrenKey);
    }

    public function labelKey(string|Closure|null $labelKey): static
    {
        $this->labelKey = $labelKey;

        return $this;
    }

    public function getLabelKey(): string
    {
        return $this->evaluate($this->labelKey) ?? 'name';
    }

    /** A label reaches `e()`, which takes a string. Anything else renders as nothing. */
    public function getItemLabel(mixed $item): string
    {
        $label = is_array($item) ? ($item[$this->getLabelKey()] ?? null) : null;

        if (is_scalar($label) || $label instanceof Stringable) {
            return (string) $label;
        }

        return '';
    }

    public function getRelativeStatePath(string $path): string
    {
        return str($path)->after($this->getStatePath() ?? '')->trim('.')->toString();
    }

    /**
     * Resolve the `statePath` an item action is handed by the browser, or `null` when it addresses
     * none of this field's items.
     */
    public function resolveItemStatePath(mixed $statePath): ?string
    {
        if (! is_string($statePath) || ! $this->ownsStatePath($statePath)) {
            return null;
        }

        $relativeStatePath = $this->getRelativeStatePath($statePath);

        if (! $this->isItemPath($relativeStatePath)) {
            return null;
        }

        return $relativeStatePath;
    }

    /**
     * An item sits at an alternation of item key and children key. Anything else — `items.a.name`,
     * say — addresses an attribute or a collection.
     */
    protected function isItemPath(string $relativeStatePath): bool
    {
        if ($relativeStatePath === '') {
            return false;
        }

        $segments = explode('.', $relativeStatePath);

        if (count($segments) % 2 === 0) {
            return false;
        }

        $childrenKey = $this->getChildrenKey();

        foreach ($segments as $index => $segment) {
            if ($segment === '' || in_array($segment, static::INTERPRETED_PATH_SEGMENTS, true)) {
                return false;
            }

            if (($index % 2 === 1) !== ($segment === $childrenKey)) {
                return false;
            }
        }

        return true;
    }

    /** A sort destination is a level: the root list, or an item's children collection. */
    protected function isLevelPath(string $relativeStatePath): bool
    {
        if ($relativeStatePath === '') {
            return true;
        }

        $suffix = '.' . $this->getChildrenKey();

        return str_ends_with($relativeStatePath, $suffix)
            && $this->isItemPath(Str::beforeLast($relativeStatePath, $suffix));
    }

    /**
     * `data_set()` would invent a missing path, leaving a phantom item the next save writes as a row.
     * Only the item need exist — a leaf with no children key is a valid destination.
     *
     * @param  array<string, mixed>  $state
     */
    protected function levelExists(array $state, string $relativeStatePath): bool
    {
        if ($relativeStatePath === '') {
            return true;
        }

        $itemStatePath = Str::beforeLast($relativeStatePath, '.' . $this->getChildrenKey());

        return is_array(data_get($state, $itemStatePath));
    }

    public function scope(string|Closure|null $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->evaluate($this->scope);
    }

    /**
     * Backstop for `movesPastMaxDepth()` in `resources/js/depth.js`, which refuses the drag first.
     * Only descending moves are checked, so a tree already past the limit stays reorderable.
     */
    protected function movesItemPastMaxDepth(mixed $item, int $sourceDepth, int $targetDepth): bool
    {
        if ($targetDepth <= $sourceDepth) {
            return false;
        }

        return $targetDepth + $this->getSubtreeHeight($item) > $this->getDeepestAllowedDepth();
    }

    /**
     * Keep any item the new order leaves out that nothing else in the tree holds — a paired call may
     * have re-parented it, and losing it altogether deletes its row.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    protected function withItemsThatWouldBeOrphaned(array $state, string $relativeStatePath, array $items): array
    {
        $currentItems = $relativeStatePath === ''
            ? $state
            : data_get($state, $relativeStatePath);

        if (! is_array($currentItems)) {
            return $items;
        }

        $stateAfterSort = $state;

        if ($relativeStatePath === '') {
            $stateAfterSort = $items;
        } else {
            data_set($stateAfterSort, $relativeStatePath, $items);
        }

        $keysInTree = $this->collectItemKeys($stateAfterSort);

        foreach ($currentItems as $key => $item) {
            if (array_key_exists($key, $items) || array_key_exists($key, $keysInTree)) {
                continue;
            }

            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * Every item key in the tree, at any depth, as a set.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<array-key, true>
     */
    protected function collectItemKeys(array $items, ?string $childrenKey = null): array
    {
        $childrenKey ??= $this->getChildrenKey();

        $keys = [];

        foreach ($items as $key => $item) {
            $keys[$key] = true;

            $children = is_array($item) ? ($item[$childrenKey] ?? null) : null;

            if (is_array($children)) {
                $keys += $this->collectItemKeys($children, $childrenKey);
            }
        }

        return $keys;
    }

    protected function pathIsWithinItem(string $relativeStatePath, string $relativeItemStatePath): bool
    {
        return $relativeStatePath === $relativeItemStatePath
            || str_starts_with($relativeStatePath, $relativeItemStatePath . '.');
    }

    /**
     * @param  array<string>  $relativeItemStatePaths
     */
    protected function pathOverlapsAny(string $relativeItemStatePath, array $relativeItemStatePaths): bool
    {
        foreach ($relativeItemStatePaths as $otherItemStatePath) {
            if (
                $this->pathIsWithinItem($relativeItemStatePath, $otherItemStatePath)
                || $this->pathIsWithinItem($otherItemStatePath, $relativeItemStatePath)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function getLevelPath(string $relativeItemStatePath): string
    {
        return str_contains($relativeItemStatePath, '.')
            ? Str::beforeLast($relativeItemStatePath, '.')
            : '';
    }

    public function getPathDepth(string $relativeStatePath): int
    {
        if ($relativeStatePath === '') {
            return 0;
        }

        $childrenKey = $this->getChildrenKey();

        return count(array_filter(
            explode('.', $relativeStatePath),
            fn (string $segment): bool => $segment === $childrenKey,
        ));
    }

    protected function getSubtreeHeight(mixed $item): int
    {
        $children = is_array($item) ? ($item[$this->getChildrenKey()] ?? null) : null;

        if (! is_array($children) || $children === []) {
            return 0;
        }

        $height = 0;

        foreach ($children as $child) {
            $height = max($height, $this->getSubtreeHeight($child));
        }

        return $height + 1;
    }

    protected function ownsStatePath(string $path): bool
    {
        $statePath = $this->getStatePath();

        // Without a path of its own the field owns nothing, not every path starting with `.`.
        if (blank($statePath)) {
            return false;
        }

        return $path === $statePath || str_starts_with($path, $statePath . '.');
    }
}
