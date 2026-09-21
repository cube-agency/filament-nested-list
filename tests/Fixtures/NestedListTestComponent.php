<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use CubeAgency\FilamentNestedList\Forms\NestedList;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class NestedListTestComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public ?Menu $record = null;

    public ?int $maxDepth = null;

    public ?string $childrenKey = null;

    public bool $withRelationship = false;

    public bool $disabled = false;

    public ?string $relationshipName = null;

    public ?string $scope = null;

    public bool $addable = true;

    public bool $editable = true;

    public bool $deletable = true;

    public ?string $labelKey = null;

    public ?string $affixes = null;

    public function mount(
        ?int $menu = null,
        ?int $maxDepth = null,
        ?string $childrenKey = null,
        bool $disabled = false,
        ?string $relationshipName = null,
        ?string $scope = null,
        bool $addable = true,
        bool $editable = true,
        bool $deletable = true,
        ?string $labelKey = null,
        ?string $affixes = null,
    ): void {
        $this->maxDepth = $maxDepth;
        $this->childrenKey = $childrenKey;
        $this->disabled = $disabled;
        $this->relationshipName = $relationshipName;
        $this->scope = $scope;
        $this->addable = $addable;
        $this->editable = $editable;
        $this->deletable = $deletable;
        $this->labelKey = $labelKey;
        $this->affixes = $affixes;

        if ($menu === null) {
            // No record: `fill()` with no arguments applies defaults, like a create form.
            $this->getSchema('form')->fill();

            return;
        }

        $this->record = Menu::findOrFail($menu);
        $this->withRelationship = true;

        // Passing an array hydrates from existing data, which is what triggers relationship loading.
        $this->getSchema('form')->fill($this->record->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $field = NestedList::make('items')
            ->disabled($this->disabled)
            ->maxDepth($this->maxDepth)
            ->addable($this->addable)
            ->editable($this->editable)
            ->deletable($this->deletable)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('url'),
            ]);

        // Set here rather than mutated from a test, so it survives every Livewire request's
        // schema rebuild.
        if ($this->childrenKey !== null) {
            $field->childrenKey($this->childrenKey);
        }

        if ($this->labelKey !== null) {
            $field->labelKey($this->labelKey);
        }

        // Closures cannot be Livewire properties, so the variants are named instead of passed in.
        match ($this->affixes) {
            'state' => $field
                ->itemPrefix(fn (array $item): ?string => $item['icon'] ?? null)
                ->itemSuffix(fn (array $item): ?string => $item['type'] ?? null),
            'static' => $field->itemPrefix('Prefix')->itemSuffix('Suffix'),
            'html' => $field->itemSuffix(fn (array $item) => new HtmlString(
                '<span class="test-badge">' . e($item['type'] ?? '') . '</span>',
            )),
            'invalid' => $field->itemSuffix(fn (array $item): array => ['not a string']),
            default => $field,
        };

        if ($this->withRelationship) {
            $field
                ->relationship($this->relationshipName)
                ->scope($this->scope ?? 'menu_id');
        }

        return $schema
            ->statePath('data')
            ->model($this->record)
            ->components([$field]);
    }

    public function nestedList(): NestedList
    {
        /** @var NestedList */
        return $this->getSchema('form')->getComponent(
            fn ($component): bool => $component instanceof NestedList,
            withHidden: true,
        );
    }

    public function save(): void
    {
        $this->getSchema('form')->getState();

        $this->record->save();

        $this->getSchema('form')->saveRelationships();
    }

    public function render(): View
    {
        return view('nested-list-test-component');
    }
}
