<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

// SortableJS reports the destination and source lists separately, so a cross-level drag produces
// two independent `sortItems()` calls. These cover the ways that pair can go wrong.

/**
 * @return array<string, mixed>
 */
function safetyTree(): array
{
    return [
        'r1' => [
            'name' => 'R1',
            'children' => [
                'r1c1' => [
                    'name' => 'R1C1',
                    'children' => [
                        'r1c1g1' => ['name' => 'R1C1G1', 'children' => []],
                    ],
                ],
            ],
        ],
        'r2' => [
            'name' => 'R2',
            'children' => [
                'r2c1' => ['name' => 'R2C1', 'children' => []],
            ],
        ],
    ];
}

function safetySort($component, mixed $targetStatePath, array $targetItemsStatePaths): void
{
    $component->call(
        'callSchemaComponentMethod',
        $component->instance()->nestedList()->getKey(),
        'sortItems',
        [
            'targetStatePath' => $targetStatePath,
            'targetItemsStatePaths' => $targetItemsStatePaths,
        ],
    );
}

function safetyComponent(int $maxDepth = 2)
{
    return Livewire::test(NestedListTestComponent::class, ['maxDepth' => $maxDepth])
        ->set('data.items', safetyTree());
}

it('keeps the dragged subtree when the destination write is refused', function () {
    $component = safetyComponent();

    // `r1c1` lands on the limit but its child falls past it, so the destination write is refused
    // while the browser still reports the source list without `r1c1` in it.
    safetySort($component, 'data.items.r2.children.r2c1.children', ['data.items.r1.children.r1c1']);
    safetySort($component, 'data.items.r1.children', []);

    expect($component->get('data.items.r1.children'))->toHaveKey('r1c1')
        ->and($component->get('data.items.r1.children.r1c1'))
        ->toBe(safetyTree()['r1']['children']['r1c1'])
        ->and($component->get('data.items.r2.children.r2c1.children'))->toBe([]);
});

it('removes an item from its old parent as part of the destination write', function () {
    $component = safetyComponent();

    // One call completes the move; the item is not left duplicated awaiting the source report.
    safetySort($component, 'data.items.r2.children', [
        'data.items.r2.children.r2c1',
        'data.items.r1.children.r1c1',
    ]);

    expect(array_keys($component->get('data.items.r2.children')))->toBe(['r2c1', 'r1c1'])
        ->and($component->get('data.items.r1.children'))->toBe([]);
});

it('completes the move when the source list is reported before the destination', function () {
    $component = safetyComponent();

    // Same move, calls swapped: the source write keeps the item until the destination claims it.
    safetySort($component, 'data.items.r1.children', []);

    expect($component->get('data.items.r1.children'))->toHaveKey('r1c1');

    safetySort($component, 'data.items.r2.children', [
        'data.items.r2.children.r2c1',
        'data.items.r1.children.r1c1',
    ]);

    expect(array_keys($component->get('data.items.r2.children')))->toBe(['r2c1', 'r1c1'])
        ->and($component->get('data.items.r1.children'))->toBe([]);
});

it('ignores an item path that the state no longer holds', function () {
    $component = safetyComponent();

    // Two drags in quick succession can report a path that has already moved.
    safetySort($component, 'data.items', ['data.items.r1', 'data.items.ghost', 'data.items.r2']);

    expect(array_keys($component->get('data.items')))->toBe(['r1', 'r2'])
        ->and($component->get('data.items'))->not->toHaveKey('ghost');
});

// The limit is raised for these two so it cannot be what refuses the move.
it('refuses to move an item into its own children list', function () {
    $component = safetyComponent(maxDepth: 5);

    safetySort($component, 'data.items.r2.children', ['data.items.r2']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses to move an item deeper into its own subtree', function () {
    $component = safetyComponent(maxDepth: 5);

    safetySort($component, 'data.items.r2.children.r2c1.children', ['data.items.r2']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses a sort that would collapse two items onto the same key', function () {
    // Item keys are unique in practice, so a collision only reaches here from a tampered payload.
    $tree = [
        'a' => ['name' => 'A', 'children' => ['x' => ['name' => 'AX', 'children' => []]]],
        'b' => ['name' => 'B', 'children' => ['x' => ['name' => 'BX', 'children' => []]]],
    ];

    $component = Livewire::test(NestedListTestComponent::class, ['maxDepth' => 2])
        ->set('data.items', $tree);

    safetySort($component, 'data.items', ['data.items.a.children.x', 'data.items.b.children.x']);

    // Keying the new level by the last path segment alone would drop `AX`, and saving deletes its row.
    expect($component->get('data.items'))->toBe($tree);
});

it('removes an arriving item from a parent sitting in the same list', function () {
    $component = safetyComponent(maxDepth: 5);

    // `r1c1` is dragged up to the root, where its own parent `r1` already sits.
    safetySort($component, 'data.items', [
        'data.items.r1',
        'data.items.r1.children.r1c1',
        'data.items.r2',
    ]);

    expect(array_keys($component->get('data.items')))->toBe(['r1', 'r1c1', 'r2'])
        ->and($component->get('data.items.r1.children'))->toBe([])
        ->and($component->get('data.items.r1c1'))->toBe(safetyTree()['r1']['children']['r1c1']);
});

it('refuses a sort that moves an item and its own descendant into the same list', function () {
    $component = safetyComponent(maxDepth: 5);

    // A single drag cannot report both, since `toArray()` only lists a list's direct children.
    safetySort($component, 'data.items.r2.children.r2c1.children', [
        'data.items.r1',
        'data.items.r1.children.r1c1',
    ]);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses a sort into an attribute of an item', function () {
    $component = safetyComponent();

    // Owning the path is not enough: `r1.name` is inside the field but is not a level of the tree.
    safetySort($component, 'data.items.r1.name', ['data.items.r2']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses a sort of a children collection as though it were an item', function () {
    $component = safetyComponent();

    // This would file the whole collection under a root item keyed `children`.
    safetySort($component, 'data.items', ['data.items.r1.children']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

// The data helpers read some segments as instructions, so an owned path can address more than one level.
it('refuses a sort into a level addressed by an interpreted segment', function (string $segment) {
    $component = safetyComponent();

    safetySort($component, "data.items.{$segment}.children", ['data.items.r1.children.r1c1']);

    expect($component->get('data.items'))->toBe(safetyTree());
})->with(['*', '\*', '{first}', '\{first}', '{last}', '\{last}']);

it('refuses a sort of an item addressed by an interpreted segment', function (string $segment) {
    $component = safetyComponent();

    safetySort($component, 'data.items', ["data.items.{$segment}"]);

    expect($component->get('data.items'))->toBe(safetyTree());
})->with(['*', '\*', '{first}', '\{first}', '{last}', '\{last}']);

it('refuses a sort into the children of an item the state does not hold', function () {
    $component = safetyComponent();

    // `data_set()` would invent the chain, leaving a root item the next save writes as a bare row.
    safetySort($component, 'data.items.ghost.children', ['data.items.r2']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses a sort into the children of an item that is not an array', function () {
    $tree = ['a' => 'not an item'];

    $component = Livewire::test(NestedListTestComponent::class, ['maxDepth' => 2])
        ->set('data.items', $tree);

    safetySort($component, 'data.items.a.children', []);

    expect($component->get('data.items'))->toBe($tree);
});

it('refuses a sort with an item path that is not a string', function () {
    $component = safetyComponent();

    safetySort($component, 'data.items', [null]);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('refuses a sort with a target path that is not a string', function () {
    $component = safetyComponent();

    safetySort($component, null, ['data.items.r1']);

    expect($component->get('data.items'))->toBe(safetyTree());
});

it('leaves nothing in the state that would break the next save', function () {
    $component = safetyComponent();

    safetySort($component, 'data.items', ['data.items.r1', 'data.items.ghost', 'data.items.r2']);

    // `rebuildTree()` calls `Arr::except($item, 'children')` on every entry, so a stray `null` makes
    // the form unsaveable.
    expect($component->instance()->nestedList()->toNestedSetTree($component->get('data.items')))
        ->each->toBeArray();
});
