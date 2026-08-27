<?php

namespace App\Auth\Listeners;

use App\Auth\Mail\WelcomeBetaTesterMail;
use App\Users\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class SendWelcomeBetaTesterMail
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $replyToAddress = config('mail.reply_to.address');
        $replyToName = config('mail.reply_to.name');
        $adminMailbox = config('ovrload.mailboxes.admin');

        Mail::to($user)->send(new WelcomeBetaTesterMail(
            userName: $user->name,
            tutorialUrl: route('tutorial', absolute: true),
            faqsUrl: route('beta-tester-faqs', absolute: true),
            replyToEmail: is_string($replyToAddress) && $replyToAddress !== ''
                ? $replyToAddress
                : (is_string($adminMailbox) && $adminMailbox !== ''
                    ? $adminMailbox
                    : (string) config('mail.from.address')),
            replyToName: is_string($replyToName) && $replyToName !== ''
                ? $replyToName
                : 'Jamie',
        ));
    }
}
