<?php

namespace CubeAgency\FilamentNestedList\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Menu extends Model
{
    protected $table = 'menus';

    protected $guarded = [];

    public $timestamps = false;

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function appendedItems(): HasMany
    {
        return $this->hasMany(AppendedMenuItem::class, 'menu_id');
    }

    public function softDeletingItems(): HasMany
    {
        return $this->hasMany(SoftDeletingMenuItem::class, 'menu_id');
    }

    public function uuidItems(): HasMany
    {
        return $this->hasMany(UuidMenuItem::class, 'menu_uuid', 'uuid');
    }

    public function unscopedItems(): HasMany
    {
        return $this->hasMany(UnscopedMenuItem::class);
    }

    public function compositeItems(): HasMany
    {
        return $this->hasMany(CompositeMenuItem::class);
    }

    public function firstItem(): HasOne
    {
        return $this->hasOne(MenuItem::class);
    }

    public function morphedItems(): MorphMany
    {
        return $this->morphMany(MenuItem::class, 'owner');
    }
}
