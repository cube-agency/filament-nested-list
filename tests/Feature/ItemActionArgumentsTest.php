<?php

use CubeAgency\FilamentNestedList\Forms\NestedList;
use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

// The item actions are handed a `statePath` by the browser, so they need the same guard as
// `sortItems()` before writing anywhere with it.

function itemActionState(): array
{
    return [
        'a' => [
            'name' => 'A',
            'children' => [
                'a1' => ['name' => 'A1', 'children' => []],
            ],
        ],
    ];
}

function actionWithStatePath(string $name, string $statePath): TestAction
{
    return TestAction::make($name)
        ->schemaComponent('items')
        ->arguments(['statePath' => $statePath]);
}

function componentWithItem(?int $maxDepth = null)
{
    return Livewire::test(NestedListTestComponent::class, ['maxDepth' => $maxDepth])
        ->set('data.items', itemActionState());
}

it('ignores an add-child action for a state path outside the field', function () {
    $component = componentWithItem(maxDepth: 2)
        ->callAction(actionWithStatePath('addChild', 'data.notTheField'), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores an edit action for a state path outside the field', function () {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('edit', 'data.notTheField'), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores a delete action for a state path outside the field', function () {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('delete', 'data.notTheField'));

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores an item action targeting the field itself rather than an item', function () {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('delete', 'data.items'));

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores a delete action targeting a children collection rather than an item', function () {
    // One crafted argument would otherwise drop every descendant of the item at once.
    $component = componentWithItem()
        ->callAction(actionWithStatePath('delete', 'data.items.a.children'));

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores an edit action targeting a children collection rather than an item', function () {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('edit', 'data.items.a.children'), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores a delete action targeting an attribute rather than an item', function () {
    // Dropping an attribute leaves an item the next save cannot write.
    $component = componentWithItem()
        ->callAction(actionWithStatePath('delete', 'data.items.a.name'));

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('ignores an item action for an item that is no longer in the state', function () {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('edit', 'data.items.ghost'), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
});

// The data helpers read some segments as instructions, so an owned path can address more than one item.
it('ignores an edit action for a segment the data helpers interpret', function (string $segment) {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('edit', "data.items.{$segment}"), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
})->with(['*', '\*', '{first}', '\{first}', '{last}', '\{last}']);

it('ignores a delete action for a segment the data helpers interpret', function (string $segment) {
    $component = componentWithItem()
        ->callAction(actionWithStatePath('delete', "data.items.{$segment}"));

    expect($component->get('data.items'))->toBe(itemActionState());
})->with(['*', '\*', '{first}', '\{first}', '{last}', '\{last}']);

it('ignores an add-child action for a segment the data helpers interpret', function (string $segment) {
    $component = componentWithItem(maxDepth: 2)
        ->callAction(actionWithStatePath('addChild', "data.items.{$segment}"), ['name' => 'Injected']);

    expect($component->get('data.items'))->toBe(itemActionState());
})->with(['*', '\*', '{first}', '\{first}', '{last}', '\{last}']);

it('refuses to add a child past the max depth', function () {
    $component = componentWithItem(maxDepth: 1)
        ->callAction(actionWithStatePath('addChild', 'data.items.a.children.a1'), ['name' => 'Too deep']);

    expect($component->get('data.items'))->toBe(itemActionState());
});

it('still adds a child within the max depth', function () {
    $component = componentWithItem(maxDepth: 2)
        ->callAction(actionWithStatePath('addChild', 'data.items.a.children.a1'), ['name' => 'Deep enough']);

    expect($component->get('data.items.a.children.a1.children'))->toHaveCount(1);
});

it('owns nothing when the field has no state path of its own', function () {
    // A blank state path would otherwise make every browser-supplied path starting with `.` look owned.
    $field = new class('items') extends NestedList
    {
        public function getStatePath(bool $isAbsolute = true): ?string
        {
            return null;
        }
    };

    expect($field->resolveItemStatePath('.injected'))->toBeNull();
});
