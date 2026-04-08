<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerificationPin extends Notification implements ShouldQueue
{
    use Queueable;

    private const EXPIRATION_MINUTES = 10;

    /**
     * Create a new notification instance.
     */
    public function __construct(private string $pin)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Verify Your Account')
            ->view('emails.auth.verification-pin', [
                'recipientName' => $notifiable->name ?? 'User',
                'pin' => $this->pin,
                'expiresInMinutes' => self::EXPIRATION_MINUTES,
                'appName' => config('app.name'),
                'panelUrl' => url('/'),
            ])
            ->text('emails.auth.verification-pin-plain', [
                'recipientName' => $notifiable->name ?? 'User',
                'pin' => $this->pin,
                'expiresInMinutes' => self::EXPIRATION_MINUTES,
                'appName' => config('app.name'),
                'panelUrl' => url('/'),
            ]);
    }
}
