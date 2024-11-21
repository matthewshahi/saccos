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
        // You can bind classes into the service container here if needed
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Attach the CurrentPeriodComposer to all views
        View::composer('*', CurrentPeriodComposer::class);

        // Attach the CompanyNameComposer to all views
        View::composer('*', CompanyNameComposer::class);

        // Share ERP contact globally with all views
        View::share('erpContact', config('app.erp_contact'));

        // Share Sacco support contact globally with all views
        View::share('saccoSupport', config('app.sacco_support'));
    }
}