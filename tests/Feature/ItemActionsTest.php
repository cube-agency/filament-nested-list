<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function addAction(): TestAction
{
    return TestAction::make('add')->schemaComponent('items');
}

function itemAction(string $name, string $statePath): TestAction
{
    return TestAction::make($name)
        ->schemaComponent('items')
        ->arguments(['statePath' => $statePath]);
}

it('adds a root item', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->callAction(addAction(), ['name' => 'First', 'url' => '/first']);

    $items = $component->get('data.items');

    expect($items)->toHaveCount(1);

    $item = reset($items);

    expect($item['name'])->toBe('First')
        ->and($item['url'])->toBe('/first')
        ->and($item['children'])->toBe([]);
});

it('adds a child item under an existing item', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'parent' => ['name' => 'Parent', 'children' => []],
        ])
        ->callAction(
            itemAction('addChild', 'data.items.parent'),
            ['name' => 'Child', 'url' => '/child'],
        );

    $children = $component->get('data.items.parent.children');

    expect($children)->toHaveCount(1)
        ->and(reset($children)['name'])->toBe('Child');
});

it('edits an existing item without dropping its children', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'parent' => [
                'name' => 'Before',
                'url' => '/before',
                'children' => [
                    'child' => ['name' => 'Child', 'children' => []],
                ],
            ],
        ])
        ->callAction(
            itemAction('edit', 'data.items.parent'),
            ['name' => 'After', 'url' => '/after'],
        );

    expect($component->get('data.items.parent.name'))->toBe('After')
        ->and($component->get('data.items.parent.url'))->toBe('/after')
        ->and($component->get('data.items.parent.children'))->toHaveKey('child');
});

it('deletes an item', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'a' => ['name' => 'A', 'children' => []],
            'b' => ['name' => 'B', 'children' => []],
        ])
        ->callAction(itemAction('delete', 'data.items.a'));

    expect($component->get('data.items'))->toHaveKey('b')
        ->and($component->get('data.items'))->not->toHaveKey('a');
});

it('hides the item actions when the field is not addable, editable or deletable', function () {
    $field = Livewire::test(NestedListTestComponent::class)
        ->instance()
        ->nestedList()
        ->addable(false)
        ->editable(false)
        ->deletable(false);

    // `getAction()` rather than `getAddAction()`: the registered actions have been prepared against
    // the schema component, which is what lets the `visible()` closure resolve `$component`.
    expect($field->getAction('add')->isVisible())->toBeFalse()
        ->and($field->getAction('addChild')->isVisible())->toBeFalse()
        ->and($field->getAction('edit')->isVisible())->toBeFalse()
        ->and($field->getAction('delete')->isVisible())->toBeFalse();
});

it('renders no item action markup when the field is not addable, editable or deletable', function () {
    // `Action::toHtml()` has no visibility guard of its own, so the view's `@if` wrappers are the
    // only thing suppressing these buttons — and the test above never renders HTML.
    $html = Livewire::test(NestedListTestComponent::class, [
        'addable' => false,
        'editable' => false,
        'deletable' => false,
    ])
        ->set('data.items', [
            'a' => ['name' => 'A', 'children' => []],
        ])
        ->html();

    expect(substr_count($html, 'mountAction'))->toBe(0);
});

it('lets the action be customised through the modify hook', function () {
    $field = Livewire::test(NestedListTestComponent::class)
        ->instance()
        ->nestedList()
        ->addAction(fn ($action) => $action->label('Custom add'));

    expect($field->getAddAction()->getLabel())->toBe('Custom add');
});
