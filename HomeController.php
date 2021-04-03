<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\v1\User;
use App\Models\v1\Ad;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use App\Models\v1\Category;
use App\Models\v1\Subcategory;
use App\Models\v1\Country;
use App\Models\v1\State;
use App\Models\v1\City;
use App\Models\v1\Neighborhood;
use App\Models\v1\DirectorySetting;
use App\Models\v1\DirectoryType;
use App\Models\v1\SellerDetails;
use App\Models\v1\PaymentGateway;
use App\Models\v1\PaymentFrequency;
use App\Models\v1\CurrencyUser;
use App\Models\v1\TypeAd;
use App\Models\v1\PriceTable;
use App\Jobs\v1\CreatePaymentGatewayWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\v1\ImportNiche;
use App\Models\v1\Directory;
use Illuminate\Support\Facades\Storage;
use App\Jobs\v1\AppendToSitemap;
use App\Jobs\v1\CloseSitemap;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('teste');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    protected $counts = [];
    protected $migrationFiles = [];

    public function getNewMigrationName($migrationName, $index) {
      $explode = explode('_', $migrationName);
      $lastChunk = $explode[count($explode) - 1];

      if(is_numeric($lastChunk)) {
        $explode[count($explode) - 1] = $index;

        return implode('_', $explode);
      }

      return $migrationName.'_'.$index;
    }

    public function renameMigration($firstMigration, $migration, $index) {
      $migrationName = $this->getMigrationName($migration);
      $newName = $this->getNewMigrationName($migrationName, $index);
      $newName = str_replace('-', '_', $newName);

      // dump('index ' . $index);
      // dump('oldname ' . $migrationName);
      // dump('newname ' . $newName);
      // dump('firstMigration ' . $firstMigration);
      // dump('$this->counts[$firstMigration][count] ' . $this->counts[$firstMigration]['count']);

      $this->updateMigrationFile($migration, $newName);
      $this->updateMigrationsDatabase($migration, $newName);

      $equalNamedMigration = $this->findEqualName($this->migrationFiles, $newName);
      // dump('$equalNamedMigration ' . $equalNamedMigration);
      if($equalNamedMigration) {
        // dump('$equalNamedMigration ' . $this->migrationFiles[$equalNamedMigration]);

        $this->renameMigration(
          $firstMigration,
          $this->migrationFiles[$equalNamedMigration],
          ++$this->counts[$firstMigration]['count']
        );
      }

      // dump($migration);
      // dd($newName);

    }

    public function updateMigrationFile($migrationFile, $newName) {
      // dump('updateMigrationFile' . );
      $newFileName = substr($migrationFile, 0, 18) . $newName . '.php';

      $oldFileContent = file_get_contents($this->getMigrationsPath().'/'.$migrationFile);
      // $oldFileContent = str_replace($this->getMigrationFileClassName($migrationFile), $this->getMigrationFileClassName($newFileName), $oldFileContent);
      $newFileContent = preg_replace("~(?<=class\s)(?<=\s)(\w+)~", $this->getMigrationFileClassName($newFileName), $oldFileContent);

      file_put_contents($this->getMigrationsPath().'/'.$migrationFile, $newFileContent);

      rename(
        $this->getMigrationsPath().'/'.$migrationFile,
        $this->getMigrationsPath().'/'.$newFileName
      );

      if($index = array_search($migrationFile, $this->migrationFiles))
        $migrationFiles[$index] = $newFileName;
    }

    public function updateMigrationsDatabase($migration, $newName) {
      $migration = substr($migration, 0, -4);
      $newFileName = substr($migration, 0, 18) . $newName;

      $affected = DB::update('update migrations set migration = ? where migration = ?', [$newFileName, $migration]);
    }

    public function findEqualName($migrationFiles, $name) {
      foreach ($migrationFiles as $key => $migration) {
        if($this->getMigrationName($migration) === $name)
          return $key;
      }

      return null;
    }

    public function getMigrationFileClassName($migrationName)
    {
      return Str::studly(substr($migrationName, 18, (strlen($migrationName)-18-4)));
    }

    public function removeDashFromFileNames() {
      $migrationFiles = array_diff(scandir($this->getMigrationsPath()), array('.', '..'));

      foreach ($migrationFiles as $key => $migration) {
        $newFileName = str_replace('-', '_', $migration);

        rename(
          $this->getMigrationsPath().'/'.$migration,
          $this->getMigrationsPath().'/'.$newFileName
        );

        $affected = DB::update('update migrations set migration = ? where migration = ?', [
          substr($newFileName, 0, -4),
          substr($migration, 0, -4)
        ]);
      }
    }

    public function teste()
    {
      ini_set('max_execution_time', 9999);

      $this->removeDashFromFileNames();

      $this->migrationFiles = array_diff(scandir($this->getMigrationsPath()), array('.', '..'));
      // dump($this->migrationFiles);

      foreach ($this->migrationFiles as $key => $migration) {
        // dump('migration ' . $migration);

        $migrationName = $this->getMigrationName($migration);

        // dump('migrationName ' . $migrationName);

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

      dd($this->counts);
      // // dd(__('notifications.ads.ad_created_1'));
      // // dd(base_path('../'));

      //   dd(serialize($teste));
      //   dd(json_encode($teste, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));

      //   dd($teste);


      //   // $settings = DirectorySetting::all();
      //   // foreach ($settings as $key => $setting) {
      //   //   $config = json_decode($setting->config_layout);

      //   //   if($config->menus->home_footer->featured_text->popular_tags === true)
      //   //     dump($setting);
      //   // }

      //   // dd(Directory::find(343)->buildSitemap());
      //   // dd($directory->neighborhoods()->get());
      //   // $start = $directory->neighborhoods()->orderBy('id', 'asc')->first();
      //   // $last = $directory->neighborhoods()->orderBy('id', 'desc')->first();
      //   // $range = 100;

      //   // for ($i=$start->id; $i <= $last->id; $i+=$range) {
      //   //     AppendToSitemap::dispatch($directory, 'neighborhoods', $i, $range, '', 'sitemap.xml');
      //   // }
      //   // dump($directory->neighborhoods()->count());
      //   // dump(CloseSitemap::dispatch($directory, 'sitemap.xml'));

        return 'true';
    }

    public function getMigrationsPath() {
      return database_path('migrations');
    }

    // return the migration name. ex: the file 2019_07_15_205741_alter_users_table_2.php would return alter_users_table
    public function getMigrationName($migration) {
      $substr = substr($migration, 18, (strlen($migration)-18-4));

      while (is_numeric(substr($substr, -1)) || substr($substr, -1) === '_') {
        $substr = substr($substr, 0, -1);
      }

      return $substr;
    }

    public function getMigrationNameIndex($name) {

    }

}
