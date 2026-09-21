/**
 * Depth arithmetic for the drag guard, mirroring `NestedList::movesItemPastMaxDepth()`. The two must
 * agree: a move the browser allows and the server then refuses leaves the tree half-moved.
 */

const ITEM_SELECTOR = '[data-sortable-item]'

/** How many levels sit above the element. Root items, and the list holding them, are at zero. */
export function getDepth(el) {
    let depth = 0
    let ancestor = el.parentElement

    while (ancestor) {
        ancestor = ancestor.closest(ITEM_SELECTOR)

        if (!ancestor) {
            break
        }

        depth++
        ancestor = ancestor.parentElement
    }

    return depth
}

/** How many levels the item carries below it, measured relative to the item so it holds mid-drag. */
export function getSubtreeHeight(el) {
    const depth = getDepth(el)

    let height = 0

    for (const descendant of el.querySelectorAll(ITEM_SELECTOR)) {
        height = Math.max(height, getDepth(descendant) - depth)
    }

    return height
}

/** Whether a children list can take an item carrying `subtreeHeight` levels below it. */
export function canAcceptDrop(list, maxDepth, subtreeHeight) {
    return getDepth(list) + subtreeHeight <= maxDepth
}

/**
 * Whether dropping `dragged` next to `related` would put it, or a descendant, past the limit. Moves
 * that do not descend are always allowed, so lowering the limit does not freeze an existing tree.
 */
export function movesPastMaxDepth(related, dragged, maxDepth, subtreeHeight) {
    if (!related || !dragged) {
        return false
    }

    const targetDepth = getDepth(related)

    if (targetDepth <= getDepth(dragged)) {
        return false
    }

    return targetDepth + (subtreeHeight ?? getSubtreeHeight(dragged)) > maxDepth
}
