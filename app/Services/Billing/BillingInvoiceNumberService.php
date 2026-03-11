<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\BillingInvoice;
use Pterodactyl\Models\BillingPayment;
use Pterodactyl\Models\BillingRefund;

class BillingInvoiceNumberService
{
    public function nextInvoiceNumber(): string
    {
        return $this->nextNumber(BillingInvoice::class, 'invoice_number', 'INV');
    }

    public function nextPaymentNumber(): string
    {
        return $this->nextNumber(BillingPayment::class, 'payment_number', 'PAY');
    }

    public function nextRefundNumber(): string
    {
        return $this->nextNumber(BillingRefund::class, 'refund_number', 'RFD');
    }

    private function nextNumber(string $modelClass, string $column, string $prefix): string
    {
        $ym = now()->format('Ym');
        $base = sprintf('%s-%s-', $prefix, $ym);

        $last = $modelClass::query()
            ->where($column, 'like', $base . '%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $sequence = 1;
        if ($last && preg_match('/(\d{6})$/', $last, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return sprintf('%s%06d', $base, $sequence);
    }
}
