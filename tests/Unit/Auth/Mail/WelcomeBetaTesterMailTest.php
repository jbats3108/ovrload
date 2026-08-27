<?php

namespace Tests\Unit\Auth\Mail;

use App\Auth\Mail\WelcomeBetaTesterMail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeBetaTesterMailTest extends TestCase
{
    #[Test]
    public function mail_includes_welcome_links_and_brand(): void
    {
        $mail = new WelcomeBetaTesterMail(
            userName: 'Buddy',
            tutorialUrl: 'https://ovrload.test/tutorial',
            faqsUrl: 'https://ovrload.test/beta-tester-faqs',
            replyToEmail: 'jamie@example.com',
            replyToName: 'Jamie',
        );

        $mail->assertHasSubject('Welcome to OVRLOAD');
        $mail->assertSeeInHtml('Welcome, Buddy');
        $mail->assertSeeInHtml('https://ovrload.test/tutorial');
        $mail->assertSeeInHtml('https://ovrload.test/beta-tester-faqs');
        $mail->assertSeeInHtml('OVR');
        $mail->assertSeeInHtml('LOAD');
        $mail->assertSeeInHtml('Reply to this email');
        $mail->assertSeeInText('Welcome, Buddy');
        $mail->assertSeeInText('https://ovrload.test/tutorial');
        $mail->assertSeeInText('https://ovrload.test/beta-tester-faqs');
    }
}
