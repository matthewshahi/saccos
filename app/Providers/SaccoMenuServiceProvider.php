<?php

namespace App\Providers;

use App\Services\SaccoMenuService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class SaccoMenuServiceProvider extends ServiceProvider
{
    /**
     * Register menu-related services.
     */
    public function register(): void
    {
        $this->app->singleton(SaccoMenuService::class, function ($app) {
            return new SaccoMenuService();
        });
    }

    /**
     * Inject the database menu whenever the official sidebar is rendered.
     */
    public function boot(SaccoMenuService $menuService): void
    {
        View::composer('partials.menu', function ($view) use ($menuService) {
            $user = auth()->user();

            if (!$user) {
                $view->with('saccoMenu', collect());

                return;
            }

            $view->with(
                'saccoMenu',
                $menuService->getVisibleMenuForUser($user)
            );
        });
    }
}