<?php

use CubeAgency\FilamentNestedList\Exceptions\ScopeNotSetException;
use CubeAgency\FilamentNestedList\Forms\NestedList;
use CubeAgency\FilamentNestedList\Tests\Fixtures\AppendedMenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\CompositeMenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\Menu;
use CubeAgency\FilamentNestedList\Tests\Fixtures\MenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use CubeAgency\FilamentNestedList\Tests\Fixtures\UnscopedMenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\UuidMenuItem;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

// `menuWithTree()` is shared with other files, so it lives in `tests/Pest.php`.

it('loads the tree from the relationship in nested-set order', function () {
    $menu = menuWithTree();

    $items = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->get('data.items');

    expect($items)->toHaveCount(2);

    $names = array_column($items, 'name');

    expect($names)->toBe(['First', 'Second']);

    $first = reset($items);

    expect($first['children'])->toHaveCount(1)
        ->and(reset($first['children'])['name'])->toBe('First child');
});

it('keys existing records by a hash of their primary key', function () {
    $menu = menuWithTree();

    $items = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->get('data.items');

    $first = MenuItem::where('name', 'First')->firstOrFail();

    expect($items)->toHaveKey(md5('record-' . $first->getKey()));
});

it('loads an empty array when the relationship has no records', function () {
    $menu = Menu::create(['name' => 'Empty']);

    expect(Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])->get('data.items'))
        ->toBe([]);
});

it('rebuilds the tree when relationships are saved', function () {
    $menu = menuWithTree();

    $component = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id]);

    $field = $component->instance()->nestedList();

    $first = MenuItem::where('name', 'First')->firstOrFail();
    $second = MenuItem::where('name', 'Second')->firstOrFail();

    // Swap the two roots, and nest the old first item under the old second one.
    $field->state([
        md5('record-' . $second->getKey()) => [
            'id' => $second->getKey(),
            'menu_id' => $menu->id,
            'name' => 'Second',
            'url' => '/second',
            'children' => [
                md5('record-' . $first->getKey()) => [
                    'id' => $first->getKey(),
                    'menu_id' => $menu->id,
                    'name' => 'First',
                    'url' => '/first',
                    'children' => [],
                ],
            ],
        ],
    ]);

    $field->saveRelationships();

    expect(MenuItem::find($first->getKey())->parent_id)->toBe($second->getKey())
        ->and(MenuItem::scoped(['menu_id' => $menu->id])->whereIsRoot()->pluck('name')->all())
        ->toBe(['Second']);
});

it('refills from the database after saving when the relation is already loaded', function () {
    $menu = menuWithTree();

    $component = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id]);

    $field = $component->instance()->nestedList();

    // Anything reading `$menu->items` leaves the relation loaded — and stale the moment
    // `rebuildTree()` runs, since that writes through a scoped query rather than the relation.
    $component->instance()->record->load('items');

    $first = MenuItem::where('name', 'First')->firstOrFail();

    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'menu_id' => $menu->id,
            'name' => 'Renamed',
            'url' => '/first',
            'children' => [],
        ],
        'new-item' => [
            'menu_id' => $menu->id,
            'name' => 'Brand new',
            'url' => '/new',
            'children' => [],
        ],
    ]);

    $field->saveRelationships();

    expect(array_column($field->getState(), 'name'))->toBe(['Renamed', 'Brand new']);
});

it('scopes the rebuilt tree by the relationship local key', function () {
    // `hasMany(..., 'menu_uuid', 'uuid')`: the foreign key holds the menu's uuid, not its primary key.
    $menu = Menu::create(['name' => 'Main', 'uuid' => 'menu-uuid']);

    $item = UuidMenuItem::create(['menu_uuid' => $menu->uuid, 'name' => 'First']);

    $field = Livewire::test(NestedListTestComponent::class, [
        'menu' => $menu->id,
        'relationshipName' => 'uuidItems',
        'scope' => 'menu_uuid',
    ])->instance()->nestedList();

    $field->state([
        md5('record-' . $item->getKey()) => [
            'id' => $item->getKey(),
            'name' => 'Renamed',
            'children' => [],
        ],
    ]);

    $field->saveRelationships();

    // Scoping by the primary key would write the tree out of the relationship's reach, leaving the
    // field to refill with nothing.
    expect(UuidMenuItem::find($item->getKey())->menu_uuid)->toBe($menu->uuid)
        ->and(array_column($field->getState(), 'name'))->toBe(['Renamed']);
});

it('refuses a related model that does not scope its nested set', function () {
    $menu = menuWithTree();
    $other = Menu::create(['name' => 'Other']);

    // `newScopedQuery()` covers the whole table for an unscoped model, so the rebuild would delete
    // every other parent's tree.
    $stray = UnscopedMenuItem::create(['menu_id' => $other->id, 'name' => 'Stray']);

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $field->relationship('unscopedItems');
    $field->state([]);

    expect(fn () => $field->saveRelationships())
        ->toThrow(LogicException::class, 'scope its nested set');

    expect(UnscopedMenuItem::find($stray->getKey()))->not->toBeNull();
});

it('refuses a related model with a composite nested set scope', function () {
    $menu = menuWithTree();

    // Only `menu_id` can be pinned, leaving `tenant_id` null — a partition of somebody else's rows.
    $stray = CompositeMenuItem::create(['menu_id' => $menu->id, 'name' => 'Stray']);

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $field->relationship('compositeItems');
    $field->state([]);

    expect(fn () => $field->saveRelationships())
        ->toThrow(LogicException::class, 'scope its nested set');

    expect(CompositeMenuItem::find($stray->getKey()))->not->toBeNull();
});

it('refuses to rebuild when the relationship local key is null', function () {
    $menu = Menu::create(['name' => 'Unkeyed']);

    // A null scope matches every unscoped row, and the rebuild deletes the ones it does not find.
    $stray = UuidMenuItem::create(['menu_uuid' => null, 'name' => 'Stray']);

    $field = Livewire::test(NestedListTestComponent::class, [
        'menu' => $menu->id,
        'relationshipName' => 'uuidItems',
        'scope' => 'menu_uuid',
    ])->instance()->nestedList();

    $field->state(['new-item' => ['name' => 'New', 'children' => []]]);

    expect(fn () => $field->saveRelationships())->toThrow(LogicException::class, 'local key');

    expect(UuidMenuItem::find($stray->getKey()))->not->toBeNull();
});

it('refuses a scope that is not the relationship foreign key', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    // Loading reads `menu_id` through the relationship, so rebuilding by `url` would work over a
    // different set of records entirely.
    $field->scope('url');

    $field->saveRelationships();
})->throws(LogicException::class, 'foreign key');

it('refuses a relationship that is not a has-many', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $field->relationship('firstItem');

    $field->getRelationship();
})->throws(LogicException::class, 'must be a HasMany relationship');

it('refuses a morph relationship, whose scope needs more than one column', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    // `scope()` names a single column, so a morph's type column would be left out of the rebuild.
    $field->relationship('morphedItems');

    $field->getRelationship();
})->throws(LogicException::class, 'must be a HasMany relationship');

it('leaves the tree untouched when the rebuild fails partway', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $first = MenuItem::where('name', 'First')->firstOrFail();
    $second = MenuItem::where('name', 'Second')->firstOrFail();

    // `name` is not nullable, so the second item's save throws after the first has been written.
    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'name' => 'Renamed',
            'children' => [],
        ],
        md5('record-' . $second->getKey()) => [
            'id' => $second->getKey(),
            'name' => null,
            'children' => [],
        ],
    ]);

    expect(fn () => $field->saveRelationships())->toThrow(QueryException::class);

    // `rebuildTree()` fixes `_lft`/`_rgt` only once every save is through, so a half-applied
    // rebuild leaves a tree that no longer describes itself.
    expect(MenuItem::find($first->getKey())->name)->toBe('First')
        ->and(MenuItem::where('menu_id', $menu->id)->count())->toBe(3);
});

it('has no existing records when no relationship is configured', function () {
    $field = Livewire::test(NestedListTestComponent::class)->instance()->nestedList();

    expect($field->hasRelationship())->toBeFalse()
        ->and($field->getCachedExistingRecords())->toBeEmpty();
});

it('does not write the tree to the parent record as an attribute', function () {
    $field = NestedList::make('items')->relationship();

    expect($field->isDehydrated())->toBeFalse();
});

it('loads the tree under a custom children key', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $field->childrenKey('kids');
    $field->fillFromRelationship();

    $first = collect($field->getState())->firstWhere('name', 'First');

    expect($first)->toHaveKey('kids')
        ->and($first)->not->toHaveKey('children')
        ->and($first['kids'])->toHaveCount(1)
        ->and(reset($first['kids'])['name'])->toBe('First child');
});

it('rebuilds the tree from a custom children key', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $field->childrenKey('kids');

    $first = MenuItem::where('name', 'First')->firstOrFail();
    $second = MenuItem::where('name', 'Second')->firstOrFail();
    $firstChild = MenuItem::where('name', 'First child')->firstOrFail();

    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'menu_id' => $menu->id,
            'name' => 'First',
            'kids' => [],
        ],
        md5('record-' . $second->getKey()) => [
            'id' => $second->getKey(),
            'menu_id' => $menu->id,
            'name' => 'Second',
            'kids' => [
                md5('record-' . $firstChild->getKey()) => [
                    'id' => $firstChild->getKey(),
                    'menu_id' => $menu->id,
                    'name' => 'First child',
                    'kids' => [],
                ],
            ],
        ],
    ]);

    $field->saveRelationships();

    expect(MenuItem::find($firstChild->getKey())->parent_id)->toBe($second->getKey())
        ->and(MenuItem::scoped(['menu_id' => $menu->id])->whereIsRoot()->pluck('name')->all())
        ->toBe(['First', 'Second']);
});

// The whole state is round-tripped, so `$fillable` is what keeps an attribute with no column of its
// own out of `rebuildTree()`'s `fill()`.
it('drops an appended attribute on save, because the model declares $fillable', function () {
    $menu = Menu::create(['name' => 'Main']);
    AppendedMenuItem::create(['menu_id' => $menu->id, 'name' => 'First', 'url' => '/first']);

    $field = Livewire::test(NestedListTestComponent::class, [
        'menu' => $menu->id,
        'relationshipName' => 'appendedItems',
    ])->instance()->nestedList();

    $state = $field->getState();

    expect(reset($state))->toHaveKey('slug');

    $field->state($state);
    $field->saveRelationships();

    expect(AppendedMenuItem::where('menu_id', $menu->id)->pluck('name')->all())->toBe(['First']);
});

it('throws a helpful exception when no scope is set', function () {
    $menu = menuWithTree();

    $component = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id]);

    $field = $component->instance()->nestedList();

    // Clear the scope that the test component sets.
    $field->scope(null);

    $field->saveRelationships();
})->throws(ScopeNotSetException::class);
