<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class UuidMenuItem extends Model
{
    use NodeTrait;

    protected $table = 'uuid_menu_items';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string>
     */
    protected function getScopeAttributes(): array
    {
        return ['menu_uuid'];
    }
}
