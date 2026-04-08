<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
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
            background: #374151;
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
            background: #2a313c;
            color: #e5e7eb;
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
            color: #f8d36a;
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
            background: #f0b90b;
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
        .totals {
            width: 290px;
            margin-left: auto;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .totals td {
            padding: 6px 0;
        }
        .totals .value {
            text-align: right;
            white-space: nowrap;
        }
        .totals .grand td {
            border-top: 2px solid #2a313c;
            font-weight: bold;
            font-size: 13px;
            color: #111827;
        }
        .summary-note {
            margin-top: 10px;
            padding: 10px 12px;
            background: #fff7db;
            border: 1px solid #f0d272;
            color: #5f4a08;
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
                            <div class="hero-title">Invoice</div>
                            <p class="hero-subtitle">{{ $branding['name'] ?? config('app.name', 'Pterodactyl') }}</p>
                        </td>
                        <td class="hero-right">
                            @if(!empty($branding['logo_data_uri']))
                                <div class="logo-wrap">
                                    <img src="{{ $branding['logo_data_uri'] }}" alt="Logo">
                                </div>
                            @endif
                            <div style="margin-top: 14px;">
                                <span class="status">{{ $statusLabel }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="meta-strip">
                <table class="grid">
                    <tr>
                        <td>
                            <span class="meta-label">Invoice No</span>
                            {{ $invoice->invoice_number }}
                        </td>
                        <td>
                            <span class="meta-label">Document Type</span>
                            {{ strtoupper(str_replace('_', ' ', $invoice->type)) }}
                        </td>
                        <td>
                            <span class="meta-label">Issue Date</span>
                            {{ $invoice->issued_at ? $invoice->issued_at->copy()->setTimezone(config('app.timezone'))->format('M j, Y g:i A') : 'N/A' }}
                        </td>
                        <td>
                            <span class="meta-label">Due Date</span>
                            {{ $invoice->due_at ? $invoice->due_at->copy()->setTimezone(config('app.timezone'))->format('M j, Y g:i A') : 'N/A' }}
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
                                    <div class="card-title">Invoice Details</div>
                                    <p><span class="label">Invoice</span><br>{{ $invoice->invoice_number }}</p>
                                    <p><span class="label">Type</span><br>{{ strtoupper(str_replace('_', ' ', $invoice->type)) }}</p>
                                    <p><span class="label">Issued</span><br>{{ $invoice->issued_at ? $invoice->issued_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A') : 'N/A' }}</p>
                                    <p><span class="label">Due</span><br>{{ $invoice->due_at ? $invoice->due_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A') : 'N/A' }}</p>
                                    @if($invoice->paid_at)
                                        <p><span class="label">Paid</span><br>{{ $invoice->paid_at->copy()->setTimezone(config('app.timezone'))->format('F j, Y g:i A') }}</p>
                                    @endif
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
                                    @if(!empty($snapshot['tax_id']))
                                        <p>Tax ID: {{ $snapshot['tax_id'] }}</p>
                                    @endif
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
                        </tbody>
                    </table>

                    <table class="totals">
                        <tr>
                            <td>Subtotal</td>
                            <td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Tax</td>
                            <td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</td>
                        </tr>
                        <tr class="grand">
                            <td>Total</td>
                            <td class="value">{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <div class="summary-note">
                    Please reference invoice <strong>{{ $invoice->invoice_number }}</strong> for any billing questions or manual settlement updates.
                </div>

                <div class="footer">
                    {{ $branding['name'] ?? config('app.name', 'Pterodactyl') }}
                    @if(!empty($branding['website']))
                        &bull; {{ preg_replace('#^https?://#', '', $branding['website']) }}
                    @endif
                    <br>
                    This invoice was generated electronically and is valid without a physical signature.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
