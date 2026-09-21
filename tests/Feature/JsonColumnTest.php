<?php

use CubeAgency\FilamentNestedList\Forms\NestedList;
use CubeAgency\FilamentNestedList\Tests\Fixtures\JsonMenu;
use CubeAgency\FilamentNestedList\Tests\Fixtures\JsonTreeTestComponent;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

// Without `relationship()` the field is an ordinary Filament field: the tree is dehydrated into the
// form state, and the page persists it however it likes — here, a json column.

/**
 * @return array<string, mixed>
 */
function jsonTree(): array
{
    return [
        'a' => [
            'name' => 'A',
            'url' => '/a',
            'children' => [
                'a1' => ['name' => 'A1', 'url' => '/a1', 'children' => []],
            ],
        ],
        'b' => ['name' => 'B', 'url' => '/b', 'children' => []],
    ];
}

function jsonMenu(?array $tree = null): JsonMenu
{
    return JsonMenu::create(['name' => 'Main', 'items' => $tree ?? jsonTree()]);
}

function jsonComponent(?JsonMenu $menu = null, ?int $maxDepth = 2)
{
    return Livewire::test(JsonTreeTestComponent::class, [
        'menu' => ($menu ?? jsonMenu())->id,
        'maxDepth' => $maxDepth,
    ]);
}

it('dehydrates the tree, unlike a field bound to a relationship', function () {
    expect(NestedList::make('items')->isDehydrated())->toBeTrue()
        ->and(NestedList::make('items')->relationship()->isDehydrated())->toBeFalse();
});

it('hydrates the tree from the attribute', function () {
    expect(jsonComponent()->get('data.items'))->toBe(jsonTree());
});

it('renders the tree from the attribute', function () {
    jsonComponent()
        ->assertOk()
        ->assertSee('A')
        ->assertSee('A1')
        ->assertSee('B');
});

it('renders the empty state when the column is null', function () {
    $menu = JsonMenu::create(['name' => 'Empty']);

    jsonComponent($menu)
        ->assertOk()
        ->assertSee(__('filament-nested-list::nested-list.empty.heading'));
});

it('hydrates an empty tree when the column is null', function () {
    $menu = JsonMenu::create(['name' => 'Empty']);

    expect(jsonComponent($menu)->instance()->nestedList()->getState())->toBe([]);
});

it('writes the tree back to the attribute unchanged', function () {
    $menu = jsonMenu();

    $component = jsonComponent($menu);

    // Cleared behind the component's back, so the tree can only come from the save.
    JsonMenu::whereKey($menu->id)->update(['items' => null]);

    $component->call('save');

    expect(JsonMenu::find($menu->id)->items)->toBe(jsonTree());
});

it('needs no scope, since nothing is rebuilt within one', function () {
    $menu = jsonMenu();

    $component = jsonComponent($menu);

    expect($component->instance()->nestedList()->getScope())->toBeNull();

    JsonMenu::whereKey($menu->id)->update(['items' => null]);

    $component->call('save');

    expect(JsonMenu::find($menu->id)->items)->toBe(jsonTree());
});

it('saves a reorder to the attribute', function () {
    $menu = jsonMenu();

    $component = jsonComponent($menu);

    $component->call(
        'callSchemaComponentMethod',
        $component->instance()->nestedList()->getKey(),
        'sortItems',
        [
            'targetStatePath' => 'data.items',
            'targetItemsStatePaths' => ['data.items.b', 'data.items.a'],
        ],
    );

    $component->call('save');

    expect(array_keys(JsonMenu::find($menu->id)->items))->toBe(['b', 'a']);
});

it('saves a re-parenting to the attribute', function () {
    $menu = jsonMenu();

    $component = jsonComponent($menu);

    $component->call(
        'callSchemaComponentMethod',
        $component->instance()->nestedList()->getKey(),
        'sortItems',
        [
            'targetStatePath' => 'data.items.b.children',
            'targetItemsStatePaths' => ['data.items.a.children.a1'],
        ],
    );

    $component->call('save');

    $tree = JsonMenu::find($menu->id)->items;

    expect($tree['a']['children'])->toBe([])
        ->and(array_column($tree['b']['children'], 'name'))->toBe(['A1']);
});

it('saves an added item to the attribute', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(TestAction::make('add')->schemaComponent('items'), ['name' => 'Added'])
        ->call('save');

    expect(array_column(JsonMenu::find($menu->id)->items, 'name'))->toBe(['A', 'B', 'Added']);
});

it('saves an added child to the attribute', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(
            TestAction::make('addChild')
                ->schemaComponent('items')
                ->arguments(['statePath' => 'data.items.b']),
            ['name' => 'Child'],
        )
        ->call('save');

    $tree = JsonMenu::find($menu->id)->items;

    expect(array_column($tree['b']['children'], 'name'))->toBe(['Child']);
});

it('saves an edit to the attribute', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(
            TestAction::make('edit')
                ->schemaComponent('items')
                ->arguments(['statePath' => 'data.items.a']),
            ['name' => 'Renamed'],
        )
        ->call('save');

    expect(JsonMenu::find($menu->id)->items['a']['name'])->toBe('Renamed');
});

it('saves a deletion to the attribute', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(
            TestAction::make('delete')
                ->schemaComponent('items')
                ->arguments(['statePath' => 'data.items.a']),
        )
        ->call('save');

    expect(array_keys(JsonMenu::find($menu->id)->items))->toBe(['b']);
});

it('still guards the max depth without a relationship', function () {
    $menu = jsonMenu();

    $component = jsonComponent($menu, maxDepth: 1);

    $component->call(
        'callSchemaComponentMethod',
        $component->instance()->nestedList()->getKey(),
        'sortItems',
        [
            'targetStatePath' => 'data.items.b.children',
            'targetItemsStatePaths' => ['data.items.a'],
        ],
    );

    $component->call('save');

    expect(JsonMenu::find($menu->id)->items)->toBe(jsonTree());
});

// Item keys are only identities within the state. Existing ones are preserved on save, and a new
// item gets a UUID, so the column holds a keyed object rather than a json list.
it('keys the stored tree by item rather than by position', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(TestAction::make('add')->schemaComponent('items'), ['name' => 'Added'])
        ->call('save');

    $keys = array_keys(JsonMenu::find($menu->id)->items);

    expect($keys)->toHaveCount(3)
        ->and(array_slice($keys, 0, 2))->toBe(['a', 'b'])
        ->and($keys[2])->toMatch('/^[0-9a-f-]{36}$/');
});

// The keys carry nothing here, so a `formatStateUsing()` / `dehydrateStateUsing()` pair can store a
// json list instead. `afterStateHydrated()` would replace the field's own callback, not run beside it.
it('stores a json list when the state is converted on the way in and out', function () {
    $list = [
        ['name' => 'A', 'url' => '/a', 'children' => [
            ['name' => 'A1', 'url' => '/a1', 'children' => []],
        ]],
        ['name' => 'B', 'url' => '/b', 'children' => []],
    ];

    $menu = jsonMenu($list);

    $component = Livewire::test(JsonTreeTestComponent::class, [
        'menu' => $menu->id,
        'maxDepth' => 2,
        'listShaped' => true,
    ]);

    // Keyed for the browser, so the rows are addressable and draggable.
    $state = $component->get('data.items');

    expect(array_keys($state))->each->toMatch('/^[0-9a-f-]{36}$/')
        ->and(array_column($state, 'name'))->toBe(['A', 'B']);

    $component->call('save');

    expect(JsonMenu::find($menu->id)->items)->toBe($list);
});

it('keeps the tree out of the parent record until it is saved', function () {
    $menu = jsonMenu();

    jsonComponent($menu)
        ->callAction(TestAction::make('add')->schemaComponent('items'), ['name' => 'Added']);

    expect(JsonMenu::find($menu->id)->items)->toBe(jsonTree());
});
