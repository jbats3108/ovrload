<?php

namespace App\Settings\Http\Controllers;

use App\Notifications\Data\UpsertPushSubscriptionData;
use App\Notifications\Models\PushSubscription;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorePushSubscriptionController extends Controller
{
    public function __invoke(UpsertPushSubscriptionData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! PushSubscription::isAllowedEndpoint($data->endpoint)) {
            return back()->withErrors([
                'endpoint' => 'This push service is not supported.',
            ]);
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::endpointHash($data->endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $data->endpoint,
                'public_key' => $data->publicKey,
                'auth_token' => $data->authToken,
                'content_encoding' => $data->contentEncoding,
            ],
        );

        return back()->with('success', 'Rest notifications enabled.');
    }
}
