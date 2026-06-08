@extends('layouts.admin')

@section('title')
    Support Tickets
@endsection

@section('content-header')
    <h1>Support Tickets<small>Unified inbox for payment, refund, account, and general support requests, bridged with Discord.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Support Tickets</li>
    </ol>
@endsection

@section('content')
    <div class="row admin-full-row">
        <div class="col-xs-12 admin-full-col">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Inbox</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.tickets.settings') }}" class="btn btn-sm btn-default">Settings</a>
                    </div>
                </div>
                <div class="box-body">
                    <form method="GET" action="{{ route('admin.tickets') }}" class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Inbox</label>
                                <select class="form-control" name="inbox">
                                    <option value="open" @if($inbox === 'open') selected @endif>Open</option>
                                    <option value="mine" @if($inbox === 'mine') selected @endif>Mine</option>
                                    <option value="resolved" @if($inbox === 'resolved') selected @endif>Resolved</option>
                                    <option value="closed" @if($inbox === 'closed') selected @endif>Closed</option>
                                    <option value="all" @if($inbox === 'all') selected @endif>All</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Category</label>
                                <select class="form-control" name="category">
                                    <option value="all" @if($category === 'all') selected @endif>All</option>
                                    <option value="payment" @if($category === 'payment') selected @endif>Payment</option>
                                    <option value="refund" @if($category === 'refund') selected @endif>Refund</option>
                                    <option value="support" @if($category === 'support') selected @endif>Support</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 admin-pad-top-xl">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="{{ route('admin.tickets') }}" class="btn btn-default">Reset</a>
                        </div>
                    </form>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>User</th>
                                <th>Server</th>
                                <th>Assigned</th>
                                <th>Discord</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tickets as $ticket)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.tickets.view', $ticket->id) }}"><strong>{{ $ticket->ticket_number }}</strong></a>
                                        <div class="text-muted small">{{ $ticket->subject }}</div>
                                    </td>
                                    <td>{{ strtoupper($ticket->category) }}</td>
                                    <td>
                                        <span class="label label-{{ in_array($ticket->status, ['resolved', 'closed']) ? 'default' : 'success' }}">
                                            {{ $ticket->status }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $ticket->user?->email ?? 'Unknown' }}
                                    </td>
                                    <td>{{ $ticket->subscription?->server_name ?? $ticket->order?->server_name ?? 'N/A' }}</td>
                                    <td>{{ $ticket->assignedAdmin?->username ?? 'Unassigned' }}</td>
                                    <td>
                                        <span class="label label-{{ $ticket->discord_sync_status === 'synced' ? 'primary' : ($ticket->discord_sync_status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ $ticket->discord_sync_status }}
                                        </span>
                                    </td>
                                    <td>{{ optional($ticket->updated_at)->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($tickets, 'links'))
                    <div class="box-footer clearfix">
                        {{ $tickets->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
