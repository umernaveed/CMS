<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\Models\PackageLog;
use App\Observers\PackageLogObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();
        Paginator::defaultView('pagination::bootstrap-5');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-5');

        PackageLog::observe(PackageLogObserver::class);

        $settings = collect();

        try {
            if (Schema::hasTable('system_settings')) {
                $settings = Cache::remember('platform_settings', 300, fn () => SystemSetting::pluck('value', 'key'));
            }
        } catch (\Throwable $exception) {
            $settings = collect();
        }

        $assetUrl = function (?string $path) {
            if (! $path) {
                return null;
            }

            return Str::startsWith($path, ['http://', 'https://'])
                ? $path
                : asset(ltrim($path, '/'));
        };

        $platformName = $settings['app_name'] ?? config('app.name', 'Courier SaaS');

        View::share('platformSettings', $settings);
        View::share('platformName', $platformName);
        $defaultLogo = 'uploads/platform/app-logo-20260505221831.png';
        $platformLogoPath = $settings['app_logo']
            ?? $settings['company_logo']
            ?? (file_exists(public_path($defaultLogo)) ? $defaultLogo : null);

        View::share('platformLogo', $assetUrl($platformLogoPath));
        View::share('platformFavicon', $assetUrl($settings['favicon'] ?? null));
    }
}
