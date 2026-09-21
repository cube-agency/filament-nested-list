@php
    use Filament\Support\Enums\Alignment;

    $addAction = $getAction('add');

    $isAddable = $isAddable();
    $isDeletable = $isDeletable();
    $isEditable = $isEditable();

    $childrenKey = $getChildrenKey();
    $key = $getKey();
    $maxDepth = $getMaxDepth();
    $statePath = $getStatePath();

    $isSortable = ! $isDisabled();

    // Carries the Livewire id, so two components with a field of the same name stay separate.
    $group = $getLivewireKey();

    [$addChildAction, $editAction, $deleteAction] = [
        $getAction('addChild'),
        $getAction('edit'),
        $getAction('delete'),
    ];
@endphp

{{-- Takes itself as its first argument: @capture closes over the variables defined above it, so it
     can see neither itself nor anything assigned later — new row inputs go in the block above. --}}
@capture($renderRow, $renderRow, $row, $itemStatePath, $depth)
    @php
        $hitDepthLimit = $depth >= $maxDepth;

        $itemPrefix = $getItemPrefix($row);
        $itemSuffix = $getItemSuffix($row);

        $children = $row[$childrenKey] ?? null;
        $children = is_array($children) ? $children : [];
    @endphp

    <div
        wire:key="{{ $itemStatePath }}"
        data-id="{{ $itemStatePath }}"
        class="fi-nested-list-item"
        data-sortable-item
    >
        <div class="fi-nested-list-item-header">
            <div class="fi-nested-list-item-label-ctn">
                @if ($isSortable)
                    <div class="fi-nested-list-item-handle" data-sortable-handle>
                        <x-filament::icon
                            :icon="\Filament\Support\Icons\Heroicon::OutlinedBars2"
                            class="fi-nested-list-item-handle-icon"
                        />
                    </div>
                @endif

                @if (filled($itemPrefix))
                    <div class="fi-nested-list-item-prefix">
                        {{ $itemPrefix }}
                    </div>
                @endif

                <div class="fi-nested-list-item-label">
                    {{ $getItemLabel($row) }}
                </div>
            </div>

            @if (filled($itemSuffix))
                <div class="fi-nested-list-item-suffix">
                    {{ $itemSuffix }}
                </div>
            @endif

            <div class="fi-nested-list-item-actions">
                @if ($isAddable && ! $hitDepthLimit)
                    {{ $addChildAction(['statePath' => $itemStatePath]) }}
                @endif

                @if ($isEditable)
                    {{ $editAction(['statePath' => $itemStatePath]) }}
                @endif

                @if ($isDeletable)
                    {{ $deleteAction(['statePath' => $itemStatePath]) }}
                @endif
            </div>
        </div>

        <div
            wire:key="{{ $itemStatePath }}.{{ $childrenKey }}"
            class="fi-nested-list-item-children"
            @if ($isSortable)
                x-data="nestedList({
                    group: @js($group),
                    key: @js($key),
                    statePath: @js($itemStatePath . '.' . $childrenKey),
                    maxDepth: @js($maxDepth),
                })"
            @endif
        >
            @foreach ($children as $uuid => $child)
                {{ $renderRow($renderRow, $child, $itemStatePath . '.' . $childrenKey . '.' . $uuid, $depth + 1) }}
            @endforeach
        </div>
    </div>
@endcapture

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="fi-nested-list">
        @if ($isAddable && $addAction->isVisible())
            <div
                @class([
                    'fi-nested-list-add-ctn',
                    match ($getAddActionAlignment()) {
                        Alignment::Start, Alignment::Left => 'fi-align-start',
                        Alignment::End, Alignment::Right => 'fi-align-end',
                        default => 'fi-align-center',
                    },
                ])
            >
                {{ $addAction }}
            </div>
        @endif

        <div
            class="fi-nested-list-items"
            @if ($isSortable)
                x-data="nestedList({
                    group: @js($group),
                    key: @js($key),
                    statePath: @js($statePath),
                    maxDepth: @js($maxDepth),
                })"
            @endif
        >
            @forelse ($getState() as $uuid => $row)
                {{ $renderRow($renderRow, $row, $statePath . '.' . $uuid, 0) }}
            @empty
                <div class="fi-nested-list-empty">
                    <div class="fi-nested-list-empty-icon-ctn">
                        <x-filament::icon
                            :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                            class="fi-nested-list-empty-icon"
                        />
                    </div>

                    {{ __('filament-nested-list::nested-list.empty.heading') }}
                </div>
            @endforelse
        </div>
    </div>
</x-dynamic-component>
