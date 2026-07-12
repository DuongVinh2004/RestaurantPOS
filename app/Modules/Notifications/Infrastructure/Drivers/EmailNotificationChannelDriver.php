<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Drivers;

use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Notifications\Infrastructure\Contracts\NotificationChannelDriver;
use App\Modules\Notifications\Infrastructure\NotificationDeliveryResult;
use Illuminate\Support\Facades\Mail;

class EmailNotificationChannelDriver implements NotificationChannelDriver
{
    public function providerKey(): string
    {
        return (string) config('notifications.channels.email.provider_key', 'mail');
    }

    public function send(NotificationOutbox $message, array $dispatchPayload): NotificationDeliveryResult
    {
        $recipient = (string) ($dispatchPayload['recipient'] ?? $message->recipient);
        $subject = (string) ($dispatchPayload['subject'] ?? 'Notification');
        $body = (string) ($dispatchPayload['text_body'] ?? '');

        $htmlBody = (string) ($dispatchPayload['html_body'] ?? '');

        if ($htmlBody !== '') {
            Mail::mailer((string) config('notifications.outbox.mailer', config('mail.default')))
                ->html($htmlBody, function ($mail) use ($recipient, $subject): void {
                    $mail->to($recipient)->subject($subject);
                });
        } else {
            Mail::mailer((string) config('notifications.outbox.mailer', config('mail.default')))
                ->raw($body, function ($mail) use ($recipient, $subject): void {
                    $mail->to($recipient)->subject($subject);
                });
        }

        return new NotificationDeliveryResult(
            providerKey: $this->providerKey(),
            providerStatus: 'accepted',
            providerMessageId: null,
            responsePayload: [
                'channel' => 'Email',
                'mode' => 'real',
                'mailer' => (string) config('notifications.outbox.mailer', config('mail.default')),
            ],
        );
    }
}
