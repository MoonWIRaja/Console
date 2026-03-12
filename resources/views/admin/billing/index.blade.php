@extends('layouts.admin')

@section('title')
    Billing
@endsection

@section('content-header')
    <h1>Billing<small>Catalog setup, invoice lifecycle, and payment operations.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Billing</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    <div class="row">
        <div class="col-xs-12">
            <div class="callout callout-info">
                <p style="margin: 0;">
                    This dashboard is now invoice-driven. New paid orders auto-provision after verified payment.
                    Use <a href="{{ route('admin.billing.reconciliation') }}">Reconciliation</a> to audit exposure, failed callbacks, refund queue, and provisioning risk.
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{ $pendingOrders }}</h3>
                    <p>Pending Billing Order{{ $pendingOrders === 1 ? '' : 's' }}</p>
                </div>
                <div class="icon"><i class="ion ion-card"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>{{ $openInvoices }}</h3>
                    <p>Open / Processing Invoices</p>
                </div>
                <div class="icon"><i class="ion ion-document-text"></i></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>{{ $activeSubscriptions }}</h3>
                    <p>Tracked Subscriptions</p>
                </div>
                <div class="icon"><i class="ion ion-loop"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Open Invoices</h3></div>
                <div class="box-body"><strong>{{ $reconciliation['open_invoices'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Paid Invoices</h3></div>
                <div class="box-body"><strong>{{ $reconciliation['paid_invoices'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Failed Payments</h3></div>
                <div class="box-body"><strong>{{ $reconciliation['failed_payments'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Refunds Pending</h3></div>
                <div class="box-body"><strong>{{ $reconciliation['refunds_pending'] }}</strong></div>
            </div>
        </div>
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
                                    <td>{{ $config->orders()->where('status', \Pterodactyl\Models\BillingOrder::STATUS_PENDING)->count() }}</td>
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
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Billing Orders</h3>
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
                                    <td><span class="label label-default">{{ strtoupper($order->status) }}</span></td>
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
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Invoices</h3>
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
            </div>
        </div>
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Payments</h3>
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
            </div>
        </div>
    </div>
@endsection
