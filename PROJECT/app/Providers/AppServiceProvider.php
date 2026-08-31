<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Backs every $this->authorize('admin') call in the codebase
        // (AdminController, SecurityLogController). Was previously
        // undefined, so Gate::authorize('admin') denied unconditionally
        // for every user, including real admins.
        Gate::define('admin', fn (User $user) => $user->hasAnyRole(['super_admin', 'admin']));
    }
}
