<?php

namespace Tests\Feature\Auth;

use App\Auth\Mail\WelcomeBetaTesterMail;
use App\Auth\Services\RegistrationInviteService;
use App\Users\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeBetaTesterMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['registration.invite' => null]);
    }

    #[Test]
    public function registration_queues_welcome_email(): void
    {
        Mail::fake();

        config([
            'mail.reply_to.address' => 'jamie@example.com',
            'mail.reply_to.name' => 'Jamie',
        ]);

        $admin = User::factory()->withRole('admin')->create();
        $invite = app(RegistrationInviteService::class)->create($admin, 'user', 'buddy', 7);

        $this->post('/register', [
            'name' => 'Buddy',
            'email' => 'buddy@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ])->assertRedirect(route('dashboard', absolute: false));

        Mail::assertQueued(WelcomeBetaTesterMail::class, function (WelcomeBetaTesterMail $mail): bool {
            return $mail->hasTo('buddy@example.com')
                && $mail->userName === 'Buddy'
                && $mail->tutorialUrl === route('tutorial', absolute: true)
                && $mail->faqsUrl === route('beta-tester-faqs', absolute: true)
                && $mail->replyToEmail === 'jamie@example.com'
                && $mail->replyToName === 'Jamie';
        });
    }

    #[Test]
    public function welcome_email_falls_back_to_admin_mailbox_for_reply_to(): void
    {
        Mail::fake();

        config([
            'mail.reply_to.address' => null,
            'mail.reply_to.name' => null,
            'ovrload.mailboxes.admin' => 'admin@ovr-load.co.uk',
        ]);

        $admin = User::factory()->withRole('admin')->create();
        $invite = app(RegistrationInviteService::class)->create($admin, 'user');

        $this->post('/register', [
            'name' => 'Fallback',
            'email' => 'fallback@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ])->assertRedirect(route('dashboard', absolute: false));

        Mail::assertQueued(WelcomeBetaTesterMail::class, function (WelcomeBetaTesterMail $mail): bool {
            return $mail->replyToEmail === 'admin@ovr-load.co.uk'
                && $mail->replyToName === 'Jamie';
        });
    }
}
