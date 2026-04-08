@extends('layouts.admin')

@section('title')
    Billing Webhook Events
@endsection

@section('content-header')
    <h1>Billing Webhook Events<small>Audit trail for gateway callbacks and replay.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Transaction</th>
                        <th>Status</th>
                        <th>Processed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ strtoupper($event->provider) }}</td>
                            <td><code>{{ $event->provider_transaction_id ?? $event->provider_event_id ?? 'N/A' }}</code></td>
                            <td><span class="label label-default">{{ strtoupper($event->status) }}</span></td>
                            <td>{{ $event->processed_at ?? 'N/A' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.billing.webhook-events.replay', $event->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">Replay</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No webhook events found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $events->links() }}</div>
    </div>
@endsection
