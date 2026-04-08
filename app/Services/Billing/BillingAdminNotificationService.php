<?php

namespace Pterodactyl\Services\Billing;

use Throwable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BillingOrder;
use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\UserOAuthAccount;
use Pterodactyl\Services\Discord\DiscordDirectMessageService;
use Pterodactyl\Notifications\BillingManualAdminOrderCreated;

class BillingAdminNotificationService
{
    public function __construct(private DiscordDirectMessageService $discordDirectMessageService)
    {
    }

    public function notifyOrderAwaitingApproval(BillingOrder $order, BillingInvoice $invoice): void
    {
        $order->loadMissing('user', 'invoice');

        User::query()
            ->where('root_admin', true)
            ->get()
            ->each(function (User $admin) use ($order, $invoice) {
                $admin->notify(new BillingManualAdminOrderCreated($order, $invoice));
                $this->notifyDiscordAdmin($admin, $order, $invoice);
            });
    }

    private function notifyDiscordAdmin(User $admin, BillingOrder $order, BillingInvoice $invoice): void
    {
        if ((bool) config('tickets.enabled', false)) {
            return;
        }

        if (!$this->discordDirectMessageService->isConfigured()) {
            return;
        }

        $discordAccount = UserOAuthAccount::query()
            ->where('user_id', $admin->id)
            ->where('provider', 'discord')
            ->first();

        if (!$discordAccount || blank($discordAccount->provider_id)) {
            return;
        }

        try {
            $this->discordDirectMessageService->sendToUser((string) $discordAccount->provider_id, implode("\n", array_filter([
                'Manual billing approval needed.',
                'Invoice: ' . $invoice->invoice_number,
                'Client: ' . ($order->user->email ?? 'Unknown'),
                'Order Type: ' . strtoupper(str_replace('_', ' ', $order->order_type ?? 'manual')),
                'Amount: ' . $invoice->currency . ' ' . number_format((float) $invoice->grand_total, 2),
                'Review: ' . route('admin.billing.orders.view', $order->id),
            ])));
        } catch (Throwable $exception) {
            report($exception);
            Log::warning('Failed to send Discord DM for manual billing approval.', [
                'admin_user_id' => $admin->id,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
