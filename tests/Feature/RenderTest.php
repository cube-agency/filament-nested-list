<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

it('renders the empty state without errors', function () {
    // Regression: the empty state used to reference an undefined `$model` variable and a
    // `filament-tables::` translation key from a package that is not a dependency.
    Livewire::test(NestedListTestComponent::class)
        ->assertOk()
        ->assertSee('No items yet');
});

it('renders a nested tree without errors', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'parent' => [
                'name' => 'Parent',
                'children' => [
                    'child' => [
                        'name' => 'Child',
                        'children' => [],
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->assertSee('Parent')
        ->assertSee('Child')
        ->assertDontSee('No items yet');
});

it('renders rows using a custom children key', function () {
    // Regression: the row markup used to read `$row['children']` regardless of the configured key.
    // The nested item is named so it cannot be matched inside the `addChild` action's own markup.
    Livewire::test(NestedListTestComponent::class, ['childrenKey' => 'kids'])
        ->set('data.items', [
            'parent' => [
                'name' => 'Parent',
                'kids' => [
                    'child' => ['name' => 'Nested item', 'kids' => []],
                ],
            ],
        ])
        ->assertOk()
        ->assertSee('Nested item');
});

it('scopes the sortable group to the livewire component', function () {
    $tree = ['a' => ['name' => 'A', 'children' => []]];

    $first = Livewire::test(NestedListTestComponent::class)->set('data.items', $tree);
    $second = Livewire::test(NestedListTestComponent::class)->set('data.items', $tree);

    $groups = function (string $html): array {
        preg_match_all('/group: ([^,]+),/', $html, $matches);

        return array_values(array_unique($matches[1]));
    };

    // Every list of one field shares a group so items can be dragged between levels, but two
    // components with a field of the same name must not exchange rows.
    expect($groups($first->html()))->toHaveCount(1)
        ->and($groups($second->html()))->not->toBe($groups($first->html()));
});

it('does not render an add-child action past the max depth', function () {
    $component = Livewire::test(NestedListTestComponent::class);

    $component->instance()->maxDepth = 1;

    $component
        ->set('data.items', [
            'parent' => [
                'name' => 'Parent',
                'children' => [
                    'child' => ['name' => 'Child', 'children' => []],
                ],
            ],
        ])
        ->assertOk();
});
