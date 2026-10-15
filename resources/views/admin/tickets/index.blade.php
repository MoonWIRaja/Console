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
    @php($statusMeta = [
        'waiting_for_staff' => ['Needs reply', 'warn'],
        'waiting_for_user' => ['Waiting on user', 'info'],
        'open' => ['Open', 'warn'],
        'in_progress' => ['In progress', 'info'],
        'resolved' => ['Resolved', 'ok'],
        'closed' => ['Closed', 'mute'],
    ])
    @php($syncMeta = ['synced' => ['Synced', 'ok'], 'failed' => ['Failed', 'bad']])
    <style>
        .tk-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .tk-pills { display: flex; gap: 4px; flex-wrap: wrap; background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 999px; padding: 3px; }
        .tk-pill, .tk-pill:hover, .tk-pill:focus { color: #742220 !important; text-decoration: none !important; }
        .tk-pill { border-radius: 999px; padding: 5px 14px; font-size: 11px; font-weight: 800; }
        .tk-pill:hover { background: rgba(45,74,62,.08); }
        body.skin-blue .tk-pill span { color: #742220 !important; }
        body.skin-blue .tk-pill.active span { color: #FEF9E1 !important; }
        .tk-pill.active, .tk-pill.active:hover, .tk-pill.active:focus { background: #2D4A3E; color: #FEF9E1 !important; }
        .tk-cat { margin-left: auto; display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.6); }
        .tk-cat select { width: 160px; }
        .tk-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 3px 10px; font-size: 10px; font-weight: 800; border: 1px solid; white-space: nowrap; }
        .tk-badge::before { content: ''; width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
        .tk-badge.warn { color: #9A5B00; border-color: rgba(202,138,4,.45); background: rgba(202,138,4,.12); }
        .tk-badge.info { color: #2D4A3E; border-color: rgba(45,74,62,.35); background: rgba(45,74,62,.08); }
        .tk-badge.ok { color: #2D6A4F; border-color: rgba(45,106,79,.4); background: rgba(45,106,79,.1); }
        .tk-badge.bad { color: #A03A2F; border-color: rgba(160,58,47,.4); background: rgba(160,58,47,.08); }
        .tk-badge.mute { color: #6B6257; border-color: rgba(107,98,87,.35); background: rgba(107,98,87,.08); }
        .tk-no, .tk-no:hover { font-weight: 900; color: #742220 !important; }
        .tk-subject { font-size: 11px; color: rgba(75,58,47,.8); margin-top: 2px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tk-cat-chip { font-size: 9px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.7); }
        .tk-muted { color: rgba(116,34,32,.5); }
        .tk-empty { text-align: center; padding: 34px 14px !important; color: rgba(116,34,32,.6); }
        .tk-row { cursor: pointer; }
    </style>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Inbox</h3>
            <div class="box-tools">
                <a href="{{ route('admin.tickets.settings') }}" class="btn btn-sm btn-default"><i class="fa fa-cog"></i> Settings</a>
            </div>
        </div>
        <div class="box-body">
            <form method="GET" action="{{ route('admin.tickets') }}" class="tk-bar">
                <input type="hidden" name="inbox" value="{{ $inbox }}">
                <div class="tk-pills">
                    @foreach(['open' => 'Open', 'mine' => 'Mine', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $key => $label)
                        <a href="{{ route('admin.tickets', ['inbox' => $key, 'category' => $category]) }}" class="tk-pill @if($inbox === $key) active @endif"><span>{{ $label }}</span></a>
                    @endforeach
                </div>
                <label class="tk-cat">Category
                    <select class="form-control input-sm" name="category" onchange="this.form.submit()">
                        @foreach(['all' => 'All categories', 'payment' => 'Payment', 'refund' => 'Refund', 'support' => 'Support'] as $key => $label)
                            <option value="{{ $key }}" @if($category === $key) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </form>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Status</th>
                        <th>User</th>
                        <th>Server</th>
                        <th>Assigned</th>
                        <th>Discord</th>
                        <th class="text-right">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        @php($st = $statusMeta[$ticket->status] ?? [ucwords(str_replace('_', ' ', $ticket->status)), 'mute'])
                        @php($sy = $syncMeta[$ticket->discord_sync_status] ?? [ucfirst((string) $ticket->discord_sync_status ?: 'Pending'), 'warn'])
                        <tr class="tk-row" onclick="if (!event.target.closest('a')) window.location='{{ route('admin.tickets.view', $ticket->id) }}'">
                            <td>
                                <a class="tk-no" href="{{ route('admin.tickets.view', $ticket->id) }}">{{ $ticket->ticket_number }}</a>
                                <span class="tk-cat-chip">· {{ $ticket->category }}</span>
                                <div class="tk-subject" title="{{ $ticket->subject }}">{{ $ticket->subject }}</div>
                            </td>
                            <td><span class="tk-badge {{ $st[1] }}">{{ $st[0] }}</span></td>
                            <td>{{ $ticket->user?->email ?? 'Unknown' }}</td>
                            <td>@if($name = $ticket->subscription?->server_name ?? $ticket->order?->server_name){{ $name }}@else<span class="tk-muted">—</span>@endif</td>
                            <td>@if($ticket->assignedAdmin){{ $ticket->assignedAdmin->username }}@else<span class="tk-muted">Unassigned</span>@endif</td>
                            <td><span class="tk-badge {{ $sy[1] }}">{{ $sy[0] }}</span></td>
                            <td class="text-right tk-muted" style="white-space:nowrap">{{ optional($ticket->updated_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tk-empty">No tickets in this inbox.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($tickets, 'links') && $tickets->hasPages())
            <div class="box-footer clearfix">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
@endsection
