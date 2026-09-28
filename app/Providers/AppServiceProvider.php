<?php

namespace App\Providers;

use App\Contracts\LeadNotifier;
use App\Services\Notifications\MockLeadNotifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap this binding for a real WhatsApp/SMS implementation in production.
        $this->app->bind(LeadNotifier::class, MockLeadNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
