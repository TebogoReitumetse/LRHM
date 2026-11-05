<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\WorkingDayCalculator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(WorkingDayCalculator::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers()->symbols());

        // Leave management
        Gate::define('review-leave', fn (User $u) => $u->isManager());
        Gate::define('decide-leave', fn (User $u) => $u->isDirector());
        Gate::define('view-leave-register', fn (User $u) => $u->isManagement());
        Gate::define('manage-absences', fn (User $u) => $u->isManagement());

        // Contract management: Manager only (Director access excluded in Phase 2).
        Gate::define('manage-contracts', fn (User $u) => $u->isManager());

        // Administration
        Gate::define('manage-users', fn (User $u) => $u->hasRole(Role::Admin, Role::Manager));
        Gate::define('manage-settings', fn (User $u) => $u->isAdmin());
        Gate::define('view-audit', fn (User $u) => $u->hasRole(Role::Admin, Role::Manager, Role::Director));
    }
}
