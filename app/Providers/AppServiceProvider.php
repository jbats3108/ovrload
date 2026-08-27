<?php

namespace App\Providers;

use App\Auth\Listeners\SendWelcomeBetaTesterMail;
use App\Routines\Models\Routine;
use App\Users\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::listen(Registered::class, SendWelcomeBetaTesterMail::class);

        // Slugs are unique per user, so binding must scope to the authenticated owner
        // (admins are unscoped). Authorization still runs via ->can() policies after bind.
        Route::bind('routine', function (string $value): Routine {
            $query = Routine::query()->where('slug', $value);

            $user = Auth::user();
            if ($user instanceof User && ! $user->isAdmin()) {
                $query->where('user_id', $user->id);
            }

            return $query->firstOrFail();
        });
    }
}
