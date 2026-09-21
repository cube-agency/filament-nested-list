<?php

namespace CubeAgency\FilamentNestedList\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use CubeAgency\FilamentNestedList\FilamentNestedListServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Kalnoy\Nestedset\NestedSet;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FormsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            FilamentNestedListServiceProvider::class,
            // Livewire must register last: it binds shared instances that a later `bind()` of the
            // same abstract would drop.
            LivewireServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('view.paths', [
            ...config('view.paths'),
            __DIR__ . '/Fixtures/views',
        ]);
    }

    protected function setUpDatabase(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('menus', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uuid')->nullable();
            $table->string('name')->nullable();
        });

        $schema->create('menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            $table->string('name');
            $table->string('url')->nullable();
            $table->nullableMorphs('owner');
            // Only `SoftDeletingMenuItem` reads this; the other models on this table ignore it.
            $table->softDeletes();
            NestedSet::columns($table);
        });

        // Shared by the models whose nested set scope does not match what the field configures.
        $schema->create('scoped_menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('menu_id');
            $table->unsignedInteger('tenant_id')->nullable();
            $table->string('name');
            NestedSet::columns($table);
        });

        // Covers a relationship whose local key is not the parent's primary key.
        $schema->create('uuid_menu_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('menu_uuid')->nullable();
            $table->string('name');
            NestedSet::columns($table);
        });

        // No nested set at all: the whole tree lives in one json column.
        $schema->create('json_menus', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->json('items')->nullable();
        });
    }
}
