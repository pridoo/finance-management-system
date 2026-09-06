<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->resolveAssetBaseFromRequest();
    }

    /**
     * Points asset URLs at whatever host and folder the app is actually being
     * served from.
     *
     * This project is opened two ways: through XAMPP at
     * localhost/ash-keisha_capstone/san-agustin-websystem/public, and through
     * `php artisan serve` at 127.0.0.1:8000. A hard-coded ASSET_URL in .env is
     * correct for exactly one of them, and under the other the Vite bundle
     * 404s. When that happened nothing announced it — the page rendered fine,
     * but window.Swal was never defined, so every confirm, success and details
     * dialog in the portal silently degraded to a native alert box.
     *
     * Deriving it from the live request means both entry points work with no
     * .env edit when switching between them.
     */
    private function resolveAssetBaseFromRequest(): void
    {
        // Console commands have no request to read; asset() is not used there.
        if ($this->app->runningInConsole() || ! $this->app->bound('request')) {
            return;
        }

        // An explicit ASSET_URL still wins, for deployments that need one.
        if (config('app.asset_url')) {
            return;
        }

        $request = $this->app->make('request');
        $base    = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');

        if ($base !== '') {
            config(['app.asset_url' => $base]);
        }
    }
}
