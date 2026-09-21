<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

// These cover the v3 -> v4/v5 replacement of `registerListeners('nested-list::sort')` with an
// `#[ExposedLivewireMethod]` method reached through `$wire.callSchemaComponentMethod()`.

function sortableState(): array
{
    return [
        'a' => ['name' => 'A', 'children' => []],
        'b' => ['name' => 'B', 'children' => []],
        'c' => ['name' => 'C', 'children' => []],
    ];
}

it('reorders items at the root level', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', sortableState());

    $key = $component->instance()->nestedList()->getKey();

    $component->call('callSchemaComponentMethod', $key, 'sortItems', [
        'targetStatePath' => 'data.items',
        'targetItemsStatePaths' => ['data.items.c', 'data.items.a', 'data.items.b'],
    ]);

    expect(array_keys($component->get('data.items')))->toBe(['c', 'a', 'b']);
});

it('reorders items within a nested level', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'parent' => [
                'name' => 'Parent',
                'children' => [
                    'x' => ['name' => 'X', 'children' => []],
                    'y' => ['name' => 'Y', 'children' => []],
                ],
            ],
        ]);

    $key = $component->instance()->nestedList()->getKey();

    $component->call('callSchemaComponentMethod', $key, 'sortItems', [
        'targetStatePath' => 'data.items.parent.children',
        'targetItemsStatePaths' => [
            'data.items.parent.children.y',
            'data.items.parent.children.x',
        ],
    ]);

    expect(array_keys($component->get('data.items.parent.children')))->toBe(['y', 'x']);
});

it('moves an item into another parent', function () {
    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', [
            'p1' => ['name' => 'P1', 'children' => []],
            'p2' => ['name' => 'P2', 'children' => []],
        ]);

    $key = $component->instance()->nestedList()->getKey();

    // The browser has already moved the node; it reports p2 as a child of p1.
    $component->call('callSchemaComponentMethod', $key, 'sortItems', [
        'targetStatePath' => 'data.items.p1.children',
        'targetItemsStatePaths' => ['data.items.p2'],
    ]);

    expect($component->get('data.items.p1.children'))->toHaveKey('p2')
        ->and($component->get('data.items.p1.children.p2.name'))->toBe('P2');
});

it('ignores state paths that do not belong to the component', function () {
    $original = sortableState();

    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', $original);

    $key = $component->instance()->nestedList()->getKey();

    $component->call('callSchemaComponentMethod', $key, 'sortItems', [
        'targetStatePath' => 'data.somethingElse',
        'targetItemsStatePaths' => ['data.somethingElse.a'],
    ]);

    expect($component->get('data.items'))->toBe($original);
});

it('does not expose arbitrary methods to the frontend', function () {
    $original = sortableState();

    $component = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', $original);

    $key = $component->instance()->nestedList()->getKey();

    // `state()` is public on the field but carries no `#[ExposedLivewireMethod]`, so Filament must
    // refuse to dispatch to it.
    $component->call('callSchemaComponentMethod', $key, 'state', ['state' => []]);

    expect($component->get('data.items'))->toBe($original);
});

/**
 * Three roots, each with two or three children, each of those with one or two children of its own.
 *
 * @return array<string, mixed>
 */
function deepTree(): array
{
    $spec = [
        'r1' => [
            'r1c1' => ['r1c1g1', 'r1c1g2'],
            'r1c2' => ['r1c2g1'],
            'r1c3' => ['r1c3g1'],
        ],
        'r2' => [
            'r2c1' => ['r2c1g1', 'r2c1g2'],
            'r2c2' => ['r2c2g1'],
        ],
        'r3' => [
            'r3c1' => ['r3c1g1'],
            'r3c2' => ['r3c2g1', 'r3c2g2'],
            'r3c3' => ['r3c3g1'],
        ],
    ];

    $build = function (array $nodes) use (&$build): array {
        $items = [];

        foreach ($nodes as $key => $children) {
            if (is_int($key)) {
                [$key, $children] = [$children, []];
            }

            $items[$key] = [
                'name' => strtoupper($key),
                'children' => $build($children),
            ];
        }

        return $items;
    };

    return $build($spec);
}

function deepTreeComponent(int $maxDepth = 2)
{
    return Livewire::test(NestedListTestComponent::class, ['maxDepth' => $maxDepth])
        ->set('data.items', deepTree());
}

/**
 * @param  array<string>  $targetItemsStatePaths
 */
function sortLevel($component, string $targetStatePath, array $targetItemsStatePaths): void
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

it('reorders the root level with every subtree left intact', function () {
    $component = deepTreeComponent();
    $original = deepTree();

    sortLevel($component, 'data.items', ['data.items.r3', 'data.items.r1', 'data.items.r2']);

    expect(array_keys($component->get('data.items')))->toBe(['r3', 'r1', 'r2'])
        ->and($component->get('data.items.r3'))->toBe($original['r3'])
        ->and($component->get('data.items.r1'))->toBe($original['r1'])
        ->and($component->get('data.items.r2'))->toBe($original['r2']);
});

it('reorders children within a parent without touching its siblings', function () {
    $component = deepTreeComponent();
    $original = deepTree();

    sortLevel($component, 'data.items.r1.children', [
        'data.items.r1.children.r1c3',
        'data.items.r1.children.r1c1',
        'data.items.r1.children.r1c2',
    ]);

    expect(array_keys($component->get('data.items.r1.children')))->toBe(['r1c3', 'r1c1', 'r1c2'])
        ->and($component->get('data.items.r1.children.r1c1.children'))
        ->toBe($original['r1']['children']['r1c1']['children'])
        ->and($component->get('data.items.r2'))->toBe($original['r2'])
        ->and($component->get('data.items.r3'))->toBe($original['r3']);
});

it('reorders grandchildren at the third level', function () {
    $component = deepTreeComponent();
    $original = deepTree();

    sortLevel($component, 'data.items.r3.children.r3c2.children', [
        'data.items.r3.children.r3c2.children.r3c2g2',
        'data.items.r3.children.r3c2.children.r3c2g1',
    ]);

    expect(array_keys($component->get('data.items.r3.children.r3c2.children')))
        ->toBe(['r3c2g2', 'r3c2g1'])
        ->and($component->get('data.items.r3.children.r3c1'))
        ->toBe($original['r3']['children']['r3c1'])
        ->and($component->get('data.items.r3.children.r3c3'))
        ->toBe($original['r3']['children']['r3c3']);
});

it('moves a child and its own children under a different root', function () {
    $component = deepTreeComponent();
    $original = deepTree();

    sortLevel($component, 'data.items.r2.children', [
        'data.items.r2.children.r2c1',
        'data.items.r1.children.r1c1',
        'data.items.r2.children.r2c2',
    ]);

    sortLevel($component, 'data.items.r1.children', [
        'data.items.r1.children.r1c2',
        'data.items.r1.children.r1c3',
    ]);

    expect(array_keys($component->get('data.items.r2.children')))->toBe(['r2c1', 'r1c1', 'r2c2'])
        ->and($component->get('data.items.r2.children.r1c1'))
        ->toBe($original['r1']['children']['r1c1'])
        ->and(array_keys($component->get('data.items.r1.children')))->toBe(['r1c2', 'r1c3']);
});

it('moves a grandchild up to the root level', function () {
    $component = deepTreeComponent();
    $original = deepTree();

    sortLevel($component, 'data.items', [
        'data.items.r1',
        'data.items.r2.children.r2c1.children.r2c1g1',
        'data.items.r2',
        'data.items.r3',
    ]);

    sortLevel($component, 'data.items.r2.children.r2c1.children', [
        'data.items.r2.children.r2c1.children.r2c1g2',
    ]);

    expect(array_keys($component->get('data.items')))->toBe(['r1', 'r2c1g1', 'r2', 'r3'])
        ->and($component->get('data.items.r2c1g1'))
        ->toBe($original['r2']['children']['r2c1']['children']['r2c1g1'])
        ->and(array_keys($component->get('data.items.r2.children.r2c1.children')))->toBe(['r2c1g2']);
});

it('moves a root and its whole subtree into the deepest level of another root', function () {
    $component = deepTreeComponent(maxDepth: 5);
    $original = deepTree();

    sortLevel($component, 'data.items.r2.children.r2c1.children.r2c1g1.children', ['data.items.r3']);

    sortLevel($component, 'data.items', ['data.items.r1', 'data.items.r2']);

    expect(array_keys($component->get('data.items')))->toBe(['r1', 'r2'])
        ->and($component->get('data.items.r2.children.r2c1.children.r2c1g1.children.r3'))
        ->toBe($original['r3']);
});

it('leaves the tree untouched when the reported order is unchanged', function () {
    $component = deepTreeComponent();

    sortLevel($component, 'data.items', ['data.items.r1', 'data.items.r2', 'data.items.r3']);

    expect($component->get('data.items'))->toBe(deepTree());
});

it('allows a descending move whose deepest descendant lands exactly on the max depth', function () {
    $component = deepTreeComponent(maxDepth: 3);
    $original = deepTree();

    sortLevel($component, 'data.items.r2.children.r2c1.children', [
        'data.items.r2.children.r2c1.children.r2c1g1',
        'data.items.r1.children.r1c1',
        'data.items.r2.children.r2c1.children.r2c1g2',
    ]);

    expect(array_keys($component->get('data.items.r2.children.r2c1.children')))
        ->toBe(['r2c1g1', 'r1c1', 'r2c1g2'])
        ->and($component->get('data.items.r2.children.r2c1.children.r1c1'))
        ->toBe($original['r1']['children']['r1c1']);
});

it('allows a reorder of a tree that is already deeper than the max depth', function () {
    $component = deepTreeComponent(maxDepth: 0);

    sortLevel($component, 'data.items.r1.children', [
        'data.items.r1.children.r1c3',
        'data.items.r1.children.r1c1',
        'data.items.r1.children.r1c2',
    ]);

    expect(array_keys($component->get('data.items.r1.children')))->toBe(['r1c3', 'r1c1', 'r1c2']);
});

it('rejects a move that would place an item past the max depth', function () {
    $component = deepTreeComponent();

    sortLevel($component, 'data.items.r2.children.r2c1.children.r2c1g1.children', ['data.items.r3']);

    expect($component->get('data.items'))->toBe(deepTree());
});

it('rejects a move whose descendants would exceed the max depth even though the destination does not', function () {
    $component = deepTreeComponent();

    // `r1c1` itself would land on the limit, but it carries a child that would fall past it.
    sortLevel($component, 'data.items.r2.children.r2c1.children', ['data.items.r1.children.r1c1']);

    expect($component->get('data.items'))->toBe(deepTree());
});

it('rejects nesting when the max depth is zero', function () {
    $component = deepTreeComponent(maxDepth: 0);

    sortLevel($component, 'data.items.r1.children', ['data.items.r2']);

    expect($component->get('data.items'))->toBe(deepTree());
});

it('still reorders the root level when the max depth is zero', function () {
    $component = deepTreeComponent(maxDepth: 0);

    sortLevel($component, 'data.items', ['data.items.r3', 'data.items.r1', 'data.items.r2']);

    expect(array_keys($component->get('data.items')))->toBe(['r3', 'r1', 'r2']);
});

it('runs after-state-updated callbacks when a level is reordered', function () {
    $field = Livewire::test(NestedListTestComponent::class)
        ->set('data.items', sortableState())
        ->instance()
        ->nestedList();

    $calls = 0;

    $field->afterStateUpdated(function () use (&$calls): void {
        $calls++;
    });

    $field->sortItems('data.items', ['data.items.c', 'data.items.a', 'data.items.b']);

    expect($calls)->toBe(1);
});
