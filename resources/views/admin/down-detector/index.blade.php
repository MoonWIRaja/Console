@extends('layouts.admin')

@section('title')
    Down Detector
@endsection

@section('content-header')
    <h1>Down Detector<small>Separate node and server monitoring, Discord alerts, and a user-facing server status launcher.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Down Detector</li>
    </ol>
@endsection

@php($activeTab = $meta['selected_tab'] ?? 'nodes')

@section('content')
    <style>
        .down-detector-admin .callout.callout-info {
            border-left: 4px solid var(--admin-primary) !important;
            border-radius: 14px !important;
            background:
                linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.14), rgba(var(--admin-primary-rgb), 0.05))
                rgba(var(--admin-card-rgb), 0.9) !important;
            color: var(--admin-foreground) !important;
            box-shadow: 0 18px 34px -26px rgba(0, 0, 0, 0.85);
        }

        .down-detector-admin .callout.callout-info p,
        .down-detector-admin .callout.callout-info h4 {
            color: inherit !important;
        }

        .down-detector-admin .box {
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-top-width: 1px !important;
            border-radius: 14px !important;
            overflow: hidden;
            background: rgba(var(--admin-card-rgb), 0.92) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.9);
        }

        .down-detector-admin .box .box-header,
        .down-detector-admin .box .box-body,
        .down-detector-admin .box .box-footer {
            background: transparent !important;
            color: var(--admin-foreground) !important;
        }

        .down-detector-admin .box .box-header.with-border,
        .down-detector-admin .box .box-footer {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .down-detector-admin .box .box-title,
        .down-detector-admin label,
        .down-detector-admin .table > thead > tr > th,
        .down-detector-admin strong {
            color: var(--admin-foreground) !important;
        }

        .down-detector-admin .text-muted,
        .down-detector-admin .help-block,
        .down-detector-admin .small {
            color: var(--admin-muted-foreground) !important;
        }

        .down-detector-admin .box.box-danger {
            border-color: rgba(255, 96, 96, 0.22) !important;
            box-shadow: 0 20px 36px -30px rgba(120, 12, 12, 0.72);
        }

        .down-detector-admin .box.box-danger .box-header {
            background: linear-gradient(180deg, rgba(120, 18, 18, 0.22), rgba(120, 18, 18, 0.08)) !important;
        }

        .down-detector-admin .box.box-default {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .down-detector-admin .box.box-primary .box-header {
            background: linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.16), rgba(var(--admin-primary-rgb), 0.05)) !important;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs {
            display: flex;
            gap: 10px;
            margin: 0 0 16px !important;
            padding: 8px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.88) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li {
            float: none !important;
            margin: 0 !important;
            border-top: 0 !important;
            border-radius: 10px !important;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li > a {
            margin: 0 !important;
            border: 1px solid transparent !important;
            border-radius: 10px !important;
            padding: 11px 16px !important;
            font-weight: 600;
            color: var(--admin-muted-foreground) !important;
            transition: all 0.18s ease;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li > a:hover {
            background: rgba(var(--admin-primary-rgb), 0.08) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.14) !important;
            color: var(--admin-foreground) !important;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li.active {
            border-top-color: transparent !important;
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li.active > a,
        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs > li.active:hover > a {
            background:
                linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.22), rgba(var(--admin-primary-rgb), 0.08))
                rgba(var(--admin-card-rgb), 0.98) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.24) !important;
            color: var(--admin-primary) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02), 0 10px 22px -18px rgba(var(--admin-primary-rgb), 0.9);
        }

        .down-detector-admin .nav-tabs-custom.down-detector-tabs > .tab-content {
            padding: 20px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.9) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .down-detector-admin .form-control {
            border-radius: 10px !important;
            border-color: rgba(var(--admin-primary-rgb), 0.18) !important;
            background: rgba(var(--admin-card-rgb), 0.7) !important;
            color: var(--admin-foreground) !important;
            box-shadow: none !important;
        }

        .down-detector-admin .form-control:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.42) !important;
            box-shadow: 0 0 0 3px rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .down-detector-admin .form-control[readonly] {
            background: rgba(var(--admin-card-rgb), 0.58) !important;
            color: var(--admin-muted-foreground) !important;
        }

        .down-detector-admin .btn {
            border-radius: 10px !important;
            font-weight: 600;
            box-shadow: none !important;
        }

        .down-detector-admin .btn-primary {
            border-color: rgba(var(--admin-primary-rgb), 0.4) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.95), rgba(var(--admin-primary-rgb), 0.72)) !important;
            color: #081105 !important;
        }

        .down-detector-admin .btn-primary:hover,
        .down-detector-admin .btn-primary:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.5) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 1), rgba(var(--admin-primary-rgb), 0.82)) !important;
            color: #081105 !important;
        }

        .down-detector-admin .btn-info {
            border-color: rgba(var(--admin-primary-rgb), 0.22) !important;
            background: rgba(var(--admin-primary-rgb), 0.14) !important;
            color: var(--admin-primary) !important;
        }

        .down-detector-admin .btn-info:hover,
        .down-detector-admin .btn-info:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.32) !important;
            background: rgba(var(--admin-primary-rgb), 0.2) !important;
            color: var(--admin-primary) !important;
        }

        .down-detector-admin .btn-default {
            border-color: rgba(var(--admin-primary-rgb), 0.18) !important;
            background: rgba(var(--admin-card-rgb), 0.8) !important;
            color: var(--admin-foreground) !important;
        }

        .down-detector-admin .btn-default:hover,
        .down-detector-admin .btn-default:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.26) !important;
            background: rgba(var(--admin-primary-rgb), 0.08) !important;
            color: var(--admin-foreground) !important;
        }

        .down-detector-admin .table > thead > tr > th,
        .down-detector-admin .table > tbody > tr > td {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .down-detector-admin .table-hover > tbody > tr:hover {
            background: rgba(var(--admin-primary-rgb), 0.06) !important;
        }

        .down-detector-admin hr {
            border-color: rgba(var(--admin-primary-rgb), 0.12);
        }

        .down-detector-admin .down-detector-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .down-detector-admin .down-detector-actions form {
            margin: 0 !important;
        }

        @media (max-width: 767px) {
            .down-detector-admin .nav-tabs-custom.down-detector-tabs > .nav-tabs {
                flex-direction: column;
            }

            .down-detector-admin .down-detector-actions {
                flex-direction: column;
            }
        }
    </style>

    <div class="row down-detector-admin">
        <div class="col-md-8">
            <div class="callout callout-info">
                <h4 style="margin-top: 0;">How it works now</h4>
                <p style="margin-bottom: 8px;">
                    Node monitoring sends admin alerts to a dedicated Discord channel and can also post periodic health summaries on a schedule you control.
                    Server monitoring uses a separate alert channel, while linked Discord users can check their own server health through a private launcher embed.
                </p>
                <p style="margin-bottom: 0;">
                    Server status replies are ephemeral and the bridge now deletes them automatically after about one minute.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.down-detector.update') }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Core Runtime</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Enable Detector</label>
                                    <select class="form-control" name="enabled">
                                        <option value="1" @if($config['enabled']) selected @endif>Enabled</option>
                                        <option value="0" @if(!$config['enabled']) selected @endif>Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>Check Interval Seconds</label>
                                    <input type="number" class="form-control" name="interval_seconds" value="{{ old('interval_seconds', $config['interval_seconds']) }}" min="60" max="3600">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label>TCP / Wings Timeout (ms)</label>
                                    <input type="number" class="form-control" name="probe_timeout_ms" value="{{ old('probe_timeout_ms', $config['probe_timeout_ms']) }}" min="1000" max="30000">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Failure Threshold</label>
                                    <input type="number" class="form-control" name="failure_threshold" value="{{ old('failure_threshold', $config['failure_threshold']) }}" min="1" max="10">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Recovery Threshold</label>
                                    <input type="number" class="form-control" name="recovery_threshold" value="{{ old('recovery_threshold', $config['recovery_threshold']) }}" min="1" max="10">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Save Core Runtime</button>
                    </div>
                </div>
            </form>

            <div class="nav-tabs-custom nav-tabs-floating down-detector-tabs">
                <ul class="nav nav-tabs">
                    <li class="@if($activeTab === 'nodes') active @endif">
                        <a href="{{ route('admin.down-detector', ['tab' => 'nodes']) }}">Node Setup</a>
                    </li>
                    <li class="@if($activeTab === 'servers') active @endif">
                        <a href="{{ route('admin.down-detector', ['tab' => 'servers']) }}">Server Setup</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane @if($activeTab === 'nodes') active @endif" id="down-detector-nodes">
                        <form method="POST" action="{{ route('admin.down-detector.update-nodes') }}">
                            @csrf
                            @method('PATCH')

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Monitor Nodes</label>
                                        <select class="form-control" name="monitor_nodes">
                                            <option value="1" @if($config['monitor_nodes']) selected @endif>Enabled</option>
                                            <option value="0" @if(!$config['monitor_nodes']) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Node Alert Channel ID</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="node[discord][alert_channel_id]"
                                            value="{{ old('node.discord.alert_channel_id', data_get($config, 'node.discord.alert_channel_id')) }}"
                                            placeholder="123456789012345678"
                                        >
                                        <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                            Transition alerts and scheduled node health summaries are sent to this channel as public embeds.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Periodic Node Summary</label>
                                        <select class="form-control" name="node[periodic_reports_enabled]">
                                            <option value="1" @if(data_get($config, 'node.periodic_reports_enabled')) selected @endif>Enabled</option>
                                            <option value="0" @if(!data_get($config, 'node.periodic_reports_enabled')) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Summary Interval (minutes)</label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="node[periodic_report_interval_minutes]"
                                            value="{{ old('node.periodic_report_interval_minutes', data_get($config, 'node.periodic_report_interval_minutes')) }}"
                                            min="60"
                                            max="10080"
                                        >
                                        <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                            Example: <code>1440</code> sends one node summary every day.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" class="btn btn-primary">Save Node Setup</button>
                            </div>
                        </form>

                        <div class="down-detector-actions">
                            <form method="POST" action="{{ route('admin.down-detector.check', ['scope' => 'node']) }}">
                                @csrf
                                <button type="submit" class="btn btn-info">Run Node Check &amp; Send Report</button>
                            </form>
                        </div>

                        <hr>

                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title">Current Node Outages</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Reason</th>
                                        <th>Last Change</th>
                                        <th>Last Check</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($downMonitors['nodes'] as $monitor)
                                        <tr>
                                            <td>
                                                <strong>{{ $monitor['name'] }}</strong><br>
                                                <span class="text-muted small">{{ $monitor['message'] }}</span>
                                            </td>
                                            <td><code>{{ $monitor['reason'] ?: 'n/a' }}</code></td>
                                            <td>{{ $monitor['changed_at'] }}</td>
                                            <td>{{ $monitor['checked_at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No active node outages detected.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="box box-default" style="margin-bottom: 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title">Recent Node Incidents</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Target</th>
                                        <th>Transition</th>
                                        <th>Reason</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($recentIncidents['nodes'] as $incident)
                                        <tr>
                                            <td>{{ $incident['created_at'] }}</td>
                                            <td>
                                                <strong>{{ $incident['name'] }}</strong><br>
                                                <span class="text-muted small">{{ $incident['summary'] }}</span>
                                            </td>
                                            <td><code>{{ $incident['from_status'] }}</code> → <code>{{ $incident['to_status'] }}</code></td>
                                            <td><code>{{ $incident['reason'] ?: 'n/a' }}</code></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No node incidents recorded yet.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane @if($activeTab === 'servers') active @endif" id="down-detector-servers">
                        <form method="POST" action="{{ route('admin.down-detector.update-servers') }}">
                            @csrf
                            @method('PATCH')

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Monitor Servers</label>
                                        <select class="form-control" name="monitor_servers">
                                            <option value="1" @if($config['monitor_servers']) selected @endif>Enabled</option>
                                            <option value="0" @if(!$config['monitor_servers']) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>Server Alert Channel ID</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="server[discord][alert_channel_id]"
                                            value="{{ old('server.discord.alert_channel_id', data_get($config, 'server.discord.alert_channel_id')) }}"
                                            placeholder="123456789012345678"
                                        >
                                        <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                            Unexpected server outage alerts are sent to this channel.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-8">
                                    <div class="form-group">
                                        <label>Server Status Launcher Channel ID</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="server[discord][launcher_channel_id]"
                                            value="{{ old('server.discord.launcher_channel_id', data_get($config, 'server.discord.launcher_channel_id')) }}"
                                            placeholder="channel id for the user-facing launcher embed"
                                        >
                                        <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                            The bot posts a public launcher embed here. Users click it and get their private server health reply.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Launcher Message ID</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            value="{{ data_get($config, 'server.discord.launcher_message_id') ?: 'Not synced yet' }}"
                                            readonly
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Auto Restart Default</label>
                                        <select class="form-control" name="server[auto_restart_default_enabled]">
                                            <option value="1" @if(data_get($config, 'server.auto_restart_default_enabled')) selected @endif>Enabled</option>
                                            <option value="0" @if(!data_get($config, 'server.auto_restart_default_enabled')) selected @endif>Disabled</option>
                                        </select>
                                        <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                            New servers inherit this default. Existing servers keep their current value.
                                        </p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Restart Delay Seconds</label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            min="10"
                                            max="600"
                                            name="server[auto_restart_delay_seconds]"
                                            value="{{ old('server.auto_restart_delay_seconds', data_get($config, 'server.auto_restart_delay_seconds', 30)) }}"
                                        >
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Max Auto Restarts</label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            max="20"
                                            name="server[auto_restart_max_attempts]"
                                            value="{{ old('server.auto_restart_max_attempts', data_get($config, 'server.auto_restart_max_attempts', 3)) }}"
                                        >
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label>Retry Window Minutes</label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            min="1"
                                            max="1440"
                                            name="server[auto_restart_window_minutes]"
                                            value="{{ old('server.auto_restart_window_minutes', data_get($config, 'server.auto_restart_window_minutes', 15)) }}"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" class="btn btn-primary">Save Server Setup</button>
                            </div>
                        </form>

                        <div class="down-detector-actions">
                            <form method="POST" action="{{ route('admin.down-detector.check', ['scope' => 'server']) }}">
                                @csrf
                                <button type="submit" class="btn btn-info">Run Server Check &amp; Send Report</button>
                            </form>

                            <form method="POST" action="{{ route('admin.down-detector.sync-server-launcher') }}">
                                @csrf
                                <button type="submit" class="btn btn-default">Sync Server Health Launcher</button>
                            </form>
                        </div>

                        <hr>

                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title">Current Server Outages</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Reason</th>
                                        <th>Last Change</th>
                                        <th>Last Check</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($downMonitors['servers'] as $monitor)
                                        <tr>
                                            <td>
                                                <strong>{{ $monitor['name'] }}</strong><br>
                                                <span class="text-muted small">{{ $monitor['message'] }}</span>
                                            </td>
                                            <td><code>{{ $monitor['reason'] ?: 'n/a' }}</code></td>
                                            <td>{{ $monitor['changed_at'] }}</td>
                                            <td>{{ $monitor['checked_at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No active server outages detected.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="box box-default" style="margin-bottom: 0;">
                            <div class="box-header with-border">
                                <h3 class="box-title">Recent Server Incidents</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Target</th>
                                        <th>Transition</th>
                                        <th>Reason</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($recentIncidents['servers'] as $incident)
                                        <tr>
                                            <td>{{ $incident['created_at'] }}</td>
                                            <td>
                                                <strong>{{ $incident['name'] }}</strong><br>
                                                <span class="text-muted small">{{ $incident['summary'] }}</span>
                                            </td>
                                            <td><code>{{ $incident['from_status'] }}</code> → <code>{{ $incident['to_status'] }}</code></td>
                                            <td><code>{{ $incident['reason'] ?: 'n/a' }}</code></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No server incidents recorded yet.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Runtime Summary</h3>
                </div>
                <div class="box-body">
                    <p><strong>Last scheduled run:</strong> {{ $meta['last_run_at_human'] }}</p>
                    <p><strong>Discord bot configured:</strong> {{ $meta['discord_bot_configured'] ? 'Yes' : 'No' }}</p>
                    <p><strong>Node alert channel ready:</strong> {{ $meta['node_alert_ready'] ? 'Yes' : 'No' }}</p>
                    <p><strong>Server alert channel ready:</strong> {{ $meta['server_alert_ready'] ? 'Yes' : 'No' }}</p>
                    <p><strong>Server launcher ready:</strong> {{ $meta['server_launcher_ready'] ? 'Yes' : 'No' }}</p>
                    <p><strong>Last node periodic summary:</strong> {{ $meta['node_last_periodic_report_at_human'] }}</p>
                    <hr>
                    <p><strong>Nodes</strong></p>
                    <ul class="list-unstyled" style="margin-bottom: 12px;">
                        <li>Total: {{ $summary['nodes']['total'] }}</li>
                        <li>Up: {{ $summary['nodes']['up'] }}</li>
                        <li>Down: {{ $summary['nodes']['down'] }}</li>
                        <li>Unknown: {{ $summary['nodes']['unknown'] }}</li>
                    </ul>

                    <p><strong>Servers</strong></p>
                    <ul class="list-unstyled" style="margin-bottom: 0;">
                        <li>Total: {{ $summary['servers']['total'] }}</li>
                        <li>Up: {{ $summary['servers']['up'] }}</li>
                        <li>Down: {{ $summary['servers']['down'] }}</li>
                        <li>Ignored: {{ $summary['servers']['ignored'] }}</li>
                        <li>Unknown: {{ $summary['servers']['unknown'] }}</li>
                    </ul>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Notes</h3>
                </div>
                <div class="box-body">
                    <ul class="text-muted" style="padding-left: 18px; margin-bottom: 0;">
                        <li>Node summaries use the same scheduler loop, but they only send when the configured periodic interval is due.</li>
                        <li>Server alerts still ignore install, suspend, transfer, starting, and maintenance states.</li>
                        <li>The server launcher reuses the existing Discord bridge. No second bot process is introduced.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
