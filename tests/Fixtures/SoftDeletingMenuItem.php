<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

class SoftDeletingMenuItem extends Model
{
    use NodeTrait;
    use SoftDeletes;

    protected $table = 'menu_items';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string>
     */
    protected function getScopeAttributes(): array
    {
        return ['menu_id'];
    }
}
