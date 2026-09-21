# Filament Nested List

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cube-agency/filament-nested-list.svg?style=flat-square)](https://packagist.org/packages/cube-agency/filament-nested-list)
[![Total Downloads](https://img.shields.io/packagist/dt/cube-agency/filament-nested-list.svg?style=flat-square)](https://packagist.org/packages/cube-agency/filament-nested-list)

A drag-and-drop nested list form field for Filament. Useful for menus, category trees, and any other ordered hierarchy.

Items are added, edited and deleted through action modals built from a schema you supply, and reordered and re-parented by dragging. The tree persists either as a nested set on a related model — through [`kalnoy/nestedset`](https://github.com/lazychaser/laravel-nestedset) and `rebuildTree()` — or as a plain `json` column on the record itself.

## Requirements

- PHP 8.2+
- Laravel 11.28+
- Filament v4 (on Livewire v3) or v5 (on Livewire v4)

## Installation

```bash
composer require cube-agency/filament-nested-list
```

The plugin ships its own compiled CSS and JS and registers them with Filament automatically, so there is nothing to add to your theme.

Anything you want to override can be published:

```bash
php artisan vendor:publish --tag="filament-nested-list-config"
php artisan vendor:publish --tag="filament-nested-list-translations"
php artisan vendor:publish --tag="filament-nested-list-views"
```

## Prerequisites

These apply to a tree saved through a relationship. For one kept in a json column there is nothing to set up beyond a cast — see [storing the tree in a column](#storing-the-tree-in-a-column).

The related model must be a nested set with [scope support](https://github.com/lazychaser/laravel-nestedset#scoping):

```php
use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class MenuItem extends Model
{
    use NodeTrait;

    protected $fillable = ['name', 'url', 'new_tab'];

    protected function getScopeAttributes(): array
    {
        return ['menu_id'];
    }
}
```

`getScopeAttributes()` must return exactly the one column you pass to `->scope()`. Saving throws otherwise: an unscoped model would have its entire table rebuilt, taking every other parent's tree with it, and a composite scope cannot be pinned from the field.

`$fillable` must list every column the form writes, and nothing more. An item's whole state is round-tripped through the browser and handed to `rebuildTree()`, which `fill()`s each record, so `$fillable` is the only thing standing between a crafted payload and a column your form never offered. Avoid `$guarded = []` here: besides opening the table up, an `$appends` attribute with no column behind it will break the save.

Add the nested set columns in a migration with `NestedSet::columns($table)`, alongside the foreign key you scope by.

## Usage

```php
use CubeAgency\FilamentNestedList\Forms\NestedList;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            NestedList::make('items')
                ->relationship()
                ->scope('menu_id')
                ->maxDepth(2)
                ->schema([
                    TextInput::make('name')
                        ->required(),

                    TextInput::make('url')
                        ->required()
                        ->url(),

                    Checkbox::make('new_tab'),
                ]),
        ]);
}
```

`->scope()` is required whenever `->relationship()` is used — it names the column that partitions the nested set, and the tree is rebuilt within that scope on save. It must be the relationship's foreign key, or loading and rebuilding would work over different records; saving throws if it is not. The column is filled with the relationship's local key, so a `hasMany()` with a custom `localKey` is scoped correctly, and a record whose local key is `null` is refused rather than rebuilt into the unscoped rows. The rebuild runs in a transaction, so a failed save leaves the existing tree as it was. If the state still refers to a record the scope no longer holds — one deleted in another session while the form was open, say — the save is refused with a `StaleRecordsException` naming the keys, rather than failing partway through the rebuild.

`maxDepth()` counts the levels _below_ the roots, so `0` is a flat reorderable list, `1` allows children, `2` allows grandchildren. It defaults to the `max_depth` config key (`1`).

### Storing the tree in a column

`->relationship()` is opt-in. Leave it off and the field behaves like any other Filament field: the tree is dehydrated into the form state under the field's name, and your page saves it wherever it saves everything else. That includes a single `json` column — no nested set, no second model, and no `->scope()`.

```php
// app/Models/Menu.php
protected $casts = ['items' => 'array'];
```

```php
// database/migrations/…
$table->json('items')->nullable();
```

```php
NestedList::make('items')
    ->maxDepth(2)
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('url'),
    ]);
```

Everything else behaves the same — dragging, the action modals, `maxDepth()`, the affixes, `disabled()`. A `null` column hydrates as an empty tree.

Note that an item's whole state still round-trips through the browser, but here no `$fillable` stands between it and the column: whatever the state holds is what gets stored. Validate in the item schema, and treat the stored tree as user input when you read it back.

#### The stored shape

The column holds an object **keyed by item**, not a json list:

```json
{
    "e5b1c0d8-…": { "name": "Products", "url": "/products", "children": {} },
    "7a2f4e91-…": { "name": "About", "url": "/about", "children": {} }
}
```

Those keys are what keeps each row's `wire:key` stable while it is being dragged — a positional list would reindex mid-drag and break row identity. Unlike in relationship mode, where a key is derived from the record it stands for, here they are identities within the form state only and nothing reads them back.

So if a consumer downstream needs a list, convert on the way out and re-key on the way in:

```php
NestedList::make('items')
    ->formatStateUsing(fn (?array $state): array => static::keyTree($state ?? []))
    ->dehydrateStateUsing(fn (array $state): array => static::listTree($state));
```

```php
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
```

Use `formatStateUsing()` rather than `afterStateHydrated()`: Filament stores a single hydration callback, so the latter would replace the field's own instead of running beside it. Both hooks above are otherwise unused by the field. Re-keying on every load is safe precisely because the keys mean nothing in this mode.

### Item prefixes and suffixes

Each row can carry something either side of its label — a type badge, a warning, an icon:

```php
use Illuminate\Support\HtmlString;

NestedList::make('items')
    ->itemSuffix(fn (array $item): ?string => $item['type'] ?? null)
    ->itemPrefix(fn (array $item) => ($item['is_hidden'] ?? false)
        ? new HtmlString('<span class="fi-badge fi-color-warning">Hidden</span>')
        : null);
```

The closure is handed that item's state as `$item` and runs once per row. A string is escaped; an `Htmlable` (including a `view()`) is rendered as markup. Return `null` or `''` and the row gets nothing — the element is omitted entirely.

An item's state is the record's full set of attributes, minus the nested set columns, so any column on the model is available here whether or not the item schema offers a field for it.

## API

### Structure

| Method                                             | Description                                                                                                                        |
| -------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `schema(array\|Closure)`                           | Components for the add/edit modals. `components()` is an alias.                                                                    |
| `relationship(string\|Closure\|null $name = null)` | Load and save through a `HasMany` relationship. Defaults to the field name. Omit it to keep the tree in the field's own attribute. |
| `scope(string\|Closure\|null)`                     | Column the nested set is scoped by. Required with `relationship()`, and must be the relationship's foreign key.                    |
| `maxDepth(int\|Closure\|null)`                     | Nesting depth, as above. Falls back to config when `null`.                                                                         |
| `childrenKey(string\|Closure)`                     | State key holding an item's children. Default `children`.                                                                          |
| `labelKey(string\|Closure\|null)`                  | State key rendered as each item's label. Falls back to `name` when `null`.                                                         |

### Appearance and permissions

| Method                                                 | Description                                                       |
| ------------------------------------------------------ | ----------------------------------------------------------------- |
| `itemPrefix(string\|Htmlable\|Closure\|null)`          | Shown before an item's label. A closure receives it as `$item`.   |
| `itemSuffix(string\|Htmlable\|Closure\|null)`          | Shown after the label, before the actions. Same closure argument. |
| `addActionAlignment(Alignment\|string\|Closure\|null)` | Alignment of the add button. Default `Alignment::End`.            |
| `addable(bool\|Closure = true)`                        | Toggle the add and add-child actions.                             |
| `editable(bool\|Closure = true)`                       | Toggle the edit action.                                           |
| `deletable(bool\|Closure = true)`                      | Toggle the delete action.                                         |

The last three are forced off when the field is `disabled()`.

### Customising the actions

Each action can be modified with a closure receiving `$action`:

```php
use Filament\Actions\Action;

NestedList::make('items')
    ->addAction(fn (Action $action) => $action->label('New item'))
    ->addChildAction(fn (Action $action) => $action->color('info'))
    ->editAction(fn (Action $action) => $action->modalHeading('Edit item'))
    ->deleteAction(fn (Action $action) => $action->requiresConfirmation(false));
```

The matching `getAddAction()`, `getAddChildAction()`, `getEditAction()` and `getDeleteAction()` methods return the built `Filament\Actions\Action` instances.

## Contributing

```bash
composer test    # Pest
composer analyse # PHPStan
npm test         # the drag guard's depth arithmetic
```

`resources/dist` is a committed artifact. Run `npm run build` after changing anything under `resources/js` or `resources/css`, or the built files that ship to consumers will be stale.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Dmitrijs Mihailovs](https://github.com/cube-agency)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
