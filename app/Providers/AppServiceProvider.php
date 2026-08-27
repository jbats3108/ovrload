<?php

namespace App\Providers;

use App\Notifications\Contracts\PushNotificationSender;
use App\Notifications\Services\WebPushNotificationSender;
use App\Routines\Models\Routine;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
        $this->app->bind(PushNotificationSender::class, WebPushNotificationSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

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
