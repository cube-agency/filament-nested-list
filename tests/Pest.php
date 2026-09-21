<?php

use CubeAgency\FilamentNestedList\Tests\Fixtures\Menu;
use CubeAgency\FilamentNestedList\Tests\Fixtures\MenuItem;
use CubeAgency\FilamentNestedList\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function menuWithTree(): Menu
{
    $menu = Menu::create(['name' => 'Main']);

    $first = MenuItem::create(['menu_id' => $menu->id, 'name' => 'First', 'url' => '/first']);
    MenuItem::create(['menu_id' => $menu->id, 'name' => 'Second', 'url' => '/second']);

    MenuItem::create([
        'menu_id' => $menu->id,
        'name' => 'First child',
        'url' => '/first-child',
        'parent_id' => $first->id,
    ]);

    return $menu;
}
