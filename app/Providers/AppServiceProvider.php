<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\View;
use App\Http\ViewComposers\CurrentPeriodComposer;
use App\Http\ViewComposers\CompanyNameComposer;


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
        View::composer('*', CurrentPeriodComposer::class);
        View::composer('*', CompanyNameComposer::class);
    }
}
