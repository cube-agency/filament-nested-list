import Sortable from 'sortablejs'

import { canAcceptDrop, getSubtreeHeight, movesPastMaxDepth } from './depth.js'

const CHILDREN_SELECTOR = '.fi-nested-list-item-children'
const DROPPABLE_CLASS = 'fi-nested-list-item-children-droppable'

// `onStart` and `onMove` fire on different Sortable instances when a drag crosses lists, and only
// one drag runs at a time, so these live outside the component.
let draggedSubtreeHeight = 0
let droppableLists = []

/**
 * Open up the empty children lists the dragged item could be dropped into. Their depth cannot
 * change while the item is in the air, so this holds for the whole drag.
 */
function openDroppableLists(item, maxDepth) {
    for (const list of item.closest('.fi-nested-list')?.querySelectorAll(CHILDREN_SELECTOR) ?? []) {
        // The item's own subtree travels with it, so it is never a destination.
        if (item.contains(list) || !canAcceptDrop(list, maxDepth, draggedSubtreeHeight)) {
            continue
        }

        list.classList.add(DROPPABLE_CLASS)
        droppableLists.push(list)
    }
}

function closeDroppableLists() {
    for (const list of droppableLists) {
        list.classList.remove(DROPPABLE_CLASS)
    }

    droppableLists = []
}

document.addEventListener('alpine:initializing', () => {
    window.Alpine.data('nestedList', ({ group, key, statePath, maxDepth }) => ({
        group,
        key,
        statePath,
        // Mirrors `getDeepestAllowedDepth()`: an unset limit means no nesting, not no guard.
        maxDepth: Math.max(0, maxDepth ?? 0),
        sortable: null,

        init() {
            this.sortable = new Sortable(this.$el, {
                // The Livewire id as well as the field key, so no two lists that should stay
                // separate can exchange rows.
                group: `nested-list-${this.group}`,
                animation: 150,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                draggable: '[data-sortable-item]',
                handle: '[data-sortable-handle]',
                onStart: (evt) => {
                    draggedSubtreeHeight = getSubtreeHeight(evt.item)

                    openDroppableLists(evt.item, this.maxDepth)
                },
                onEnd: () => {
                    closeDroppableLists()
                },
                onMove: (evt) => {
                    // Refused before the DOM changes, so the server never has to undo it.
                    if (
                        movesPastMaxDepth(
                            evt.related,
                            evt.dragged,
                            this.maxDepth,
                            draggedSubtreeHeight,
                        )
                    ) {
                        return false
                    }
                },
                onSort: () => {
                    // Keys must match `NestedList::sortItems()`'s parameter names; Filament spreads
                    // them as named arguments.
                    this.$wire.callSchemaComponentMethod(this.key, 'sortItems', {
                        targetStatePath: this.statePath,
                        targetItemsStatePaths: this.sortable.toArray(),
                    })
                },
            })
        },

        destroy() {
            // Re-parenting changes each moved row's `wire:key`, so Livewire replaces these
            // elements on the re-render that follows a sort.
            this.sortable?.destroy()
            this.sortable = null

            // A re-render mid-drag would otherwise leave the opened lists padded out.
            closeDroppableLists()
        },
    }))
})
