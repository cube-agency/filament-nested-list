import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { canAcceptDrop, getDepth, getSubtreeHeight, movesPastMaxDepth } from './depth.js'

// A stand-in for the only parts of the DOM these helpers touch: `parentElement`, `closest()` and
// `querySelectorAll()`, matching only `[data-sortable-item]`.

function item(...children) {
    return node(true, children)
}

function list(...children) {
    return node(false, children)
}

function node(isItem, children) {
    const el = {
        isItem,
        children,
        parentElement: null,

        closest(selector) {
            assert.equal(selector, '[data-sortable-item]')

            let candidate = el

            while (candidate) {
                if (candidate.isItem) {
                    return candidate
                }

                candidate = candidate.parentElement
            }

            return null
        },

        querySelectorAll(selector) {
            assert.equal(selector, '[data-sortable-item]')

            const descendants = []

            const walk = (parent) => {
                for (const child of parent.children) {
                    if (child.isItem) {
                        descendants.push(child)
                    }

                    walk(child)
                }
            }

            walk(el)

            return descendants
        },
    }

    for (const child of children) {
        child.parentElement = el
    }

    return el
}

describe('getDepth', () => {
    it('reports zero for a root item', () => {
        const root = item()

        list(root)

        assert.equal(getDepth(root), 0)
    })

    it('counts the item ancestors of a nested item', () => {
        const grandchild = item()
        const child = item(list(grandchild))
        const root = item(list(child))

        list(root)

        assert.equal(getDepth(child), 1)
        assert.equal(getDepth(grandchild), 2)
    })

    it('reports the level depth of a children list', () => {
        const childrenList = list()
        const root = item(childrenList)

        list(root)

        assert.equal(getDepth(childrenList), 1)
    })
})

describe('getSubtreeHeight', () => {
    it('reports zero for an item with no children', () => {
        assert.equal(getSubtreeHeight(item()), 0)
    })

    it('reports the number of levels below the item', () => {
        const oneLevel = item(list(item()))
        const twoLevels = item(list(item(list(item()))))

        assert.equal(getSubtreeHeight(oneLevel), 1)
        assert.equal(getSubtreeHeight(twoLevels), 2)
    })

    it('reports the deepest branch when they differ', () => {
        const subject = item(list(item(), item(list(item()))))

        assert.equal(getSubtreeHeight(subject), 2)
    })

    it('does not depend on where the item currently sits', () => {
        const subject = item(list(item()))

        list(item(list(subject)))

        assert.equal(getSubtreeHeight(subject), 1)
    })
})

describe('movesPastMaxDepth', () => {
    it('allows a move whose deepest descendant lands on the limit', () => {
        const dragged = item(list(item()))
        const destination = item()

        list(item(list(destination)), dragged)

        // Destination level 1, dragged item carries one level, so its deepest lands at 2.
        assert.equal(movesPastMaxDepth(destination, dragged, 2), false)
    })

    it('refuses a move whose descendants would fall past the limit', () => {
        const dragged = item(list(item()))
        const destination = item()

        list(item(list(item(list(destination)))), dragged)

        // The case the server used to refuse only *after* the browser had moved the item.
        assert.equal(movesPastMaxDepth(destination, dragged, 2), true)
    })

    it('uses a supplied subtree height rather than measuring the dragged item', () => {
        const dragged = item()
        const destination = item()

        list(item(list(destination)), dragged)

        // Measured, the item is a leaf and the move would be allowed. The caller reports the height
        // it took at the start of the drag instead.
        assert.equal(movesPastMaxDepth(destination, dragged, 2, 2), true)
    })

    it('allows a childless item to land exactly on the limit', () => {
        const dragged = item()
        const destination = item()

        list(item(list(item(list(destination)))), dragged)

        assert.equal(movesPastMaxDepth(destination, dragged, 2), false)
    })

    it('refuses a move past the limit even for an item with no children', () => {
        const dragged = item()
        const destination = item()

        list(item(list(item(list(item(list(destination)))))), dragged)

        assert.equal(movesPastMaxDepth(destination, dragged, 2), true)
    })

    it('allows reordering within a level that is already past the limit', () => {
        const dragged = item()
        const sibling = item()

        list(item(list(item(list(dragged, sibling)))))

        // Lowering the limit on an existing tree must not freeze it.
        assert.equal(movesPastMaxDepth(sibling, dragged, 1), false)
    })

    it('allows the move when there is no element to compare against', () => {
        assert.equal(movesPastMaxDepth(null, item(), 1), false)
        assert.equal(movesPastMaxDepth(item(), null, 1), false)
    })

    it('allows a move that does not descend', () => {
        const dragged = item(list(item()))
        const destination = item()

        list(item(list(dragged)), item(list(destination)))

        assert.equal(movesPastMaxDepth(destination, dragged, 1), false)
    })
})

describe('canAcceptDrop', () => {
    it('allows a list whose deepest arrival lands on the limit', () => {
        const childrenList = list()

        list(item(childrenList))

        // The list sits at level 1 and the item carries one level, so its deepest lands at 2.
        assert.equal(canAcceptDrop(childrenList, 2, 1), true)
    })

    it('refuses a list whose arrival would fall past the limit', () => {
        const childrenList = list()

        list(item(childrenList))

        assert.equal(canAcceptDrop(childrenList, 2, 2), false)
    })

    it('refuses every children list when nesting is off', () => {
        const childrenList = list()

        list(item(childrenList))

        assert.equal(canAcceptDrop(childrenList, 0, 0), false)
    })
})
