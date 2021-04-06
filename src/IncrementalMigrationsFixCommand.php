<?php

namespace ThiagoBrauer\LaravelIncrementalMigrations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IncrementalMigrationsFixCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'incremental-migrations:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix existing migrations to use incremental names and avoid a class declaration error';

    /**
     * Amount of each repeated migrations.
     *
     * @var array
     */
    protected $counts = [];

    /**
     * List of files found in the migrations folder.
     *
     * @var array
     */
    protected $migrationFiles = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $this->info('Checking migrations...');

      $this->removeDashFromFileNames();

      $this->migrationFiles = array_diff(scandir($this->getMigrationsPath()), array('.', '..'));

      foreach ($this->migrationFiles as $key => $migration) {
        $migrationName = $this->getMigrationName($migration);

        if(isset($this->counts[$migrationName])) {
          $this->counts[$migrationName]['count']++;

          $this->renameMigration($migrationName, $migration, $this->counts[$migrationName]['count']);
        } else {
          $this->counts[$migrationName] = [
            'migrationFileName' => $migration,
            'count' => 1
          ];
        }
      }

      $this->info('Migrations fixed!');
    }

    /**
     * Check all the migration files' names to remove any "-"
     *
     * @return void
     */
    protected function removeDashFromFileNames() {
      $migrationFiles = array_diff(scandir($this->getMigrationsPath()), array('.', '..'));

      foreach ($migrationFiles as $key => $migration) {
        if(strpos($migration, '-') !== false) {
          $newFileName = str_replace('-', '_', $migration);

          rename(
            $this->getMigrationsPath().'/'.$migration,
            $this->getMigrationsPath().'/'.$newFileName
          );

          DB::update('update migrations set migration = ? where migration = ?', [
            substr($newFileName, 0, -4),
            substr($migration, 0, -4)
          ]);
        }
      }
    }

    /**
     * Get the migrations folder path.
     *
     * @return string
     */
    protected function getMigrationsPath() {
      return database_path('migrations');
    }

    /**
     * Get the migration name. ex: the file 2019_07_15_205741_alter_users_table_2.php would
     * return alter_users_table
     *
     * @param string $migration
     * @return string
     */
    protected function getMigrationName($migration) {
      $substr = substr($migration, 18, (strlen($migration)-18-4));

      while (is_numeric(substr($substr, -1)) || substr($substr, -1) === '_') {
        $substr = substr($substr, 0, -1);
      }

      return $substr;
    }

    /**
     *
     */
    protected function getNewMigrationName($migrationName, $index) {
      $explode = explode('_', $migrationName);
      $lastChunk = $explode[count($explode) - 1];

      if(is_numeric($lastChunk)) {
        $explode[count($explode) - 1] = $index;

        return implode('_', $explode);
      }

      return $migrationName.'_'.$index;
    }

    /**
     *
     */
    protected function renameMigration($firstMigration, $migration, $index) {
      $migrationName = $this->getMigrationName($migration);
      $newName = $this->getNewMigrationName($migrationName, $index);
      $newName = str_replace('-', '_', $newName);

      $this->updateMigrationFile($migration, $newName);
      $this->updateMigrationsDatabase($migration, $newName);

      $equalNamedMigration = $this->findEqualName($this->migrationFiles, $newName);

      if($equalNamedMigration) {
        $this->renameMigration(
          $firstMigration,
          $this->migrationFiles[$equalNamedMigration],
          ++$this->counts[$firstMigration]['count']
        );
      }
    }

    /**
     *
     */
    protected function updateMigrationFile($migrationFile, $newName) {
      $newFileName = substr($migrationFile, 0, 18) . $newName . '.php';

      $oldFileContent = file_get_contents($this->getMigrationsPath().'/'.$migrationFile);
      $newFileContent = preg_replace("~(?<=class\s)(?<=\s)(\w+)~", $this->getMigrationFileClassName($newFileName), $oldFileContent);

      file_put_contents($this->getMigrationsPath().'/'.$migrationFile, $newFileContent);

      rename(
        $this->getMigrationsPath().'/'.$migrationFile,
        $this->getMigrationsPath().'/'.$newFileName
      );

      if($index = array_search($migrationFile, $this->migrationFiles))
        $this->migrationFiles[$index] = $newFileName;
    }

    /**
     *
     */
    protected function updateMigrationsDatabase($migration, $newName) {
      $migration = substr($migration, 0, -4);
      $newFileName = substr($migration, 0, 18) . $newName;

      $affected = DB::update('update migrations set migration = ? where migration = ?', [$newFileName, $migration]);
    }

    /**
     * Check $migrationFiles if there is a migration with the given name.
     */
    protected function findEqualName($migrationFiles, $name) {
      foreach ($migrationFiles as $key => $migration) {
        if($this->getMigrationName($migration) === $name)
          return $key;
      }

      return null;
    }

    /**
     *
     */
    protected function getMigrationFileClassName($migrationName)
    {
      return Str::studly(substr($migrationName, 18, (strlen($migrationName)-18-4)));
    }
}
