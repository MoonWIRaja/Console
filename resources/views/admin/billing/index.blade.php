@extends('layouts.admin')

@section('title')
    Billing
@endsection

@section('content-header')
    <h1>Billing<small>Catalog setup, invoice lifecycle, and manual settlement operations.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Billing</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    <div class="bl-head">
        <h3 class="bl-title">Billing overview</h3>
        <span class="bl-info" tabindex="0">
            <button type="button" class="bl-info-btn" aria-label="How billing works">!</button>
            <span class="bl-info-tip">Billing runs in manual, invoice-driven mode. New orders, renewals and upgrades stay pending until a billing admin verifies the payment and records it. Use <b>Reconciliation</b> to audit unpaid exposure, the refund queue, provisioning risk and old gateway leftovers.</span>
        </span>
        <a href="{{ route('admin.billing.reconciliation') }}" class="btn btn-default btn-sm bl-head-btn">Reconciliation</a>
    </div>

    <div class="bl-stats">
        <div class="bl-stat {{ $pendingOrders ? 'warn' : '' }}"><span>Awaiting approval</span><strong>{{ $pendingOrders }}</strong><small>order{{ $pendingOrders === 1 ? '' : 's' }} to review</small></div>
        <div class="bl-stat {{ $openInvoices ? 'warn' : '' }}"><span>Open invoices</span><strong>{{ $openInvoices }}</strong><small>open or processing</small></div>
        <div class="bl-stat good"><span>Paid invoices</span><strong>{{ $reconciliation['paid_invoices'] }}</strong><small>all time</small></div>
        <div class="bl-stat {{ $reconciliation['failed_payments'] ? 'bad' : '' }}"><span>Failed payments</span><strong>{{ $reconciliation['failed_payments'] }}</strong><small>need follow-up</small></div>
        <div class="bl-stat {{ $reconciliation['refunds_pending'] ? 'warn' : '' }}"><span>Refunds pending</span><strong>{{ $reconciliation['refunds_pending'] }}</strong><small>in the queue</small></div>
        <div class="bl-stat"><span>Subscriptions</span><strong>{{ $activeSubscriptions }}</strong><small>tracked</small></div>
    </div>


    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Node Billing Setup</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Node</th>
                                <th>Billing</th>
                                <th>Max vCore / Order</th>
                                <th>RAM Remaining</th>
                                <th>Storage Remaining</th>
                                <th>Games</th>
                                <th>Pending Orders</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summaries as $summary)
                                @php($config = $summary['config'])
                                @php($availability = $summary['availability'])
                                <tr>
                                    <td>
                                        <strong>{{ $config->display_name }}</strong><br>
                                        <small class="text-muted">
                                            {{ optional($config->node->location)->short ?? 'No Location' }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($config->enabled)
                                            <span class="label label-success">Enabled</span>
                                        @else
                                            <span class="label label-default">Disabled</span>
                                        @endif
                                    </td>
                                    <td>{{ $availability['cpu_remaining'] }} vCore</td>
                                    <td>
                                        {{ $availability['memory_remaining_gb'] }} GB
                                        <small class="text-muted">
                                            / {{ $availability['node_memory_remaining_gb'] }} GB node
                                            · {{ $availability['billing_memory_remaining_gb'] }} GB billing
                                        </small>
                                    </td>
                                    <td>
                                        {{ $availability['disk_remaining_gb'] }} GB
                                        <small class="text-muted">
                                            / {{ $availability['node_disk_remaining_gb'] }} GB node
                                            · {{ $availability['billing_disk_remaining_gb'] }} GB billing
                                        </small>
                                    </td>
                                    <td>{{ $config->gameProfiles->where('enabled', true)->count() }}</td>
                                    <td>{{ $config->orders()->where('status', \Pterodactyl\Models\BillingOrder::STATUS_AWAITING_PAYMENT)->count() }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.nodes.view', $config->node_id) }}" class="btn btn-xs btn-primary">Setup</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border admin-billing-box-header">
                    <h3 class="box-title">Recent Billing Orders</h3>
                    @include('admin.billing.partials.table-filter', [
                        'name' => 'orders_status',
                        'value' => $selectedOrderStatus,
                        'options' => $orderStatusOptions,
                        'pageName' => 'orders_page',
                        'placeholder' => 'All order statuses',
                    ])
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Node</th>
                                <th>Game</th>
                                <th>Resources</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td><code>#{{ $order->id }}</code></td>
                                    <td>{{ $order->user->email }}</td>
                                    <td>{{ $order->node_name }}</td>
                                    <td>{{ $order->game_name }}</td>
                                    <td>{{ $order->cpu_cores }} / {{ $order->memory_gb }} GB / {{ $order->disk_gb }} GB</td>
                                    <td>
                                        <span class="label {{ $order->status === \Pterodactyl\Models\BillingOrder::STATUS_AWAITING_PAYMENT ? 'label-warning' : 'label-default' }}">
                                            {{ strtoupper($order->status) }}
                                        </span>
                                    </td>
                                    <td>RM {{ number_format((float) $order->total, 2) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.orders.view', $order->id) }}" class="btn btn-xs btn-default">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No billing orders have been created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer clearfix">
                    @include('admin.billing.partials.table-pagination', ['paginator' => $recentOrders])
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border admin-billing-box-header">
                    <h3 class="box-title">Recent Invoices</h3>
                    @include('admin.billing.partials.table-filter', [
                        'name' => 'invoices_status',
                        'value' => $selectedInvoiceStatus,
                        'options' => $invoiceStatusOptions,
                        'pageName' => 'invoices_page',
                        'placeholder' => 'All invoice statuses',
                    ])
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInvoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('admin.billing.invoices.view', $invoice->id) }}">{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->user->email }}</td>
                                    <td><span class="label label-default">{{ strtoupper($invoice->status) }}</span></td>
                                    <td>RM {{ number_format((float) $invoice->grand_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No invoices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer clearfix">
                    @include('admin.billing.partials.table-pagination', ['paginator' => $recentInvoices])
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border admin-billing-box-header">
                    <h3 class="box-title">Recent Payments</h3>
                    @include('admin.billing.partials.table-filter', [
                        'name' => 'payments_status',
                        'value' => $selectedPaymentStatus,
                        'options' => $paymentStatusOptions,
                        'pageName' => 'payments_page',
                        'placeholder' => 'All payment statuses',
                    ])
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th>Invoice</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentPayments as $payment)
                                <tr>
                                    <td><a href="{{ route('admin.billing.payments.view', $payment->id) }}">{{ $payment->payment_number }}</a></td>
                                    <td>{{ optional($payment->invoice)->invoice_number ?? 'N/A' }}</td>
                                    <td><span class="label label-default">{{ strtoupper($payment->status) }}</span></td>
                                    <td>RM {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No payments yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer clearfix">
                    @include('admin.billing.partials.table-pagination', ['paginator' => $recentPayments])
                </div>
            </div>
        </div>
    </div>
@endsection
