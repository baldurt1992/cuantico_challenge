<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (config('app.compiled_assets')) {
            Vite::useHotFile(storage_path('framework/compiled-assets.hot'));
        }
    }
}
