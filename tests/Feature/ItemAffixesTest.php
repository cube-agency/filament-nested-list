<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

function affixTree(array $parent = []): array
{
    return [
        'parent' => [
            'name' => 'Parent',
            'type' => 'Page',
            'icon' => 'Icon',
            'children' => [
                'child' => ['name' => 'Child', 'type' => 'Link', 'children' => []],
            ],
            ...$parent,
        ],
    ];
}

it('renders a suffix built from each item', function () {
    Livewire::test(NestedListTestComponent::class, ['affixes' => 'state'])
        ->set('data.items', affixTree())
        ->assertOk()
        ->assertSee('Page')
        ->assertSee('Link');
});

it('renders a prefix built from each item', function () {
    Livewire::test(NestedListTestComponent::class, ['affixes' => 'state'])
        ->set('data.items', affixTree())
        ->assertOk()
        ->assertSee('Icon');
});

it('renders no affix elements when none are configured', function () {
    Livewire::test(NestedListTestComponent::class)
        ->set('data.items', affixTree())
        ->assertOk()
        ->assertDontSee('fi-nested-list-item-prefix')
        ->assertDontSee('fi-nested-list-item-suffix');
});

it('omits the element for an item whose affix is blank', function () {
    // The child has no `icon`, so only the parent gets a prefix.
    $html = Livewire::test(NestedListTestComponent::class, ['affixes' => 'state'])
        ->set('data.items', affixTree())
        ->html();

    expect(substr_count($html, 'fi-nested-list-item-prefix'))->toBe(1)
        ->and(substr_count($html, 'fi-nested-list-item-suffix'))->toBe(2);
});

it('accepts a plain string used for every item', function () {
    $html = Livewire::test(NestedListTestComponent::class, ['affixes' => 'static'])
        ->set('data.items', affixTree())
        ->html();

    expect(substr_count($html, 'Suffix'))->toBe(2)
        ->and(substr_count($html, 'Prefix'))->toBe(2);
});

it('escapes a string affix', function () {
    Livewire::test(NestedListTestComponent::class, ['affixes' => 'state'])
        ->set('data.items', affixTree(['type' => '<b>Boom</b>']))
        ->assertOk()
        ->assertDontSee('<b>Boom</b>', escape: false)
        ->assertSee('<b>Boom</b>');
});

it('renders an htmlable affix as markup', function () {
    Livewire::test(NestedListTestComponent::class, ['affixes' => 'html'])
        ->set('data.items', affixTree())
        ->assertOk()
        ->assertSee('<span class="test-badge">Page</span>', escape: false);
});

it('drops an affix that cannot be rendered', function () {
    // Closures are user code over browser-writable state, so the return value is not certainly a string.
    Livewire::test(NestedListTestComponent::class, ['affixes' => 'invalid'])
        ->set('data.items', affixTree())
        ->assertOk()
        ->assertDontSee('fi-nested-list-item-suffix');
});
