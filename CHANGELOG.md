# Changelog

All notable changes to `filament-nested-list` will be documented in this file.

## 1.0.0

Initial release.

A drag-and-drop nested list form field for Filament. Items are managed through action modals built from a schema you supply, and reordered and re-parented by dragging. The tree persists either as a scoped nested set on a related model, through `kalnoy/nestedset` and `rebuildTree()`, or as a plain `json` column on the record itself.

### Requirements

- Filament v4 or v5
- PHP 8.2+
- Laravel 11.28+

### Features

- `NestedList` form field with add, add-child, edit and delete item actions, each customisable through a modify hook.
- `relationship()` / `scope()` for loading and saving a scoped nested set through a `HasMany` relationship.
- Omitting `relationship()` leaves the field an ordinary dehydrated field, so the tree can be kept in a `json` column on the record itself, with no nested set and no second model. Items are stored keyed rather than as a list, keeping each row's `wire:key` stable across a drag.
- `maxDepth()` limiting, enforced while dragging — counting the dragged item's own descendants, not just its destination — when rendering the add-child action, and again on the server.
- Configurable `childrenKey()` and `labelKey()`, and `addable()` / `editable()` / `deletable()` toggles that are forced off when the field is disabled.
- Ships a compiled stylesheet and registers it with Filament, so the field is styled correctly without a custom theme — necessary on Filament v4+, which no longer ships raw Tailwind utilities in its compiled CSS.
- Translations for the action labels and the empty state.
- Drag-to-sort is handled by `NestedList::sortItems()`, exposed to JavaScript with `#[ExposedLivewireMethod]` and called through `$wire.callSchemaComponentMethod()`.

### Handling of browser input

The tree is held in the schema's state, which is a public Livewire property, and each item is written back through `rebuildTree()`. Everything reaching the field from the browser is therefore treated as untrusted:

- A drag across levels is reported by SortableJS as two independent sorts, one per level. Each is applied as a complete move, so the pair is order-independent, and a sort can never remove an item from the tree altogether — a move refused for exceeding `maxDepth` leaves the item where it was instead of deleting it and, on save, its rows.
- State paths are checked to address an item of this field before anything is written with them, in the item actions as well as in `sortItems()`. Paths the state no longer holds are ignored rather than stored as `null`.
- An item cannot be dropped into its own subtree.
- `_lft`, `_rgt`, `parent_id` and the scope column are neither sent to the browser nor read back from it: `rebuildTree()` derives them from the shape of the tree, and the scope is pinned to the record being saved, so an item cannot be moved into another scope. Declare `$fillable` on the related model to guard the remaining columns.
- A tree kept in a `json` column has no `$fillable` between the state and storage, since nothing is filled onto a model. Whatever the state holds is stored, so validate in the item schema and treat the stored tree as user input when reading it back.

### Dependencies

`kalnoy/nestedset` is required at `^6.0.5|^7.0`. Note that `6.0.7` narrowed itself to `illuminate/support <=12.0`, so it cannot be installed alongside Laravel 12.1+; Composer resolves to `6.0.6` on Laravel 11 and 12, and to `7.0` once Laravel 13 is in play. The API this package uses is identical across both.

`ryangjchandler/blade-capture-directive` is required directly at `^1.0`: it powers the `@capture` directive the field's view depends on, and cannot be relied on transitively since `filament/support` ships it inconsistently across the supported `^4.0|^5.0` range.
