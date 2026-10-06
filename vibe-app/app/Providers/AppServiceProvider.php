<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
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
        // Child views render before the layout's @php runs, so the shared helpers are provided here.
        View::composer(['budget-allocation', 'budget-item-form', 'budget-item-show', 'budget-report', 'chart-of-accounts', 'aip-index', 'aip-show', 'aip-kra-form', 'allotment-registry'], function ($view) {
            $view->with([
                'peso' => fn ($value) => '₱' . number_format((float) $value, 2),
                'inputClass' => 'mt-1 w-full rounded border border-outline-variant/50 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary',
            ]);
        });
    }
}
