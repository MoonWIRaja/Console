@extends('layouts.admin')

@section('title')
    Billing Provision Failures
@endsection

@section('content-header')
    <h1>Provision Failures<small>Orders that paid but failed to provision.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>User</th>
                        <th>Invoice</th>
                        <th>Failure Code</th>
                        <th>Message</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td><a href="{{ route('admin.billing.orders.view', $order->id) }}">#{{ $order->id }}</a></td>
                            <td>{{ $order->user->email }}</td>
                            <td>{{ optional($order->invoice)->invoice_number ?? 'N/A' }}</td>
                            <td><code>{{ $order->provision_failure_code ?? 'N/A' }}</code></td>
                            <td>{{ $order->provision_failure_message ?? 'N/A' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.billing.orders.retry-provision', $order->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">Retry</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No provision failures found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $orders->links() }}</div>
    </div>
@endsection
