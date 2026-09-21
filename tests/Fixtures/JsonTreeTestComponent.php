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
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * A nested list with no `relationship()` and no `scope()`, saved like any other field: the tree is
 * dehydrated into the form state, and the page writes that to a json column.
 */
class JsonTreeTestComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public ?JsonMenu $record = null;

    public ?int $maxDepth = null;

    public bool $listShaped = false;

    public function mount(int $menu, ?int $maxDepth = null, bool $listShaped = false): void
    {
        $this->maxDepth = $maxDepth;
        $this->listShaped = $listShaped;

        $this->record = JsonMenu::findOrFail($menu);

        $this->getSchema('form')->fill($this->record->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $field = NestedList::make('items')
            ->maxDepth($this->maxDepth)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('url'),
            ]);

        // The recipe the README gives for storing a json list rather than a keyed object.
        if ($this->listShaped) {
            $field
                ->formatStateUsing(fn (?array $state): array => static::keyTree($state ?? []))
                ->dehydrateStateUsing(fn (array $state): array => static::listTree($state));
        }

        return $schema
            ->statePath('data')
            ->model($this->record)
            ->components([$field]);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<string, mixed>
     */
    protected static function keyTree(array $items): array
    {
        $keyed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $children = $item['children'] ?? null;
            $item['children'] = static::keyTree(is_array($children) ? $children : []);

            $keyed[(string) Str::uuid()] = $item;
        }

        return $keyed;
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<int, mixed>
     */
    protected static function listTree(array $items): array
    {
        $list = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $children = $item['children'] ?? null;
            $item['children'] = static::listTree(is_array($children) ? $children : []);

            $list[] = $item;
        }

        return $list;
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
        $this->record->update($this->getSchema('form')->getState());
    }

    public function render(): View
    {
        return view('nested-list-test-component');
    }
}
