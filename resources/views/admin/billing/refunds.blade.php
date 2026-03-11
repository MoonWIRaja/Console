@extends('layouts.admin')

@section('title')
    Billing Refunds
@endsection

@section('content-header')
    <h1>Billing Refunds<small>All refund attempts and statuses.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Refund</th>
                        <th>Payment</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refunds as $refund)
                        <tr>
                            <td><code>{{ $refund->refund_number }}</code></td>
                            <td>{{ optional($refund->payment)->payment_number ?? 'N/A' }}</td>
                            <td>{{ optional(optional(optional($refund->payment)->invoice)->user)->email ?? 'N/A' }}</td>
                            <td><span class="label label-default">{{ strtoupper($refund->status) }}</span></td>
                            <td>RM {{ number_format((float) $refund->amount, 2) }}</td>
                            <td>{{ $refund->requested_at ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No refunds found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $refunds->links() }}</div>
    </div>
@endsection
