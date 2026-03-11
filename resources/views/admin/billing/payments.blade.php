@extends('layouts.admin')

@section('title')
    Billing Payments
@endsection

@section('content-header')
    <h1>Billing Payments<small>Gateway verified payment records.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Invoice</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Provider Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td><a href="{{ route('admin.billing.payments.view', $payment->id) }}">{{ $payment->payment_number }}</a></td>
                            <td>{{ optional($payment->invoice)->invoice_number ?? 'N/A' }}</td>
                            <td>{{ optional(optional($payment->invoice)->user)->email ?? 'N/A' }}</td>
                            <td><span class="label label-default">{{ strtoupper($payment->status) }}</span></td>
                            <td>{{ $payment->provider_status ?? 'N/A' }}</td>
                            <td>RM {{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $payments->links() }}</div>
    </div>
@endsection
