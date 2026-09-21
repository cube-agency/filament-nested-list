<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

/**
 * Scopes its nested set by two columns, only one of which the field can be told about.
 */
class CompositeMenuItem extends Model
{
    use NodeTrait;

    protected $table = 'scoped_menu_items';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string>
     */
    protected function getScopeAttributes(): array
    {
        return ['menu_id', 'tenant_id'];
    }
}
