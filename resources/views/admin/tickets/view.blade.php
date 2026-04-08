@extends('layouts.admin')

@php
    $sortedMessages = $ticket->messages->sortBy('id');
    $discordThreadUrl = $ticket->discord_thread_id && config('services.discord.guild_id')
        ? 'https://discord.com/channels/' . config('services.discord.guild_id') . '/' . $ticket->discord_thread_id
        : null;
@endphp

@section('title')
    {{ $ticket->ticket_number }}
@endsection

@section('scripts')
    @parent
    <style>
        .admin-ticket-hover-button {
            position: relative;
            display: inline-flex;
            min-width: 11.25rem;
            height: 44px;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--admin-border);
            border-radius: 999px;
            background: var(--admin-card);
            color: var(--admin-foreground) !important;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-decoration: none !important;
            transition: border-color 0.25s ease, transform 0.25s ease, box-shadow 0.25s ease;
        }

        .admin-ticket-hover-button:hover,
        .admin-ticket-hover-button:focus {
            border-color: var(--admin-primary);
            box-shadow: 0 0 24px rgba(var(--admin-primary-rgb), 0.22);
            color: #071006 !important;
            text-decoration: none !important;
        }

        .admin-ticket-hover-button-label,
        .admin-ticket-hover-button-hover {
            position: relative;
            z-index: 2;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .admin-ticket-hover-button-hover {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transform: translateX(28px);
        }

        .admin-ticket-hover-button-fill {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 1;
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: var(--admin-primary);
            opacity: 0.95;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }

        .admin-ticket-hover-button:hover .admin-ticket-hover-button-label,
        .admin-ticket-hover-button:focus .admin-ticket-hover-button-label {
            opacity: 0;
            transform: translateX(-16px);
        }

        .admin-ticket-hover-button:hover .admin-ticket-hover-button-hover,
        .admin-ticket-hover-button:focus .admin-ticket-hover-button-hover {
            opacity: 1;
            transform: translateX(0);
        }

        .admin-ticket-hover-button:hover .admin-ticket-hover-button-fill,
        .admin-ticket-hover-button:focus .admin-ticket-hover-button-fill {
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
            opacity: 1;
            transform: none;
        }

        .admin-ticket-chat-box,
        .admin-ticket-reply-box {
            border-color: var(--admin-border);
            background: transparent;
            box-shadow: none;
        }

        .admin-ticket-chat-box .box-header,
        .admin-ticket-reply-box .box-header {
            border-bottom-color: rgba(255, 255, 255, 0.06);
            background: rgba(var(--admin-card-rgb), 0.72);
        }

        .admin-ticket-chat-box .box-title,
        .admin-ticket-reply-box .box-title {
            color: var(--admin-foreground);
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .admin-ticket-chat-box .box-body,
        .admin-ticket-reply-box .box-body {
            padding: 18px;
            background: rgba(var(--admin-background-rgb), 0.35);
        }

        .admin-ticket-chat-shell {
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
            box-shadow: 0 34px 80px -48px rgba(0, 0, 0, 0.88);
        }

        .admin-ticket-message-list {
            max-height: 60vh;
            overflow-y: auto;
            padding: 24px;
            background:
                radial-gradient(circle at top, rgba(var(--admin-primary-rgb), 0.08), transparent 34%),
                linear-gradient(180deg, rgba(4, 8, 14, 0.96), rgba(2, 4, 8, 0.96));
        }

        .admin-ticket-empty-state {
            display: flex;
            min-height: 220px;
            align-items: center;
            justify-content: center;
            border: 1px dashed rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.02);
            padding: 24px;
            color: var(--admin-muted-foreground);
            text-align: center;
        }

        .admin-ticket-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
        }

        .admin-ticket-row:last-child {
            margin-bottom: 0;
        }

        .admin-ticket-row.is-user {
            flex-direction: row-reverse;
        }

        .admin-ticket-row.is-user .admin-ticket-stack {
            align-items: flex-end;
        }

        .admin-ticket-row.is-user .admin-ticket-meta {
            justify-content: flex-end;
            text-align: right;
        }

        .admin-ticket-avatar {
            display: flex;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.04);
            color: #f8f6ef;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .admin-ticket-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-ticket-row.is-user .admin-ticket-avatar {
            border-color: rgba(var(--admin-primary-rgb), 0.28);
            background: rgba(var(--admin-primary-rgb), 0.16);
            color: #efffc8;
        }

        .admin-ticket-row.is-admin .admin-ticket-avatar {
            border-color: rgba(56, 189, 248, 0.28);
            background: rgba(14, 165, 233, 0.16);
            color: #e0f2fe;
        }

        .admin-ticket-row.is-system .admin-ticket-avatar {
            border-color: rgba(245, 158, 11, 0.28);
            background: rgba(245, 158, 11, 0.16);
            color: #fef3c7;
        }

        .admin-ticket-stack {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-width: min(84%, 46rem);
        }

        .admin-ticket-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 0 4px;
            color: var(--admin-muted-foreground);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .admin-ticket-meta-dot {
            opacity: 0.4;
        }

        .admin-ticket-bubble {
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 22px;
            padding: 14px 16px;
            box-shadow: 0 18px 30px rgba(0, 0, 0, 0.16);
        }

        .admin-ticket-row.is-user .admin-ticket-bubble {
            border-color: rgba(var(--admin-primary-rgb), 0.28);
            border-bottom-right-radius: 10px;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.32), rgba(var(--admin-primary-rgb), 0.08));
            color: #f8f6ef;
        }

        .admin-ticket-row.is-admin .admin-ticket-bubble {
            border-color: rgba(56, 189, 248, 0.22);
            border-bottom-left-radius: 10px;
            background: rgba(14, 165, 233, 0.1);
            color: #e0f2fe;
        }

        .admin-ticket-row.is-system .admin-ticket-bubble {
            border-color: rgba(245, 158, 11, 0.22);
            border-bottom-left-radius: 10px;
            background: rgba(245, 158, 11, 0.1);
            color: #fef3c7;
        }

        .admin-ticket-message-body {
            margin: 0;
            white-space: pre-wrap;
            font-size: 13px;
            line-height: 1.8;
        }

        .admin-ticket-message-empty {
            margin: 0;
            font-size: 13px;
            font-style: italic;
            opacity: 0.8;
        }

        .admin-ticket-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .admin-ticket-attachment {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: #f8f6ef !important;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none !important;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .admin-ticket-attachment:hover,
        .admin-ticket-attachment:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.28);
            background: rgba(var(--admin-primary-rgb), 0.12);
            color: #f8f6ef !important;
            text-decoration: none !important;
        }

        .admin-ticket-attachment-meta {
            color: var(--admin-muted-foreground);
        }

        .admin-ticket-reply-top {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 14px;
        }

        .admin-ticket-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border: 1px solid rgba(245, 158, 11, 0.24);
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.12);
            color: #fef3c7;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-ticket-prompt-shell {
            padding: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 30px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.025));
            box-shadow: 0 24px 80px -44px rgba(0, 0, 0, 0.9);
        }

        .admin-ticket-prompt-inner {
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 26px;
            background: rgba(3, 8, 14, 0.86);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .admin-ticket-selected-files {
            display: none;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .admin-ticket-selected-files.has-files {
            display: flex;
        }

        .admin-ticket-file-chip {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.24);
            border-radius: 999px;
            background: rgba(var(--admin-primary-rgb), 0.1);
            color: #f8f6ef;
            font-size: 12px;
            font-weight: 600;
        }

        .admin-ticket-file-chip-name {
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-ticket-file-chip-meta {
            color: var(--admin-muted-foreground);
        }

        .admin-ticket-file-chip-remove {
            display: inline-flex;
            width: 20px;
            height: 20px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--admin-muted-foreground);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .admin-ticket-file-chip-remove:hover,
        .admin-ticket-file-chip-remove:focus {
            background: rgba(255, 255, 255, 0.1);
            color: #f8f6ef;
            outline: none;
        }

        .admin-ticket-prompt-input {
            width: 100%;
            min-height: 72px;
            max-height: 220px;
            resize: none;
            border: 0;
            background: transparent;
            color: #f8f6ef;
            font-size: 13px;
            line-height: 1.8;
            outline: none;
        }

        .admin-ticket-prompt-input::placeholder {
            color: var(--admin-muted-foreground);
        }

        .admin-ticket-prompt-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 10px;
            padding: 14px 4px 2px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .admin-ticket-prompt-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .admin-ticket-icon-btn {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            color: #f8f6ef;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .admin-ticket-icon-btn:hover,
        .admin-ticket-icon-btn:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.26);
            background: rgba(var(--admin-primary-rgb), 0.12);
            color: #f8f6ef;
            outline: none;
        }

        .admin-ticket-sync-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            color: var(--admin-muted-foreground);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .admin-ticket-sync-pill .material-icons-round {
            color: var(--admin-primary);
            font-size: 14px;
        }

        .admin-ticket-send-btn {
            display: inline-flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.88), rgba(var(--admin-primary-rgb), 0.58));
            color: #08110d;
            box-shadow: 0 0 28px rgba(var(--admin-primary-rgb), 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .admin-ticket-send-btn:hover,
        .admin-ticket-send-btn:focus {
            transform: scale(1.03);
            box-shadow: 0 0 32px rgba(var(--admin-primary-rgb), 0.32);
            color: #08110d;
            outline: none;
        }

        .admin-ticket-send-btn .material-icons-round {
            font-size: 20px;
        }

        @media (max-width: 767px) {
            .admin-ticket-chat-box .box-body,
            .admin-ticket-reply-box .box-body {
                padding: 14px;
            }

            .admin-ticket-message-list {
                padding: 16px;
            }

            .admin-ticket-stack {
                max-width: calc(100% - 52px);
            }

            .admin-ticket-prompt-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .admin-ticket-prompt-actions {
                justify-content: space-between;
            }

            .admin-ticket-send-btn {
                align-self: flex-end;
            }
        }
    </style>
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

                    @if($discordThreadUrl)
                        <a class="admin-ticket-hover-button" href="{{ $discordThreadUrl }}" target="_blank" rel="noreferrer">
                            <span class="admin-ticket-hover-button-label">Open Discord Thread</span>
                            <span class="admin-ticket-hover-button-hover">
                                <span>Open Discord Thread</span>
                                <i class="fa fa-arrow-right"></i>
                            </span>
                            <span class="admin-ticket-hover-button-fill"></span>
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
                        <form method="POST" action="{{ route('admin.tickets.close', $ticket->id) }}">
                            @csrf
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
            <div class="box box-primary admin-ticket-chat-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Transcript</h3>
                </div>
                <div class="box-body">
                    <div class="admin-ticket-chat-shell">
                        <div class="admin-ticket-message-list" id="adminTicketTranscript">
                            @forelse($sortedMessages as $message)
                                @php
                                    $displayName = $message->author_display_name ?: strtoupper($message->author_type);
                                    $messageType = $message->author_type === \Pterodactyl\Models\TicketMessage::AUTHOR_ADMIN
                                        ? 'admin'
                                        : ($message->author_type === \Pterodactyl\Models\TicketMessage::AUTHOR_SYSTEM ? 'system' : 'user');
                                    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(\Illuminate\Support\Str::squish($displayName), 0, 2));
                                    $initials = $initials !== '' ? $initials : '?';
                                @endphp
                                <article class="admin-ticket-row is-{{ $messageType }}">
                                    <div class="admin-ticket-avatar">
                                        @if($message->author_avatar_url)
                                            <img src="{{ $message->author_avatar_url }}" alt="Message avatar">
                                        @else
                                            <span>{{ $initials }}</span>
                                        @endif
                                    </div>
                                    <div class="admin-ticket-stack">
                                        <div class="admin-ticket-meta">
                                            <span>{{ $displayName }}</span>
                                            <span class="admin-ticket-meta-dot">•</span>
                                            <span>{{ optional($message->created_at)->toDayDateTimeString() }}</span>
                                        </div>
                                        <div class="admin-ticket-bubble">
                                            @if($message->body)
                                                <p class="admin-ticket-message-body">{{ $message->body }}</p>
                                            @else
                                                <p class="admin-ticket-message-empty">Attachment only message</p>
                                            @endif

                                            @if($message->attachments->count() > 0)
                                                <div class="admin-ticket-attachments">
                                                    @foreach($message->attachments as $attachment)
                                                        <a
                                                            class="admin-ticket-attachment"
                                                            href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('tickets.attachments.download', now()->addMinutes(60), ['ticketAttachment' => $attachment->id]) }}"
                                                        >
                                                            <i class="fa fa-paperclip"></i>
                                                            <span>{{ $attachment->original_name }}</span>
                                                            <span class="admin-ticket-attachment-meta">
                                                                {{ number_format($attachment->size_bytes / 1024, 1) }} KB
                                                            </span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="admin-ticket-empty-state">
                                    No messages yet. Start the conversation from the reply box below.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="box box-success admin-ticket-reply-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Reply</h3>
                </div>
                <div class="box-body">
                    @if($ticket->status === 'resolved' || $ticket->status === 'closed')
                        <div class="admin-ticket-reply-top">
                            <span class="admin-ticket-status-badge">Reopen to reply</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.tickets.messages.store', $ticket->id) }}" enctype="multipart/form-data" id="adminTicketReplyForm">
                        @csrf
                        <input id="adminTicketFileInput" type="file" name="attachments[]" multiple hidden>

                        <div class="admin-ticket-prompt-shell">
                            <div class="admin-ticket-prompt-inner">
                                <div class="admin-ticket-selected-files" id="adminTicketSelectedFiles"></div>
                                <textarea class="admin-ticket-prompt-input" id="adminTicketReplyBody" rows="1" name="body" placeholder="Write your message here...">{{ old('body') }}</textarea>

                                <div class="admin-ticket-prompt-footer">
                                    <div class="admin-ticket-prompt-actions">
                                        <button type="button" class="admin-ticket-icon-btn" id="adminTicketAttachButton">
                                            <i class="fa fa-paperclip"></i>
                                            <span class="sr-only">Attach files</span>
                                        </button>
                                        <span class="admin-ticket-sync-pill">
                                            <span class="material-icons-round">auto_awesome</span>
                                            <span>Discord Sync</span>
                                        </span>
                                    </div>
                                    <button type="submit" class="admin-ticket-send-btn">
                                        <span class="material-icons-round">north</span>
                                        <span class="sr-only">Send reply</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var form = document.getElementById('adminTicketReplyForm');
            var textarea = document.getElementById('adminTicketReplyBody');
            var fileInput = document.getElementById('adminTicketFileInput');
            var attachButton = document.getElementById('adminTicketAttachButton');
            var selectedFiles = document.getElementById('adminTicketSelectedFiles');
            var transcript = document.getElementById('adminTicketTranscript');

            function formatFileSize(size) {
                if (size < 1024) {
                    return size + ' B';
                }

                if (size < 1024 * 1024) {
                    return (size / 1024).toFixed(1) + ' KB';
                }

                return (size / (1024 * 1024)).toFixed(1) + ' MB';
            }

            function resizeTextarea() {
                if (!textarea) {
                    return;
                }

                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 220) + 'px';
            }

            function renderSelectedFiles() {
                if (!selectedFiles || !fileInput) {
                    return;
                }

                var files = Array.prototype.slice.call(fileInput.files || []);
                selectedFiles.innerHTML = '';

                if (files.length < 1) {
                    selectedFiles.classList.remove('has-files');
                    return;
                }

                selectedFiles.classList.add('has-files');

                files.forEach(function (file, index) {
                    var chip = document.createElement('div');
                    chip.className = 'admin-ticket-file-chip';
                    chip.innerHTML =
                        '<i class="fa fa-paperclip"></i>' +
                        '<span class="admin-ticket-file-chip-name"></span>' +
                        '<span class="admin-ticket-file-chip-meta"></span>' +
                        '<button type="button" class="admin-ticket-file-chip-remove" data-remove-file="' + index + '">' +
                            '<i class="fa fa-times"></i>' +
                            '<span class="sr-only">Remove file</span>' +
                        '</button>';

                    chip.querySelector('.admin-ticket-file-chip-name').textContent = file.name;
                    chip.querySelector('.admin-ticket-file-chip-meta').textContent = formatFileSize(file.size);
                    selectedFiles.appendChild(chip);
                });
            }

            if (transcript) {
                transcript.scrollTop = transcript.scrollHeight;
            }

            if (attachButton && fileInput) {
                attachButton.addEventListener('click', function () {
                    fileInput.click();
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', renderSelectedFiles);
            }

            if (selectedFiles && fileInput) {
                selectedFiles.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    var button = target.closest('[data-remove-file]');
                    if (!(button instanceof HTMLElement)) {
                        return;
                    }

                    var index = Number(button.getAttribute('data-remove-file'));
                    if (Number.isNaN(index)) {
                        return;
                    }

                    var files = Array.prototype.slice.call(fileInput.files || []);
                    var transfer = new DataTransfer();
                    files.forEach(function (file, fileIndex) {
                        if (fileIndex !== index) {
                            transfer.items.add(file);
                        }
                    });

                    fileInput.files = transfer.files;
                    renderSelectedFiles();
                });
            }

            if (textarea) {
                resizeTextarea();
                textarea.addEventListener('input', resizeTextarea);
                textarea.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
                        return;
                    }

                    event.preventDefault();

                    if (!form) {
                        return;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }

                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                });
            }
        })();
    </script>
@endsection
