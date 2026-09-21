<?php

use CubeAgency\FilamentNestedList\Exceptions\StaleRecordsException;
use CubeAgency\FilamentNestedList\Tests\Fixtures\Menu;
use CubeAgency\FilamentNestedList\Tests\Fixtures\MenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use CubeAgency\FilamentNestedList\Tests\Fixtures\SoftDeletingMenuItem;
use Livewire\Livewire;

// The tree lives in `$data`, a public Livewire property, and every item is handed to `rebuildTree()`
// to be filled and saved. The columns deciding where a record lives must not come from there.

function tamperingMenu(): Menu
{
    $menu = Menu::create(['name' => 'Main']);

    MenuItem::create(['menu_id' => $menu->id, 'name' => 'First', 'url' => '/first']);

    return $menu;
}

function fieldForMenu(Menu $menu)
{
    return Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();
}

it('keeps the columns the nested set computes out of the state', function () {
    $menu = tamperingMenu();

    $state = fieldForMenu($menu)->getState();

    $item = reset($state);

    expect($item)->toHaveKey('name')
        ->and($item)->not->toHaveKey('_lft')
        ->and($item)->not->toHaveKey('_rgt')
        ->and($item)->not->toHaveKey('parent_id');
});

it('refuses to move an item into another scope through the state', function () {
    $menu = tamperingMenu();
    $otherMenu = Menu::create(['name' => 'Other']);

    $field = fieldForMenu($menu);
    $first = MenuItem::where('name', 'First')->firstOrFail();

    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'menu_id' => $otherMenu->id,
            'name' => 'First',
            'children' => [],
        ],
    ]);

    $field->saveRelationships();

    expect(MenuItem::find($first->getKey())->menu_id)->toBe($menu->id);
});

// `rebuildTree()` throws a message-less `ModelNotFoundException` for a key it cannot find in scope.
it('reports a record the scope no longer holds instead of failing inside the rebuild', function () {
    $menu = tamperingMenu();

    $field = fieldForMenu($menu);
    $state = $field->getState();

    $first = MenuItem::where('name', 'First')->firstOrFail();

    // Another session deletes the row while this form is open.
    $first->forceDelete();

    $field->state($state);

    expect(fn () => $field->saveRelationships())
        ->toThrow(StaleRecordsException::class, (string) $first->getKey());
});

it('refuses to adopt a record belonging to another scope', function () {
    $menu = tamperingMenu();
    $otherMenu = Menu::create(['name' => 'Other']);
    $stranger = MenuItem::create(['menu_id' => $otherMenu->id, 'name' => 'Stranger', 'url' => '/s']);

    $field = fieldForMenu($menu);

    $field->state([
        md5('record-' . $stranger->getKey()) => [
            'id' => $stranger->getKey(),
            'name' => 'Stolen',
            'children' => [],
        ],
    ]);

    expect(fn () => $field->saveRelationships())->toThrow(StaleRecordsException::class);

    // The rebuild runs in a transaction, so the other menu keeps both its row and its name.
    expect(MenuItem::find($stranger->getKey()))
        ->not->toBeNull()
        ->menu_id->toBe($otherMenu->id)
        ->name->toBe('Stranger');
});

it('still saves a record the scope only holds as trashed', function () {
    $menu = Menu::create(['name' => 'Main']);
    $item = SoftDeletingMenuItem::create(['menu_id' => $menu->id, 'name' => 'First', 'url' => '/first']);

    $field = Livewire::test(NestedListTestComponent::class, [
        'menu' => $menu->id,
        'relationshipName' => 'softDeletingItems',
    ])->instance()->nestedList();

    $state = $field->getState();

    $item->delete();

    $field->state($state);

    // `rebuildTree()` matches against trashed rows, so this is not a record the scope has lost.
    $field->saveRelationships();

    expect(SoftDeletingMenuItem::withTrashed()->find($item->getKey()))->not->toBeNull();
});

it('still saves a tree of records that are all in scope', function () {
    $menu = tamperingMenu();

    $field = fieldForMenu($menu);
    $field->state($field->getState());

    $field->saveRelationships();

    expect(MenuItem::where('menu_id', $menu->id)->pluck('name')->all())->toBe(['First']);
});

it('takes an item parent from the tree rather than from the state', function () {
    $menu = tamperingMenu();

    $field = fieldForMenu($menu);
    $first = MenuItem::where('name', 'First')->firstOrFail();

    $second = MenuItem::create(['menu_id' => $menu->id, 'name' => 'Second', 'url' => '/second']);

    // Both are roots in the tree, but the state claims `First` is a child of `Second`.
    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'name' => 'First',
            'parent_id' => $second->getKey(),
            'children' => [],
        ],
        md5('record-' . $second->getKey()) => [
            'id' => $second->getKey(),
            'name' => 'Second',
            'children' => [],
        ],
    ]);

    $field->saveRelationships();

    expect(MenuItem::find($first->getKey())->parent_id)->toBeNull()
        ->and(MenuItem::scoped(['menu_id' => $menu->id])->whereIsRoot()->pluck('name')->all())
        ->toBe(['First', 'Second']);
});
