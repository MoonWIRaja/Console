<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;

class BillingManualAdminOrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private BillingOrder $order, private BillingInvoice $invoice)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(sprintf('Manual Billing Approval Needed: %s', $this->invoice->invoice_number))
            ->greeting(sprintf('Hello %s,', $notifiable->username ?? $notifiable->name ?? 'admin'))
            ->line('A client created a billing order that now needs manual payment approval.')
            ->line('Invoice: ' . $this->invoice->invoice_number)
            ->line('Client: ' . ($this->order->user->email ?? 'Unknown'))
            ->line('Order Type: ' . strtoupper(str_replace('_', ' ', $this->order->order_type ?? 'manual')))
            ->line('Amount Due: ' . $this->invoice->currency . ' ' . number_format((float) $this->invoice->grand_total, 2))
            ->action('Open Billing Order', route('admin.billing.orders.view', $this->order->id))
            ->salutation(sprintf("Regards,\n%s Billing", config('app.name', 'BurHan Console')));
    }
}
