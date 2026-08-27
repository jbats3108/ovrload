<?php

namespace App\Settings\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $config = config('services.web_push', []);
        $publicKey = is_array($config) ? ($config['public_key'] ?? null) : null;
        $configured = is_array($config)
            && is_string($config['subject'] ?? null)
            && $config['subject'] !== ''
            && is_string($publicKey)
            && $publicKey !== ''
            && is_string($config['private_key'] ?? null)
            && $config['private_key'] !== '';

        return Inertia::render('settings/Notifications', [
            'vapid_public_key' => $configured ? $publicKey : null,
            'has_subscription' => $user->pushSubscriptions()->exists(),
        ]);
    }
}
