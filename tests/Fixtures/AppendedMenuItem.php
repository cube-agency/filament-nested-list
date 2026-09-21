<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class AppendedMenuItem extends Model
{
    use NodeTrait;

    protected $table = 'menu_items';

    /**
     * @var array<string>
     */
    protected $fillable = ['menu_id', 'name', 'url'];

    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $appends = ['slug'];

    public function getSlugAttribute(): string
    {
        return str($this->name ?? '')->slug()->toString();
    }

    /**
     * @return array<string>
     */
    protected function getScopeAttributes(): array
    {
        return ['menu_id'];
    }
}
