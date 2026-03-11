@extends('layouts.admin')

@section('title')
    Billing Invoice
@endsection

@section('content-header')
    <h1>Billing Invoice<small>{{ $invoice->invoice_number }}</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Invoice Summary</h3></div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>User</dt><dd>{{ $invoice->user->email }}</dd>
                        <dt>Status</dt><dd><span class="label label-default">{{ strtoupper($invoice->status) }}</span></dd>
                        <dt>Type</dt><dd>{{ strtoupper($invoice->type) }}</dd>
                        <dt>Subtotal</dt><dd>RM {{ number_format((float) $invoice->subtotal, 2) }}</dd>
                        <dt>Tax</dt><dd>RM {{ number_format((float) $invoice->tax_total, 2) }}</dd>
                        <dt>Total</dt><dd>RM {{ number_format((float) $invoice->grand_total, 2) }}</dd>
                        <dt>Issued</dt><dd>{{ $invoice->issued_at ?? 'N/A' }}</dd>
                        <dt>Due</dt><dd>{{ $invoice->due_at ?? 'N/A' }}</dd>
                        <dt>Paid</dt><dd>{{ $invoice->paid_at ?? 'N/A' }}</dd>
                        @if($invoice->order)
                            <dt>Order</dt><dd><a href="{{ route('admin.billing.orders.view', $invoice->order->id) }}">#{{ $invoice->order->id }}</a></dd>
                        @endif
                        @if($invoice->subscription)
                            <dt>Subscription</dt><dd>#{{ $invoice->subscription->id }} @if($invoice->subscription->server) / {{ $invoice->subscription->server->name }} @endif</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Billing Snapshot</h3></div>
                <div class="box-body">
                    @php($snapshot = $invoice->billing_profile_snapshot ?? [])
                    @if(!empty($snapshot))
                        <dl class="dl-horizontal">
                            <dt>Legal Name</dt><dd>{{ $snapshot['legal_name'] ?? 'N/A' }}</dd>
                            <dt>Company</dt><dd>{{ $snapshot['company_name'] ?? 'N/A' }}</dd>
                            <dt>Email</dt><dd>{{ $snapshot['email'] ?? 'N/A' }}</dd>
                            <dt>Phone</dt><dd>{{ $snapshot['phone'] ?? 'N/A' }}</dd>
                            <dt>Country</dt><dd>{{ $snapshot['country_code'] ?? 'N/A' }}</dd>
                            <dt>Tax ID</dt><dd>{{ $snapshot['tax_id'] ?? 'N/A' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted">No billing snapshot was recorded for this invoice.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Invoice Items</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead><tr><th>Description</th><th>Type</th><th>Qty</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ strtoupper($item->type) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>RM {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Payment Trail</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead><tr><th>Payment</th><th>Status</th><th>Provider</th><th>Amount</th></tr></thead>
                        <tbody>
                            @forelse($invoice->payments as $payment)
                                <tr>
                                    <td><a href="{{ route('admin.billing.payments.view', $payment->id) }}">{{ $payment->payment_number }}</a></td>
                                    <td><span class="label label-default">{{ strtoupper($payment->status) }}</span></td>
                                    <td>{{ strtoupper($payment->provider) }}</td>
                                    <td>RM {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No payment records on this invoice yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border"><h3 class="box-title">Checkout Attempts</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead><tr><th>Attempt</th><th>Status</th><th>Checkout Reference</th><th>Verified</th></tr></thead>
                        <tbody>
                            @forelse($invoice->attempts as $attempt)
                                <tr>
                                    <td>#{{ $attempt->attempt_number }}</td>
                                    <td><span class="label label-default">{{ strtoupper($attempt->status) }}</span></td>
                                    <td><code>{{ $attempt->checkout_reference ?? 'N/A' }}</code></td>
                                    <td>{{ $attempt->verified_at ?? 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No checkout attempts recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
