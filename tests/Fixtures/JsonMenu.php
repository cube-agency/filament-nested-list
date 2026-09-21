<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Holds its tree in a json column rather than a related nested set, covering the field used without
 * `relationship()`.
 */
class JsonMenu extends Model
{
    protected $table = 'json_menus';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @var array<string, string>
     */
    protected $casts = ['items' => 'array'];
}
