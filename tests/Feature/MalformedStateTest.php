<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\MenuItem;
use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

// The tree lives in `$data`, a public Livewire property, so the browser can replace any part of it
// with a value of the wrong shape.

it('renders the empty state when the tree is not an array', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', null)
        ->assertSee(__('filament-nested-list::nested-list.empty.heading'));
});

it('renders a row whose children are not an array', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', ['a' => ['name' => 'A', 'children' => 'oops']])
        ->assertSee('A');
});

// A label reaches `e()`, which takes a string. `labelKey()` can just as well name an array column.
it('renders a row whose label is not a string', function (mixed $label) {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', ['a' => ['name' => $label, 'children' => []]])
        ->assertOk()
        ->assertSee('fi-nested-list-item-label', escape: false);
})->with([
    'an array' => [['oops']],
    'null' => [null],
    'a boolean' => [true],
]);

it('renders a row whose label is a number', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', ['a' => ['name' => 2026, 'children' => []]])
        ->assertSee('2026');
});

it('renders a row that is not an array at all', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', ['a' => 'not an item'])
        ->assertOk();
});

it('drops an item that is not an array rather than failing the save', function () {
    $menu = menuWithTree();

    $field = Livewire::test(NestedListTestComponent::class, ['menu' => $menu->id])
        ->instance()
        ->nestedList();

    $first = MenuItem::where('name', 'First')->firstOrFail();

    // `rebuildTree()` reads every entry as an array, and a scalar describes no row to keep.
    $field->state([
        md5('record-' . $first->getKey()) => [
            'id' => $first->getKey(),
            'name' => 'First',
            'children' => [],
        ],
        'tampered' => 'invalid',
    ]);

    $field->saveRelationships();

    expect(array_column($field->getState(), 'name'))->toBe(['First']);
});

it('ignores a sort when the tree is not an array', function () {
    $field = Livewire::test(NestedListTestComponent::class)->instance()->nestedList();

    $field->state(null);

    $field->sortItems('data.items', ['data.items.a']);

    expect($field->getState())->toBe([]);
});
