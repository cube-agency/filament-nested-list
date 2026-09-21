<?php

use CubeAgency\FilamentNestedList\Forms\NestedList;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Alignment;

it('falls back to the configured max depth when none is set', function () {
    config()->set('filament-nested-list.max_depth', 3);

    expect(NestedList::make('items')->getMaxDepth())->toBe(3);
});

it('honours an explicit max depth of zero instead of falling back to config', function () {
    config()->set('filament-nested-list.max_depth', 3);

    // Regression: a truthiness check here used to make `maxDepth(0)` silently fall back to 3.
    expect(NestedList::make('items')->maxDepth(0)->getMaxDepth())->toBe(0);
});

it('resolves max depth from a closure', function () {
    expect(NestedList::make('items')->maxDepth(fn (): int => 5)->getMaxDepth())->toBe(5);
});

it('defaults the add action alignment to the end', function () {
    expect(NestedList::make('items')->getAddActionAlignment())->toBe(Alignment::End);
});

it('coerces a string alignment to the enum', function () {
    expect(NestedList::make('items')->addActionAlignment('start')->getAddActionAlignment())
        ->toBe(Alignment::Start);
});

it('falls back to centre for an unrecognised alignment', function () {
    expect(NestedList::make('items')->addActionAlignment('nonsense')->getAddActionAlignment())
        ->toBe(Alignment::Center);
});

it('exposes configurable children and label keys', function () {
    $field = NestedList::make('items')
        ->childrenKey('kids')
        ->labelKey('title');

    expect($field->getChildrenKey())->toBe('kids')
        ->and($field->getLabelKey())->toBe('title');
});

it('defaults to children and name keys', function () {
    $field = NestedList::make('items');

    expect($field->getChildrenKey())->toBe('children')
        ->and($field->getLabelKey())->toBe('name');
});

it('falls back to the default label key when null is passed', function () {
    expect(NestedList::make('items')->labelKey(null)->getLabelKey())->toBe('name');
});

it('falls back to the default label key when a closure resolves to null', function () {
    expect(NestedList::make('items')->labelKey(fn () => null)->getLabelKey())->toBe('name');
});

it('returns the item schema as an array', function () {
    $field = NestedList::make('items')->schema([TextInput::make('name')]);

    expect($field->getItemSchema())->toHaveCount(1)
        ->and($field->getItemSchema()[0])->toBeInstanceOf(TextInput::class);
});

it('returns an empty item schema when none is set', function () {
    expect(NestedList::make('items')->getItemSchema())->toBe([]);
});

it('is not addable, editable or deletable when disabled', function () {
    $field = NestedList::make('items')->disabled();

    expect($field->isAddable())->toBeFalse()
        ->and($field->isEditable())->toBeFalse()
        ->and($field->isDeletable())->toBeFalse();
});
