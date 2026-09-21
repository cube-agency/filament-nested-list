<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

// The item actions already refuse a disabled field through `visible()`. Dragging is the remaining
// way to change the tree, and a disabled field still has its relationships saved.

/**
 * @return array<string, mixed>
 */
function disabledTree(): array
{
    return [
        'a' => ['name' => 'A', 'children' => []],
        'b' => ['name' => 'B', 'children' => []],
    ];
}

function disabledComponent(bool $disabled = true)
{
    return Livewire::test(NestedListTestComponent::class, ['disabled' => $disabled])
        ->set('data.items', disabledTree());
}

it('refuses to reorder a disabled list', function () {
    $component = disabledComponent();

    $component->call(
        'callSchemaComponentMethod',
        $component->instance()->nestedList()->getKey(),
        'sortItems',
        [
            'targetStatePath' => 'data.items',
            'targetItemsStatePaths' => ['data.items.b', 'data.items.a'],
        ],
    );

    expect(array_keys($component->get('data.items')))->toBe(['a', 'b']);
});

it('renders no drag handle for a disabled list', function () {
    disabledComponent()
        ->assertOk()
        ->assertDontSeeHtml('data-sortable-handle');
});

it('initialises no sortable component for a disabled list', function () {
    disabledComponent()
        ->assertOk()
        ->assertDontSeeHtml('nestedList(');
});

it('renders the drag-and-drop markup when the list is not disabled', function () {
    // The guard above must not take the feature away from everyone else.
    disabledComponent(disabled: false)
        ->assertOk()
        ->assertSeeHtml('data-sortable-handle')
        ->assertSeeHtml('nestedList(');
});
