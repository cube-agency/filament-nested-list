<?php

namespace CubeAgency\FilamentNestedList\Forms\Concerns;

use Closure;
use CubeAgency\FilamentNestedList\Forms\NestedList;
use Filament\Actions\Action;
use Filament\Forms\Components\Concerns\CanGenerateUuids;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

trait HasItemActions
{
    use CanGenerateUuids;

    protected bool|Closure $isAddable = true;

    protected bool|Closure $isEditable = true;

    protected bool|Closure $isDeletable = true;

    protected ?Closure $modifyAddActionUsing = null;

    protected ?Closure $modifyAddChildActionUsing = null;

    protected ?Closure $modifyDeleteActionUsing = null;

    protected ?Closure $modifyEditActionUsing = null;

    public function getAddAction(): Action
    {
        $action = Action::make('add')
            ->label(__('filament-nested-list::nested-list.actions.add.label'))
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->schema(fn (NestedList $component): array => $component->getItemSchema())
            ->action(function (NestedList $component, array $data): void {
                $uuid = $component->generateUuid();

                $items = $component->getState();

                $items[$uuid] = [
                    $component->getChildrenKey() => [],
                    ...$data,
                ];

                $component->state($items);

                $component->callAfterStateUpdated();
            })
            ->button()
            ->size(Size::Small)
            ->visible(fn (NestedList $component): bool => $component->isAddable());

        if ($this->modifyAddActionUsing) {
            $action = $this->evaluate($this->modifyAddActionUsing, [
                'action' => $action,
            ]) ?? $action;
        }

        return $action;
    }

    public function addAction(?Closure $callback): static
    {
        $this->modifyAddActionUsing = $callback;

        return $this;
    }

    public function getAddChildAction(): Action
    {
        $action = Action::make('addChild')
            ->label(__('filament-nested-list::nested-list.actions.add_child.label'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->schema(fn (NestedList $component): array => $component->getItemSchema())
            ->action(function (NestedList $component, array $arguments, array $data): void {
                $statePath = $component->resolveItemStatePath($arguments['statePath'] ?? null);

                if ($statePath === null) {
                    return;
                }

                $items = $component->getState();

                if (! is_array(data_get($items, $statePath))) {
                    return;
                }

                // The row view hides the button at the limit; this holds if the action is reached
                // another way.
                if ($component->getPathDepth($statePath) + 1 > $component->getDeepestAllowedDepth()) {
                    return;
                }

                $uuid = $component->generateUuid();

                data_set($items, ("$statePath." . $component->getChildrenKey() . ".$uuid"), [
                    $component->getChildrenKey() => [],
                    ...$data,
                ]);

                $component->state($items);

                $component->callAfterStateUpdated();
            })
            ->size(Size::ExtraSmall)
            ->visible(fn (NestedList $component): bool => $component->isAddable());

        if ($this->modifyAddChildActionUsing) {
            $action = $this->evaluate($this->modifyAddChildActionUsing, [
                'action' => $action,
            ]) ?? $action;
        }

        return $action;
    }

    public function addChildAction(?Closure $callback): static
    {
        $this->modifyAddChildActionUsing = $callback;

        return $this;
    }

    public function getEditAction(): Action
    {
        $action = Action::make('edit')
            ->label(__('filament-nested-list::nested-list.actions.edit.label'))
            ->iconButton()
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->schema(fn (NestedList $component): array => $component->getItemSchema())
            ->fillForm(function (NestedList $component, array $arguments): array {
                $statePath = $component->resolveItemStatePath($arguments['statePath'] ?? null);

                if ($statePath === null) {
                    return [];
                }

                $item = data_get($component->getState(), $statePath);

                return is_array($item) ? $item : [];
            })
            ->action(function (NestedList $component, array $arguments, array $data): void {
                $statePath = $component->resolveItemStatePath($arguments['statePath'] ?? null);

                if ($statePath === null) {
                    return;
                }

                $state = $component->getState();

                $item = data_get($state, $statePath);

                if (! is_array($item)) {
                    return;
                }

                data_set($state, $statePath, array_merge($item, $data));

                $component->state($state);

                $component->callAfterStateUpdated();
            })
            ->size(Size::ExtraSmall)
            ->visible(fn (NestedList $component): bool => $component->isEditable());

        if ($this->modifyEditActionUsing) {
            $action = $this->evaluate($this->modifyEditActionUsing, [
                'action' => $action,
            ]) ?? $action;
        }

        return $action;
    }

    public function editAction(?Closure $callback): static
    {
        $this->modifyEditActionUsing = $callback;

        return $this;
    }

    public function getDeleteAction(): Action
    {
        $action = Action::make('delete')
            ->label(__('filament-nested-list::nested-list.actions.delete.label'))
            ->iconButton()
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (NestedList $component, array $arguments): void {
                $statePath = $component->resolveItemStatePath($arguments['statePath'] ?? null);

                if ($statePath === null) {
                    return;
                }

                $items = $component->getState();

                data_forget($items, $statePath);

                $component->state($items);

                $component->callAfterStateUpdated();
            })
            ->size(Size::ExtraSmall)
            ->visible(fn (NestedList $component): bool => $component->isDeletable());

        if ($this->modifyDeleteActionUsing) {
            $action = $this->evaluate($this->modifyDeleteActionUsing, [
                'action' => $action,
            ]) ?? $action;
        }

        return $action;
    }

    public function deleteAction(?Closure $callback): static
    {
        $this->modifyDeleteActionUsing = $callback;

        return $this;
    }

    public function addable(bool|Closure $condition = true): static
    {
        $this->isAddable = $condition;

        return $this;
    }

    public function isAddable(): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        return (bool) $this->evaluate($this->isAddable);
    }

    public function editable(bool|Closure $condition = true): static
    {
        $this->isEditable = $condition;

        return $this;
    }

    public function isEditable(): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        return (bool) $this->evaluate($this->isEditable);
    }

    public function deletable(bool|Closure $condition = true): static
    {
        $this->isDeletable = $condition;

        return $this;
    }

    public function isDeletable(): bool
    {
        if ($this->isDisabled()) {
            return false;
        }

        return (bool) $this->evaluate($this->isDeletable);
    }
}
