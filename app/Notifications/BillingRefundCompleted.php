<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingRefund;
use Pterodactyl\Models\BillingSubscription;
use Pterodactyl\Notifications\Concerns\FormatsBillingMailMessage;

class BillingRefundCompleted extends Notification implements ShouldQueue
{
    use Queueable;
    use FormatsBillingMailMessage;

    public function __construct(private BillingRefund $refund)
    {
        $this->afterCommit();
    }

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->refund->payment->invoice;
        $subscription = $invoice->subscription ?: $this->resolveSubscriptionForInvoice($invoice);
        $providerReference = data_get($this->refund->raw_response, 'refundID')
            ?? $this->refund->provider_refund_id
            ?? 'Pending assignment';

        $message = $this->makeBillingMail(
            $notifiable,
            $this->subjectForInvoice($invoice),
            $this->headlineForInvoice($invoice)
        )
            ->line('Refund: ' . $this->refund->refund_number)
            ->line('Amount: ' . $this->formatBillingAmount($this->refund->payment->currency, (float) $this->refund->amount))
            ->line('Provider reference: ' . $providerReference)
            ->line('Submitted at: ' . $this->formatBillingDate($this->refund->completed_at, 'Just now'));

        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && $subscription && in_array($invoice->type, [
            BillingInvoice::TYPE_NEW_SERVER,
            BillingInvoice::TYPE_RENEWAL,
        ], true)) {
            $message
                ->line('Server: ' . $subscription->server_name)
                ->line('Automatic suspension: ' . $this->formatBillingDate($subscription->grace_suspend_at, 'Not scheduled'))
                ->line('Permanent deletion: ' . $this->formatBillingDate($subscription->grace_delete_at, 'Not scheduled'))
                ->line('Please download any important files, backups, or configuration data before the suspension time above.');
        }

        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && $subscription && $invoice->type === BillingInvoice::TYPE_UPGRADE) {
            $message
                ->line('Server: ' . $subscription->server_name)
                ->line(sprintf(
                    'The upgrade has been rolled back to the previous plan: %d vCore, %d GB RAM, %d GB Disk.',
                    $subscription->cpu_cores,
                    $subscription->memory_gb,
                    $subscription->disk_gb
                ));
        }

        return $message
            ->line('Most banks and payment providers will return the funds within 2-7 business days.')
            ->line('If the refund still does not appear after that window, please contact support and include the refund number above.');
    }

    private function subjectForInvoice(BillingInvoice $invoice): string
    {
        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && in_array($invoice->type, [
            BillingInvoice::TYPE_NEW_SERVER,
            BillingInvoice::TYPE_RENEWAL,
        ], true)) {
            return sprintf('Refund Processing & Server Closure: %s', $this->refund->refund_number);
        }

        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && $invoice->type === BillingInvoice::TYPE_UPGRADE) {
            return sprintf('Upgrade Refund Processing: %s', $this->refund->refund_number);
        }

        return sprintf('Refund Processing: %s', $this->refund->refund_number);
    }

    private function headlineForInvoice(BillingInvoice $invoice): string
    {
        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && in_array($invoice->type, [
            BillingInvoice::TYPE_NEW_SERVER,
            BillingInvoice::TYPE_RENEWAL,
        ], true)) {
            return 'Your refund request has been accepted. The related server is now scheduled for suspension and removal.';
        }

        if ($invoice->status === BillingInvoice::STATUS_REFUNDED && $invoice->type === BillingInvoice::TYPE_UPGRADE) {
            return 'Your upgrade refund request has been accepted and the server has been returned to the previous paid plan.';
        }

        return 'Your refund request has been accepted and is now being processed by the payment provider.';
    }

    private function resolveSubscriptionForInvoice(BillingInvoice $invoice): ?BillingSubscription
    {
        if ($invoice->subscription) {
            return $invoice->subscription;
        }

        if ($invoice->order?->id) {
            $subscription = BillingSubscription::query()
                ->where('billing_order_id', $invoice->order->id)
                ->first();
            if ($subscription) {
                return $subscription;
            }
        }

        if ($invoice->order?->server_id) {
            return BillingSubscription::query()
                ->where('server_id', $invoice->order->server_id)
                ->latest('id')
                ->first();
        }

        return null;
    }
}
