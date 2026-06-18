<?php

namespace App\Providers;

use App\Http\ViewComposers\CompanyNameComposer;
use App\Http\ViewComposers\CurrentPeriodComposer;
use App\Listeners\LogSaccoFailedLogin;
use App\Listeners\LogSaccoLoginLockout;
use App\Listeners\LogSaccoLogout;
use App\Listeners\LogSaccoSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind classes into the service container here if needed.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Global view composers.
         */
        View::composer('*', CurrentPeriodComposer::class);
        View::composer('*', CompanyNameComposer::class);

        /**
         * Global shared view variables.
         */
        View::share('erpContact', config('app.erp_contact'));
        View::share('saccoSupport', config('app.sacco_support'));

        /**
         * Bootstrap pagination styling.
         */
        Paginator::useBootstrap();

        /**
         * SACCO route/security audit event listeners.
         *
         * Important:
         * If these listeners are already registered in EventServiceProvider,
         * do not register them here again, otherwise logs may be duplicated.
         */
        Event::listen(Login::class, LogSaccoSuccessfulLogin::class);
        Event::listen(Failed::class, LogSaccoFailedLogin::class);
        Event::listen(Logout::class, LogSaccoLogout::class);
        Event::listen(Lockout::class, LogSaccoLoginLockout::class);
    }
}