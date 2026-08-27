<?php

namespace App\Settings\Http\Controllers;

use App\Notifications\Data\RevokePushSubscriptionData;
use App\Notifications\Models\PushSubscription;
use App\Shared\Http\Controllers\Controller;
use App\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RevokePushSubscriptionController extends Controller
{
    public function __invoke(RevokePushSubscriptionData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::endpointHash($data->endpoint))
            ->delete();

        return back()->with('success', 'Rest notifications disabled.');
    }
}
