<?php

namespace App\Providers;

use App\Models\LegacyInstitution;
use App\Providers\Postgres\DatabaseServiceProvider;
use App\Services\CacheManager;
use App\Services\StudentUnificationService;
use Exception;
use iEducar\Modules\ErrorTracking\HoneyBadgerTracker;
use iEducar\Modules\ErrorTracking\Tracker;
use iEducar\Support\Navigation\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Load migrations from other repositories or packages.
     *
     * @return void
     */
    private function loadLegacyMigrations()
    {
        foreach (config('legacy.migrations') as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }

    /**
     * Load legacy bootstrap application.
     *
     * @return void
     *
     * @throws Exception
     */
    private function loadLegacyBootstrap()
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        date_default_timezone_set(config('legacy.app.locale.timezone'));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     *
     * @throws Exception
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadLegacyMigrations();
        }

        if (env('ASSETS_SECURE')) {
            URL::forceScheme('https');
        }

        $this->loadLegacyBootstrap();

        Collection::macro('getKeyValueArray', function ($valueField) {
            $keyValueArray = [];
            foreach ($this->items as $item) {
                $keyValueArray[$item->getKey()] = $item->getAttribute($valueField);
            }

            return $keyValueArray;
        });

        SchemaBuilder::defaultStringLength(191);

        Paginator::defaultView('vendor.pagination.default');

        QueryBuilder::macro('whereUnaccent', function ($column, $value) {
            $this->whereRaw('unaccent(' . $column . ') ilike unaccent(\'%\' || ? || \'%\')', [$value]);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Breadcrumb::class);

        if ($this->app->environment('development', 'local', 'testing')) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(Tracker::class, HoneyBadgerTracker::class);

        $this->app->bind(LegacyInstitution::class, function () {
            return LegacyInstitution::query()->where('ativo', 1)->firstOrFail();
        });

        $this->app->bind(StudentUnificationService::class, function () {
            return new StudentUnificationService(Auth::user());
        });

        Cache::swap(new CacheManager(app()));
        $this->app->register(DatabaseServiceProvider::class);
    }
}
