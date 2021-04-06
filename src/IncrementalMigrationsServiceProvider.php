<?php

namespace ThiagoBrauer\LaravelIncrementalMigrations;

use Illuminate\Database\MigrationServiceProvider;
use ThiagoBrauer\LaravelIncrementalMigrations\Commands\CustomMigrateMakeCommand;
use ThiagoBrauer\LaravelIncrementalMigrations\Commands\IncrementalMigrationsFixCommand;

class IncrementalMigrationsServiceProvider extends MigrationServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->app->when(MigrationCreator::class)
            ->needs('$customStubPath')
            ->give(function ($app) {
                return $app->basePath('stubs');
            });

        $this->registerMigrateMakeCommand();

        $this->app->singleton('command.incremental-migrations.fix', function($app) {
          return new IncrementalMigrationsFixCommand;
        });

        $this->commands('command.incremental-migrations.fix');
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateMakeCommand()
    {
        $this->app->singleton('command.migrate.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['migration.creator'];

            $composer = $app['composer'];

            return new CustomMigrateMakeCommand($creator, $composer);
        });
    }
}
