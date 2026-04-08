@extends('layouts.admin')

@section('title')
    System Logs
@endsection

@section('content-header')
    <h1>System Logs<small>Review auth, payment, ticket, and security activity with dedicated Discord channel routing.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Logs</li>
    </ol>
@endsection

@section('content')
    @php($activeHeading = $tabs[$activeTab] ?? 'Log Entries')

    <style>
        .log-center-admin .box {
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-top-width: 1px !important;
            border-radius: 14px !important;
            overflow: hidden;
            background: rgba(var(--admin-card-rgb), 0.92) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.88);
        }

        .log-center-admin .box .box-header,
        .log-center-admin .box .box-body,
        .log-center-admin .box .box-footer {
            background: transparent !important;
            color: var(--admin-foreground) !important;
        }

        .log-center-admin .box .box-header.with-border,
        .log-center-admin .box .box-footer {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .log-center-admin .box.box-primary .box-header,
        .log-center-admin .box.box-default .box-header {
            background: linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.16), rgba(var(--admin-primary-rgb), 0.05)) !important;
        }

        .log-center-admin .box .box-title,
        .log-center-admin label,
        .log-center-admin .table > thead > tr > th,
        .log-center-admin strong {
            color: var(--admin-foreground) !important;
        }

        .log-center-admin .text-muted,
        .log-center-admin .small,
        .log-center-admin .help-block {
            color: var(--admin-muted-foreground) !important;
        }

        .log-center-admin .form-control {
            border-radius: 10px !important;
            border-color: rgba(var(--admin-primary-rgb), 0.18) !important;
            background: rgba(var(--admin-card-rgb), 0.72) !important;
            color: var(--admin-foreground) !important;
            box-shadow: none !important;
        }

        .log-center-admin .form-control:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.42) !important;
            box-shadow: 0 0 0 3px rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .log-center-admin .btn {
            border-radius: 10px !important;
            font-weight: 600;
            box-shadow: none !important;
        }

        .log-center-admin .btn-primary {
            border-color: rgba(var(--admin-primary-rgb), 0.38) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.95), rgba(var(--admin-primary-rgb), 0.72)) !important;
            color: #081105 !important;
        }

        .log-center-admin .btn-primary:hover,
        .log-center-admin .btn-primary:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.48) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 1), rgba(var(--admin-primary-rgb), 0.82)) !important;
            color: #081105 !important;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 16px !important;
            padding: 8px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.88) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li {
            float: none !important;
            margin: 0 !important;
            border-top: 0 !important;
            border-radius: 10px !important;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li > a {
            margin: 0 !important;
            border: 1px solid transparent !important;
            border-radius: 10px !important;
            padding: 11px 15px !important;
            font-weight: 600;
            color: var(--admin-muted-foreground) !important;
            transition: all 0.18s ease;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li > a:hover {
            background: rgba(var(--admin-primary-rgb), 0.08) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.14) !important;
            color: var(--admin-foreground) !important;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li.active {
            border-top-color: transparent !important;
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li.active > a,
        .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs > li.active:hover > a {
            background:
                linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.22), rgba(var(--admin-primary-rgb), 0.08))
                rgba(var(--admin-card-rgb), 0.98) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.24) !important;
            color: var(--admin-primary) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02), 0 10px 22px -18px rgba(var(--admin-primary-rgb), 0.9);
        }

        .log-center-admin .nav-tabs-custom.log-center-tabs > .tab-content {
            padding: 20px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.9) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .log-center-admin .table-responsive {
            border: 0 !important;
        }

        .log-center-admin .table {
            margin-bottom: 0 !important;
        }

        .log-center-admin .table > thead > tr > th,
        .log-center-admin .table > tbody > tr > td {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .log-center-admin .table > thead > tr > th {
            background: rgba(var(--admin-primary-rgb), 0.06) !important;
        }

        .log-center-admin .table-hover > tbody > tr:hover {
            background: rgba(var(--admin-primary-rgb), 0.06) !important;
        }

        .log-center-admin code {
            background: rgba(var(--admin-primary-rgb), 0.12) !important;
            color: var(--admin-primary) !important;
            border-radius: 8px;
        }

        @media (max-width: 991px) {
            .log-center-admin .nav-tabs-custom.log-center-tabs > .nav-tabs {
                flex-direction: column;
            }
        }
    </style>

    <div class="row log-center-admin">
        <div class="col-md-4">
            <form method="POST" action="{{ route('admin.logs.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Discord Channels</h3>
                    </div>
                    <div class="box-body">
                        @foreach([
                            'admin_logs:new_account:discord_channel_id' => 'New Account',
                            'admin_logs:payment:discord_channel_id' => 'Payment',
                            'admin_logs:security:discord_channel_id' => 'Security',
                            'admin_logs:login:discord_channel_id' => 'Login',
                            'admin_logs:forgot_password:discord_channel_id' => 'Forget Password',
                            'admin_logs:change_password:discord_channel_id' => 'Change Password',
                            'admin_logs:change_email:discord_channel_id' => 'Change Email',
                            'admin_logs:ticket:discord_channel_id' => 'Ticket',
                        ] as $key => $label)
                            <div class="form-group">
                                <label class="control-label">{{ $label }} Channel ID</label>
                                <input type="text" class="form-control" name="{{ $key }}" value="{{ old($key, $channelSettings[$key] ?? '') }}" placeholder="123456789012345678">
                            </div>
                        @endforeach
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Save Log Channels</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-8">
            <div class="nav-tabs-custom log-center-tabs">
                <ul class="nav nav-tabs">
                    @foreach($tabs as $key => $label)
                        <li class="@if($activeTab === $key) active @endif">
                            <a href="{{ route('admin.logs', ['tab' => $key]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @if($activeTab === 'new-account')
                        <div class="box box-default" style="margin-bottom: 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title">{{ $activeHeading }}</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Created</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Signup IP</th>
                                        <th>Verified</th>
                                        <th>Verification</th>
                                        <th>OAuth</th>
                                        <th>Last Seen</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($payload['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['created_at'] }}</td>
                                            <td>{{ $row['username'] }}</td>
                                            <td>{{ $row['email'] }}</td>
                                            <td>{{ $row['name'] }}</td>
                                            <td>{{ $row['signup_ip'] }}</td>
                                            <td>{{ $row['email_verified'] ? 'Yes' : 'No' }}</td>
                                            <td>{{ $row['verification_status'] }}</td>
                                            <td>{{ $row['oauth_providers'] ? implode(', ', $row['oauth_providers']) : 'None' }}</td>
                                            <td>{{ $row['last_seen_at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center text-muted">No accounts found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($activeTab === 'payment')
                        @foreach([
                            'invoices' => 'Recent Invoices',
                            'attempts' => 'Recent Payment Attempts',
                            'orders' => 'Recent Provisioning Orders',
                            'gateway_events' => 'Recent Gateway Events',
                        ] as $section => $heading)
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">{{ $heading }}</h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover">
                                        <thead>
                                        @if($section === 'invoices')
                                            <tr><th>Created</th><th>Invoice</th><th>User</th><th>Type</th><th>Status</th><th>Provider</th><th>Amount</th><th>Order</th></tr>
                                        @elseif($section === 'attempts')
                                            <tr><th>Created</th><th>Invoice</th><th>User</th><th>Provider</th><th>Status</th><th>Reference</th><th>Failure</th></tr>
                                        @elseif($section === 'orders')
                                            <tr><th>Updated</th><th>User</th><th>Server</th><th>Type</th><th>Status</th><th>Approver</th><th>Failure</th></tr>
                                        @else
                                            <tr><th>Created</th><th>Provider</th><th>Event Type</th><th>Status</th><th>Transaction</th><th>Error</th></tr>
                                        @endif
                                        </thead>
                                        <tbody>
                                        @forelse($payload[$section] as $row)
                                            <tr>
                                                @foreach($row as $value)
                                                    <td>{{ $value }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="8" class="text-center text-muted">No entries found.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @elseif($activeTab === 'security')
                        <div class="box box-default" style="margin-bottom: 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title">{{ $activeHeading }}</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Threat</th>
                                        <th>Outcome</th>
                                        <th>Source</th>
                                        <th>Target</th>
                                        <th>Details</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($payload['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['timestamp'] }}</td>
                                            <td>
                                                <strong>{{ $row['attack'] }}</strong>
                                                <div class="small text-muted"><code>{{ $row['event'] }}</code></div>
                                            </td>
                                            <td>{{ $row['outcome'] }}</td>
                                            <td>{{ $row['source'] }}</td>
                                            <td>{{ $row['target'] }}</td>
                                            <td>{{ $row['details'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No log entries found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif(in_array($activeTab, ['login', 'forgot-password', 'change-password', 'change-email'], true))
                        <div class="box box-default" style="margin-bottom: 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title">{{ $activeHeading }}</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Event</th>
                                        <th>Actor</th>
                                        <th>Subject</th>
                                        <th>IP</th>
                                        <th>Context</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($payload['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['timestamp'] }}</td>
                                            <td><code>{{ $row['event'] }}</code></td>
                                            <td>{{ $row['actor'] }}</td>
                                            <td>{{ $row['subject'] }}</td>
                                            <td>{{ $row['ip'] }}</td>
                                            <td>{{ $row['context'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No log entries found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @elseif($activeTab === 'ticket')
                        @foreach([
                            'tickets' => 'Recent Tickets',
                            'messages' => 'Recent Ticket Messages',
                            'activity' => 'Ticket Activity',
                        ] as $section => $heading)
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">{{ $heading }}</h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover">
                                        <thead>
                                        @if($section === 'tickets')
                                            <tr><th>Updated</th><th>Ticket</th><th>User</th><th>Category</th><th>Status</th><th>Subject</th><th>Assigned Admin</th></tr>
                                        @elseif($section === 'messages')
                                            <tr><th>Created</th><th>Ticket</th><th>User</th><th>Author</th><th>Type</th><th>Origin</th><th>Body</th></tr>
                                        @else
                                            <tr><th>Time</th><th>Event</th><th>Actor</th><th>Subject</th><th>IP</th><th>Context</th></tr>
                                        @endif
                                        </thead>
                                        <tbody>
                                        @forelse($payload[$section] as $row)
                                            <tr>
                                                @foreach($row as $value)
                                                    <td>{{ $value }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted">No ticket entries found.</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
