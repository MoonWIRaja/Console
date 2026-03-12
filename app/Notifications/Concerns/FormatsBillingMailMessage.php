<?php

namespace Pterodactyl\Notifications\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;

trait FormatsBillingMailMessage
{
    protected function makeBillingMail(object $notifiable, string $subject, string $headline): MailMessage
    {
        $name = $notifiable->username ?? $notifiable->name ?? 'there';
        $appName = (string) config('app.name', 'BurHan Console');

        return (new MailMessage())
            ->subject($subject)
            ->greeting(sprintf('Hello %s,', $name))
            ->line($headline)
            ->action('Open Billing', url('/billing'))
            ->salutation(sprintf("Regards,\n%s Billing", $appName));
    }

    protected function formatBillingAmount(string $currency, float $amount): string
    {
        return sprintf('%s %s', $currency, number_format($amount, 2));
    }

    protected function formatBillingDate(?CarbonInterface $date, string $fallback = 'Not available'): string
    {
        return $date
            ? $date->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A')
            : $fallback;
    }
}
