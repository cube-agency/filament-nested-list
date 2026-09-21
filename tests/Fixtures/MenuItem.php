<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class MenuItem extends Model
{
    use NodeTrait;

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
