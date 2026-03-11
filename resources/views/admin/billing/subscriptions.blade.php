@extends('layouts.admin')

@section('title')
    Billing Subscriptions
@endsection

@section('content-header')
    <h1>Billing Subscriptions<small>Recurring lifecycle and renewal state.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Server</th>
                        <th>Status</th>
                        <th>Auto Renew</th>
                        <th>Renews At</th>
                        <th>Recurring</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                        <tr>
                            <td><code>#{{ $subscription->id }}</code></td>
                            <td>{{ $subscription->user->email }}</td>
                            <td>{{ $subscription->server_name }}</td>
                            <td><span class="label label-default">{{ strtoupper($subscription->status) }}</span></td>
                            <td>{{ $subscription->auto_renew ? 'Yes' : 'No' }}</td>
                            <td>{{ $subscription->renews_at ?? 'N/A' }}</td>
                            <td>RM {{ number_format((float) $subscription->recurring_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No subscriptions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $subscriptions->links() }}</div>
    </div>
@endsection
