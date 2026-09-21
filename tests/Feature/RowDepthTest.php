<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\NestedListTestComponent;
use Livewire\Livewire;

/**
 * Whether the row for the given item renders an add-child button. Rows are matched on their
 * `data-id` and read up to the next row, so the answer says *which* row offered the button.
 */
function rowOffersAddChild(string $html, string $itemStatePath): bool
{
    foreach (explode('data-id="', $html) as $segment) {
        if (! str_starts_with($segment, $itemStatePath . '"')) {
            continue;
        }

        return str_contains($segment, 'Add child');
    }

    throw new RuntimeException("No row was rendered for [{$itemStatePath}].");
}

function twoLevelTree(string $childrenKey = 'children'): array
{
    return [
        'parent' => [
            'name' => 'Parent',
            $childrenKey => [
                'child' => ['name' => 'Child', $childrenKey => []],
            ],
        ],
    ];
}

it('offers an add-child action at every level within the max depth', function () {
    $html = Livewire::test(NestedListTestComponent::class, ['maxDepth' => 2])
        ->set('data.items', twoLevelTree())
        ->html();

    expect(rowOffersAddChild($html, 'data.items.parent'))->toBeTrue()
        ->and(rowOffersAddChild($html, 'data.items.parent.children.child'))->toBeTrue();
});

it('stops offering an add-child action at the max depth', function () {
    $html = Livewire::test(NestedListTestComponent::class, ['maxDepth' => 1])
        ->set('data.items', twoLevelTree())
        ->html();

    expect(rowOffersAddChild($html, 'data.items.parent'))->toBeTrue()
        ->and(rowOffersAddChild($html, 'data.items.parent.children.child'))->toBeFalse();
});

it('counts depth by level rather than by matching the children key as a substring', function () {
    // Item keys are md5 hashes or UUIDs, so a hex-only children key can occur inside one — and
    // counting its occurrences in the path then overstates the depth.
    $html = Livewire::test(NestedListTestComponent::class, ['maxDepth' => 2, 'childrenKey' => 'ace'])
        ->set('data.items', [
            'aceface' => [
                'name' => 'Parent',
                'ace' => [
                    'child' => ['name' => 'Child', 'ace' => []],
                ],
            ],
        ])
        ->html();

    expect(rowOffersAddChild($html, 'data.items.aceface'))->toBeTrue()
        ->and(rowOffersAddChild($html, 'data.items.aceface.ace.child'))->toBeTrue();
});
