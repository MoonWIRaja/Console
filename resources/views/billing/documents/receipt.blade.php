<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->payment_number }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2933;
            font-size: 12px;
            margin: 0;
            background: #f4f4f1;
        }
        h1, h2, h3, p { margin: 0; }
        .page { padding: 20px; }
        .shell {
            background: #ffffff;
            border: 1px solid #d7d2c8;
        }
        .hero {
            background: #4b5563;
            border-top: 8px solid #f0b90b;
            color: #ffffff;
            padding: 18px 22px 14px;
        }
        .hero-table, .grid { width: 100%; border-collapse: collapse; }
        .hero-left, .hero-right { vertical-align: top; }
        .hero-right { text-align: right; }
        .logo-wrap {
            display: inline-block;
            width: 44px;
            height: 44px;
            padding: 6px;
            border-radius: 10px;
            background: #ffffff;
            text-align: center;
        }
        .logo-wrap img {
            width: 100%;
            height: 100%;
        }
        .eyebrow {
            font-size: 9px;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            opacity: 0.88;
            margin-bottom: 5px;
        }
        .hero-title {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 0.04em;
        }
        .hero-subtitle {
            margin-top: 5px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.9);
        }
        .meta-strip {
            background: #f4cf62;
            color: #2a313c;
            padding: 9px 22px;
            font-size: 10px;
        }
        .meta-strip td {
            width: 25%;
            vertical-align: top;
        }
        .meta-label {
            display: block;
            margin-bottom: 2px;
            font-size: 9px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #5c4b17;
        }
        .content {
            padding: 18px 22px 22px;
        }
        .section { margin-bottom: 16px; }
        .status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #f8d36a;
            color: #1f2937;
            font-weight: bold;
            letter-spacing: 0.18em;
            font-size: 10px;
        }
        .cards td {
            width: 50%;
            vertical-align: top;
            padding: 0 6px 0 0;
        }
        .cards td:last-child { padding-right: 0; padding-left: 6px; }
        .card {
            background: #faf8f3;
            border: 1px solid #e6dfd0;
            padding: 12px 14px;
        }
        .card-title {
            margin-bottom: 8px;
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #4b5563;
        }
        .card p {
            margin-bottom: 5px;
            line-height: 1.3;
        }
        .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 1px solid #d8d1c4;
        }
        table.items th,
        table.items td {
            border-bottom: 1px solid #e7e1d6;
            padding: 8px 7px;
            text-align: left;
        }
        table.items th {
            background: #ece7dc;
            color: #374151;
            font-size: 10px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        table.items tbody tr:nth-child(even) td {
            background: #fcfbf8;
        }
        .amount {
            text-align: right;
            white-space: nowrap;
        }
        .paid-row td {
            background: #fff7db !important;
            font-weight: bold;
            color: #5f4a08;
        }
        .summary-note {
            margin-top: 10px;
            padding: 10px 12px;
            background: #eef2f7;
            border: 1px solid #d5dce6;
            color: #334155;
            line-height: 1.35;
        }
        .footer {
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #e5ddd0;
            color: #6b7280;
            font-size: 9px;
            text-align: center;
            line-height: 1.35;
        }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="hero">
                <table class="hero-table">
                    <tr>
                        <td class="hero-left">
                            <div class="eyebrow">Billing Document</div>
                            <div class="hero-title">Receipt</div>
                            <p class="hero-subtitle">{{ $branding['name'] ?? config('app.name', 'Pterodactyl') }}</p>
                        </td>
                        <td class="hero-right">
                            @if(!empty($branding['logo_data_uri']))
                                <div class="logo-wrap">
                                    <img src="{{ $branding['logo_data_uri'] }}" alt="Logo">
                                </div>
                            @endif
                            <div style="margin-top: 14px;">
                                <span class="status">RECEIPT</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta-strip">
                <table class="grid">
                    <tr>
                        <td>
                            <span class="meta-label">Receipt No</span>
                            {{ $payment->payment_number }}
                        </td>
                        <td>
                            <span class="meta-label">Invoice No</span>
                            {{ $invoice->invoice_number }}
                        </td>
                        <td>
                            <span class="meta-label">Paid At</span>
                            {{ $payment->paid_at ? $payment->paid_at->copy()->setTimezone(config('app.timezone'))->format('M j, Y g:i A') : 'N/A' }}
                        </td>
                        <td>
                            <span class="meta-label">Amount</span>
                            {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            <div class="content">
                <div class="section">
                    <table class="grid cards">
                        <tr>
                            <td>
                                <div class="card">
                                    <div class="card-title">Receipt Details</div>
                                    <p><span class="label">Receipt</span><br>{{ $payment->payment_number }}</p>
                                    <p><span class="label">Invoice</span><br>{{ $invoice->invoice_number }}</p>
                                    <p><span class="label">Paid At</span><br>{{ $payment->paid_at ? $payment->paid_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A') : 'N/A' }}</p>
                                    <p><span class="label">Method</span><br>{{ strtoupper(str_replace('_', ' ', $payment->provider_payment_method ?? $payment->provider ?? 'manual')) }}</p>
                                    <p><span class="label">Status</span><br>{{ strtoupper(str_replace('_', ' ', $payment->status)) }}</p>
                                </div>
                            </td>
                            <td>
                                <div class="card">
                                    <div class="card-title">Bill To</div>
                                    <p>{{ $snapshot['legal_name'] ?? 'N/A' }}</p>
                                    @if(!empty($snapshot['company_name']))
                                        <p>{{ $snapshot['company_name'] }}</p>
                                    @endif
                                    <p>{{ $snapshot['email'] ?? 'N/A' }}</p>
                                    <p>{{ $snapshot['phone'] ?? 'N/A' }}</p>
                                    <p>{{ $snapshot['address_line_1'] ?? 'N/A' }}</p>
                                    @if(!empty($snapshot['address_line_2']))
                                        <p>{{ $snapshot['address_line_2'] }}</p>
                                    @endif
                                    <p>
                                        {{ trim(implode(' ', array_filter([
                                            $snapshot['postcode'] ?? null,
                                            $snapshot['city'] ?? null,
                                            $snapshot['state'] ?? null,
                                        ]))) ?: 'N/A' }}
                                    </p>
                                    <p>{{ $snapshot['country_code'] ?? 'N/A' }}</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="section">
                    <table class="items">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th class="amount">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ strtoupper(str_replace('_', ' ', $item->type)) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="amount">{{ $invoice->currency }} {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="paid-row">
                                <td colspan="3">Total Paid</td>
                                <td class="amount">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="summary-note">
                    This receipt confirms payment for invoice <strong>{{ $invoice->invoice_number }}</strong>. Keep this document for your accounting and billing reference.
                </div>

                <div class="footer">
                    {{ $branding['name'] ?? config('app.name', 'Pterodactyl') }}
                    @if(!empty($branding['website']))
                        &bull; {{ preg_replace('#^https?://#', '', $branding['website']) }}
                    @endif
                    <br>
                    This receipt was generated electronically and is valid without a physical signature.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
