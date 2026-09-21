<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

/**
 * Leaves `getScopeAttributes()` at the `NodeTrait` default of `null`, so its scoped queries cover
 * the whole table.
 */
class UnscopedMenuItem extends Model
{
    use NodeTrait;

    protected $table = 'scoped_menu_items';

    protected $guarded = [];

    public $timestamps = false;
}
