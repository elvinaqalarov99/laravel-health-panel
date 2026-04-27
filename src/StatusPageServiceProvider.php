<?php

namespace Elvinaqalarov99\StatusPage;

use Elvinaqalarov99\StatusPage\Contracts\StatusPageRepositoryContract;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;
use Elvinaqalarov99\StatusPage\Repositories\StatusPageRepository;
use Elvinaqalarov99\StatusPage\Services\StatusPageService;
use Illuminate\Support\ServiceProvider;

class StatusPageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/status-page.php', 'status-page');

        $this->app->bind(StatusPageRepositoryContract::class, StatusPageRepository::class);
        $this->app->bind(StatusPageServiceContract::class, StatusPageService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'status-page');

        if (config('status-page.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/status-page.php' => config_path('status-page.php'),
            ], 'status-page:config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/status-page'),
            ], 'status-page:views');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'status-page:migrations');
        }
    }
}
