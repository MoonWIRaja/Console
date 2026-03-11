@extends('layouts.admin')

@section('title')
    Billing Payment
@endsection

@section('content-header')
    <h1>Billing Payment<small>{{ $payment->payment_number }}</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="row">
        <div class="col-md-7">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Payment Summary</h3></div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>Invoice</dt><dd><a href="{{ route('admin.billing.invoices.view', $payment->invoice->id) }}">{{ $payment->invoice->invoice_number }}</a></dd>
                        <dt>User</dt><dd>{{ $payment->invoice->user->email }}</dd>
                        <dt>Status</dt><dd><span class="label label-default">{{ strtoupper($payment->status) }}</span></dd>
                        <dt>Provider</dt><dd>{{ strtoupper($payment->provider) }}</dd>
                        <dt>Provider Txn</dt><dd><code>{{ $payment->provider_transaction_id ?? 'N/A' }}</code></dd>
                        <dt>Method</dt><dd>{{ $payment->provider_payment_method ?? 'N/A' }}</dd>
                        <dt>Amount</dt><dd>RM {{ number_format((float) $payment->amount, 2) }}</dd>
                        <dt>Paid At</dt><dd>{{ $payment->paid_at ?? 'N/A' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Refund</h3></div>
                <form method="POST" action="{{ route('admin.billing.payments.refund', $payment->id) }}">
                    @csrf
                    <div class="box-body">
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="number" step="0.01" min="0.01" max="{{ (float) $payment->amount }}" name="amount" class="form-control" value="{{ number_format((float) $payment->amount, 2, '.', '') }}">
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-danger">Submit Refund</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
