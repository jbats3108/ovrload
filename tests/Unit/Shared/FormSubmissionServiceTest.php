<?php

namespace Tests\Unit\Shared;

use App\Shared\Enums\FormSubmissionType;
use App\Shared\Services\FormSubmissionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormSubmissionServiceTest extends TestCase
{
    private FormSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FormSubmissionService;
    }

    #[Test]
    public function recipient_address_uses_the_type_mailbox_when_configured(): void
    {
        config([
            'ovrload.mailboxes.invite' => 'invite@example.com',
            'ovrload.mailboxes.feedback' => 'feedback@example.com',
        ]);

        $this->assertSame(
            'invite@example.com',
            $this->service->recipientAddress(FormSubmissionType::InviteInterest),
        );
        $this->assertSame(
            'feedback@example.com',
            $this->service->recipientAddress(FormSubmissionType::Feedback),
        );
    }

    #[Test]
    public function recipient_address_falls_back_to_admin_mailbox(): void
    {
        config([
            'ovrload.mailboxes.invite' => '',
            'ovrload.mailboxes.admin' => 'admin@example.com',
            'mail.from.address' => 'from@example.com',
        ]);

        $this->assertSame(
            'admin@example.com',
            $this->service->recipientAddress(FormSubmissionType::InviteInterest),
        );
    }

    #[Test]
    public function recipient_address_falls_back_to_mail_from_when_admin_is_empty(): void
    {
        config([
            'ovrload.mailboxes.feedback' => null,
            'ovrload.mailboxes.admin' => '',
            'mail.from.address' => 'from@example.com',
        ]);

        $this->assertSame(
            'from@example.com',
            $this->service->recipientAddress(FormSubmissionType::Feedback),
        );
    }
}
