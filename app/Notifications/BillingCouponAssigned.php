<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingCoupon;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingCouponAssigned extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingCoupon $coupon)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $value = $this->coupon->discount_type === BillingCoupon::TYPE_PERCENTAGE
            ? rtrim(rtrim(number_format((float) $this->coupon->discount_value, 2), '0'), '.') . '% off'
            : $this->formatBillingAmount('RM', (float) $this->coupon->discount_value) . ' off';

        $mail = $this->makeBillingMail(
            $notifiable,
            'You\'ve Received a Discount Coupon',
            'A discount coupon has been created just for your account. It cannot be used by anyone else.'
        )
            ->line('Your code: ' . $this->coupon->code)
            ->line('Discount: ' . $value);

        if ($this->coupon->description) {
            $mail->line($this->coupon->description);
        }

        $mail->line($this->coupon->expires_at
            ? 'Valid until: ' . $this->formatBillingDate($this->coupon->expires_at)
            : 'This coupon does not expire.');

        return $mail->line('Enter this code at checkout on a new order or renewal to apply the discount.');
    }
}
