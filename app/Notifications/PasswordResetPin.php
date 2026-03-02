<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetPin extends Notification implements ShouldQueue
{
    use Queueable;

    private const EXPIRATION_MINUTES = 10;

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
            ->subject('Reset Your Password')
            ->view('emails.auth.password-reset-pin', [
                'recipientName' => $notifiable->name ?? 'User',
                'pin' => $this->pin,
                'expiresInMinutes' => self::EXPIRATION_MINUTES,
                'appName' => config('app.name'),
                'panelUrl' => url('/auth/password'),
            ])
            ->text('emails.auth.password-reset-pin-plain', [
                'recipientName' => $notifiable->name ?? 'User',
                'pin' => $this->pin,
                'expiresInMinutes' => self::EXPIRATION_MINUTES,
                'appName' => config('app.name'),
                'panelUrl' => url('/auth/password'),
            ]);
    }
}
