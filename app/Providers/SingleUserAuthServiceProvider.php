<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\AuthManager;
use App\Auth\SingleUserProvider;

class SingleUserAuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(AuthManager::class)->provider('single-user', function ($app) {
            return new SingleUserProvider();
        });
    }
}
