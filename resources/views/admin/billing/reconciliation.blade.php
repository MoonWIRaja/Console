@extends('layouts.admin')

@section('title')
    Billing Reconciliation
@endsection

@section('content-header')
    <h1>Billing Reconciliation<small>Trace outstanding invoices, refund queue, and settlement risk.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Reconciliation</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    <div class="row">
        <div class="col-md-3">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3>RM {{ number_format((float) $summary['verified_gross'], 2) }}</h3>
                    <p>Verified Gross Collected</p>
                </div>
                <div class="icon"><i class="ion ion-cash"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3>RM {{ number_format((float) $summary['refunded_total'], 2) }}</h3>
                    <p>Completed Refund Total</p>
                </div>
                <div class="icon"><i class="ion ion-reply"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>RM {{ number_format((float) $summary['net_collected'], 2) }}</h3>
                    <p>Estimated Net Collected</p>
                </div>
                <div class="icon"><i class="ion ion-stats-bars"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>RM {{ number_format((float) $summary['outstanding_invoices'], 2) }}</h3>
                    <p>Outstanding Invoice Exposure</p>
                </div>
                <div class="icon"><i class="ion ion-document-text"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Open</h3></div>
                <div class="box-body"><strong>{{ $summary['open_invoices'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Paid</h3></div>
                <div class="box-body"><strong>{{ $summary['paid_invoices'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Payment Failures</h3></div>
                <div class="box-body"><strong>{{ $summary['failed_payments'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Refunds Pending</h3></div>
                <div class="box-body"><strong>{{ $summary['refunds_pending'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Webhook Failures</h3></div>
                <div class="box-body"><strong>{{ $summary['webhook_failures'] }}</strong></div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Provision Failures</h3></div>
                <div class="box-body"><strong>{{ $summary['provision_failures'] }}</strong></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Open / Processing Invoices</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($open_invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('admin.billing.invoices.view', $invoice->id) }}">{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->user->email }}</td>
                                    <td><span class="label label-default">{{ strtoupper($invoice->status) }}</span></td>
                                    <td>{{ $invoice->due_at ?? 'N/A' }}</td>
                                    <td>RM {{ number_format((float) $invoice->grand_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No open invoices.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Failed Payments</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th>User</th>
                                <th>Status</th>
                                <th>Provider Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($failed_payments as $payment)
                                <tr>
                                    <td><a href="{{ route('admin.billing.payments.view', $payment->id) }}">{{ $payment->payment_number }}</a></td>
                                    <td>{{ optional(optional($payment->invoice)->user)->email ?? 'N/A' }}</td>
                                    <td><span class="label label-danger">{{ strtoupper($payment->status) }}</span></td>
                                    <td>{{ $payment->provider_status ?? 'N/A' }}</td>
                                    <td>RM {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No failed payments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Pending Refund Queue</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Refund</th>
                                <th>Payment</th>
                                <th>User</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pending_refunds as $refund)
                                <tr>
                                    <td><code>{{ $refund->refund_number }}</code></td>
                                    <td>{{ optional($refund->payment)->payment_number ?? 'N/A' }}</td>
                                    <td>{{ optional(optional(optional($refund->payment)->invoice)->user)->email ?? 'N/A' }}</td>
                                    <td>RM {{ number_format((float) $refund->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No pending refunds.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Failed Webhook Events</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Transaction</th>
                                <th>Error</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($failed_webhook_events as $event)
                                <tr>
                                    <td>{{ strtoupper($event->provider) }}</td>
                                    <td><code>{{ $event->provider_transaction_id ?? $event->provider_event_id ?? 'N/A' }}</code></td>
                                    <td>{{ $event->processing_error ?: 'N/A' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.webhook-events') }}" class="btn btn-xs btn-default">Audit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No failed webhook events.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Provision Failure Queue</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>User</th>
                                <th>Invoice</th>
                                <th>Failure</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($provision_failures as $order)
                                <tr>
                                    <td><a href="{{ route('admin.billing.orders.view', $order->id) }}">#{{ $order->id }}</a></td>
                                    <td>{{ $order->user->email }}</td>
                                    <td>{{ optional($order->invoice)->invoice_number ?? 'N/A' }}</td>
                                    <td>{{ $order->provision_failure_message ?? $order->provision_failure_code ?? 'N/A' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.provision-failures') }}" class="btn btn-xs btn-default">Open Queue</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No provision failures.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
