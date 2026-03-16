@extends('layouts.admin')

@section('title')
    {{ $ticket->ticket_number }}
@endsection

@section('content-header')
    <h1>{{ $ticket->ticket_number }}<small>{{ $ticket->subject }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Support Tickets</a></li>
        <li class="active">{{ $ticket->ticket_number }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Summary</h3>
                </div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>Category</dt>
                        <dd>{{ strtoupper($ticket->category) }}</dd>
                        <dt>Status</dt>
                        <dd>{{ $ticket->status }}</dd>
                        <dt>User</dt>
                        <dd>{{ $ticket->user?->email ?? 'Unknown' }}</dd>
                        <dt>Assigned</dt>
                        <dd>{{ $ticket->assignedAdmin?->username ?? 'Unassigned' }}</dd>
                        <dt>Invoice</dt>
                        <dd>{{ $ticket->invoice?->invoice_number ?? 'N/A' }}</dd>
                        <dt>Payment</dt>
                        <dd>{{ $ticket->payment?->payment_number ?? 'N/A' }}</dd>
                        <dt>Discord</dt>
                        <dd>{{ $ticket->discord_thread_id ?: 'Not linked' }}</dd>
                    </dl>

                    @if($ticket->discord_thread_id && config('services.discord.guild_id'))
                        <a class="btn btn-default btn-block" target="_blank" rel="noreferrer"
                           href="https://discord.com/channels/{{ config('services.discord.guild_id') }}/{{ $ticket->discord_thread_id }}">
                            Open Discord Thread
                        </a>
                    @endif
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Update Ticket</h3>
                </div>
                <form method="POST" action="{{ route('admin.tickets.update', $ticket->id) }}">
                    <div class="box-body">
                        @csrf
                        @method('PATCH')
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                @foreach(['waiting_for_staff', 'waiting_for_user', 'resolved', 'closed'] as $status)
                                    <option value="{{ $status }}" @if($ticket->status === $status) selected @endif>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned Admin ID</label>
                            <input type="number" class="form-control" name="assigned_admin_id" value="{{ old('assigned_admin_id', $ticket->assigned_admin_id) }}">
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <a href="{{ route('admin.tickets') }}" class="btn btn-default">Back</a>
                    </div>
                </form>
                @if($ticket->status !== 'closed')
                    <div class="box-footer">
                        <form method="POST" action="{{ route('admin.tickets.update', $ticket->id) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="closed">
                            @if($ticket->assigned_admin_id)
                                <input type="hidden" name="assigned_admin_id" value="{{ $ticket->assigned_admin_id }}">
                            @endif
                            <button type="submit" class="btn btn-danger btn-block">Close Ticket</button>
                        </form>
                    </div>
                @endif
                @if($ticket->status === 'closed')
                    <div class="box-footer">
                        <form method="POST" action="{{ route('admin.tickets.reopen', $ticket->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-block">Reopen Ticket</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Transcript</h3>
                </div>
                <div class="box-body" style="max-height: 60vh; overflow-y: auto;">
                    @forelse($ticket->messages->sortBy('id') as $message)
                        <div class="well well-sm" style="background: rgba(0,0,0,0.03);">
                            <div class="clearfix">
                                <strong>{{ $message->author_display_name ?: strtoupper($message->author_type) }}</strong>
                                <span class="pull-right text-muted small">{{ optional($message->created_at)->toDayDateTimeString() }}</span>
                            </div>
                            @if($message->body)
                                <p style="white-space: pre-wrap; margin-top: 8px;">{{ $message->body }}</p>
                            @endif
                            @if($message->attachments->count() > 0)
                                <div class="small text-muted">
                                    Attachments:
                                    @foreach($message->attachments as $attachment)
                                        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('tickets.attachments.download', now()->addMinutes(60), ['ticketAttachment' => $attachment->id]) }}">
                                            {{ $attachment->original_name }}
                                        </a>@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted">No messages yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Reply</h3>
                </div>
                <form method="POST" action="{{ route('admin.tickets.messages.store', $ticket->id) }}" enctype="multipart/form-data">
                    <div class="box-body">
                        @csrf
                        <div class="form-group">
                            <label>Message</label>
                            <textarea class="form-control" rows="6" name="body"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Attachments</label>
                            <input type="file" name="attachments[]" multiple>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-success">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
