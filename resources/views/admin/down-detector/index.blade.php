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
        .dd-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #C8BCA0; }
        .dd-title { font-size: 18px; font-weight: 900; color: #742220; margin: 0; }
        .dd-sub { font-size: 11px; color: rgba(116,34,32,.6); }
        .dd-info { position: relative; display: inline-flex; }
        .dd-info-btn { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #742220; color: #742220; background: #FEF9E1; font-size: 12px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: help; padding: 0; }
        .dd-info-tip { position: absolute; left: -10px; top: calc(100% + 10px); width: 340px; max-width: 80vw; z-index: 30; background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 12px 14px; font-size: 11px; line-height: 1.6; color: #4B3A2F; opacity: 0; visibility: hidden; transition: opacity .15s; pointer-events: none; }
        .dd-info-tip ul { margin: 6px 0 0; padding-left: 16px; }
        .dd-info:hover .dd-info-tip, .dd-info:focus-within .dd-info-tip { opacity: 1; visibility: visible; }
        .dd-head-actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .dd-head-actions form { margin: 0; }
        .dd-stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 18px; }
        .dd-stat { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 12px 14px; }
        .dd-stat span { display: block; font-size: 9px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: rgba(116,34,32,.6); }
        .dd-stat strong { display: block; margin-top: 4px; font-size: 20px; font-weight: 900; color: #4B3A2F; }
        .dd-stat small { font-size: 10px; color: rgba(116,34,32,.6); }
        .dd-stat.bad strong { color: #A03A2F; }
        .dd-stat.good strong { color: #2D6A4F; }
        .dd-card { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 16px 18px; margin-bottom: 18px; }
        .dd-card-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 900; color: #742220; margin: 0 0 12px; }
        .dd-card-title .material-icons-round { font-size: 18px; }
        .dd-card-title .dd-count { margin-left: auto; font-size: 10px; font-weight: 800; letter-spacing: .1em; color: rgba(116,34,32,.6); }
        .dd-card .form-group:last-child { margin-bottom: 0; }
        .dd-foot { display: flex; align-items: center; gap: 8px; justify-content: flex-end; border-top: 2px solid #C8BCA0; padding-top: 14px; margin-top: 6px; flex-wrap: wrap; }
        .dd-foot form { margin: 0; }
        .dd-foot .dd-left { margin-right: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .dd-list { display: flex; flex-direction: column; gap: 8px; }
        .dd-row { display: flex; gap: 12px; align-items: flex-start; background: #FEF9E1; border: 1px solid #C8BCA0; border-radius: 10px; padding: 10px 12px; }
        .dd-dot { width: 10px; height: 10px; border-radius: 999px; margin-top: 5px; flex-shrink: 0; background: #A03A2F; }
        .dd-dot.up { background: #2D6A4F; }
        .dd-row-main { flex: 1; min-width: 0; }
        .dd-row-name { font-size: 12px; font-weight: 800; color: #4B3A2F; word-break: break-word; }
        .dd-row-msg { font-size: 11px; color: rgba(116,34,32,.7); margin-top: 2px; word-break: break-word; }
        .dd-row-meta { font-size: 10px; color: rgba(116,34,32,.6); text-align: right; white-space: nowrap; }
        .dd-row-meta code { font-size: 10px; }
        .dd-empty { text-align: center; padding: 18px 12px; border: 2px dashed #C8BCA0; border-radius: 10px; font-size: 11px; color: rgba(116,34,32,.6); }
        .dd-grid2 { display: grid; gap: 18px; grid-template-columns: 1fr; }
        @media (min-width: 1200px) { .dd-grid2 { grid-template-columns: 1fr 1fr; } }
        .dd-ok { color: #2D6A4F; font-weight: 800; }
        .dd-no { color: #A03A2F; font-weight: 800; }
    </style>

    @php($yes = fn ($v) => $v ? '<span class="dd-ok">Ready</span>' : '<span class="dd-no">Not set</span>')

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="@if($activeTab === 'nodes') active @endif"><a href="{{ route('admin.down-detector', ['tab' => 'nodes']) }}">Nodes</a></li>
            <li class="@if($activeTab === 'servers') active @endif"><a href="{{ route('admin.down-detector', ['tab' => 'servers']) }}">Servers</a></li>
            <li class="@if($activeTab === 'settings') active @endif"><a href="{{ route('admin.down-detector', ['tab' => 'settings']) }}">Detector Settings</a></li>
        </ul>
    </div>

    <div class="dd-head">
        <h3 class="dd-title">{{ ['nodes' => 'Node monitoring', 'servers' => 'Server monitoring', 'settings' => 'Detector settings'][$activeTab] }}</h3>
        <span class="dd-info" tabindex="0">
            <button type="button" class="dd-info-btn" aria-label="How it works">!</button>
            <span class="dd-info-tip">
                Node monitoring sends admin alerts to a Discord channel and can post a periodic health summary. Server monitoring uses a separate alert channel, and linked Discord users can check their own servers from a private launcher embed (replies delete themselves after about a minute).
                <ul>
                    <li>Summaries only send when their interval is due.</li>
                    <li>Server alerts ignore install, suspend, transfer, starting and maintenance states.</li>
                    <li>The launcher reuses the existing Discord bridge; no extra bot process.</li>
                </ul>
            </span>
        </span>
        <span class="dd-sub">Last check {{ $meta['last_run_at_human'] }}</span>
    </div>

    <div class="dd-stats">
        <div class="dd-stat {{ $config['enabled'] ? 'good' : 'bad' }}"><span>Detector</span><strong>{{ $config['enabled'] ? 'On' : 'Off' }}</strong><small>every {{ $config['interval_seconds'] }}s</small></div>
        <div class="dd-stat {{ $summary['nodes']['down'] ? 'bad' : 'good' }}"><span>Nodes up</span><strong>{{ $summary['nodes']['up'] }} / {{ $summary['nodes']['total'] }}</strong><small>{{ $summary['nodes']['down'] }} down · {{ $summary['nodes']['unknown'] }} unknown</small></div>
        <div class="dd-stat {{ $summary['servers']['down'] ? 'bad' : 'good' }}"><span>Servers down</span><strong>{{ $summary['servers']['down'] }}</strong><small>{{ $summary['servers']['up'] }} up · {{ $summary['servers']['ignored'] }} ignored of {{ $summary['servers']['total'] }}</small></div>
        <div class="dd-stat {{ $meta['discord_bot_configured'] ? 'good' : 'bad' }}"><span>Discord bot</span><strong>{{ $meta['discord_bot_configured'] ? 'Ready' : 'Not set' }}</strong><small>used for alerts and the launcher</small></div>
    </div>

    @if($activeTab === 'nodes')
        <div class="dd-card">
            <div class="dd-card-title"><span class="material-icons-round">dns</span>Node alerts</div>
            <form method="POST" action="{{ route('admin.down-detector.update-nodes') }}" id="dd-node-form">
                @csrf
                @method('PATCH')
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label>Monitor nodes</label>
                        <select class="form-control" name="monitor_nodes">
                            <option value="1" @if($config['monitor_nodes']) selected @endif>Enabled</option>
                            <option value="0" @if(!$config['monitor_nodes']) selected @endif>Disabled</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-6">
                        <label>Alert channel ID</label>
                        <input type="text" class="form-control" name="node[discord][alert_channel_id]" value="{{ old('node.discord.alert_channel_id', data_get($config, 'node.discord.alert_channel_id')) }}" placeholder="123456789012345678">
                        <p class="text-muted small admin-help-tight">Up/down alerts and summaries post here. {!! $yes($meta['node_alert_ready']) !!}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label>Periodic summary</label>
                        <select class="form-control" name="node[periodic_reports_enabled]">
                            <option value="1" @if(data_get($config, 'node.periodic_reports_enabled')) selected @endif>Enabled</option>
                            <option value="0" @if(!data_get($config, 'node.periodic_reports_enabled')) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small admin-help-tight">Last sent {{ $meta['node_last_periodic_report_at_human'] }}</p>
                    </div>
                    <div class="form-group col-sm-6">
                        <label>Summary every (minutes)</label>
                        <input type="number" class="form-control" name="node[periodic_report_interval_minutes]" value="{{ old('node.periodic_report_interval_minutes', data_get($config, 'node.periodic_report_interval_minutes')) }}" min="60" max="10080">
                        <p class="text-muted small admin-help-tight"><code>1440</code> = once a day.</p>
                    </div>
                </div>
            </form>
            <div class="dd-foot">
                <div class="dd-left">
                    <form method="POST" action="{{ route('admin.down-detector.check', ['scope' => 'node']) }}">@csrf<button type="submit" class="btn btn-default btn-sm">Run check &amp; send report</button></form>
                </div>
                <button type="submit" form="dd-node-form" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>

        <div class="dd-grid2">
            @include('admin.down-detector.partials.lists', ['monitors' => $downMonitors['nodes'], 'incidents' => $recentIncidents['nodes'], 'kind' => 'node'])
        </div>
    @elseif($activeTab === 'servers')
        <div class="dd-card">
            <div class="dd-card-title"><span class="material-icons-round">storage</span>Server alerts &amp; launcher</div>
            <form method="POST" action="{{ route('admin.down-detector.update-servers') }}" id="dd-server-form">
                @csrf
                @method('PATCH')
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label>Monitor servers</label>
                        <select class="form-control" name="monitor_servers">
                            <option value="1" @if($config['monitor_servers']) selected @endif>Enabled</option>
                            <option value="0" @if(!$config['monitor_servers']) selected @endif>Disabled</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-6">
                        <label>Alert channel ID</label>
                        <input type="text" class="form-control" name="server[discord][alert_channel_id]" value="{{ old('server.discord.alert_channel_id', data_get($config, 'server.discord.alert_channel_id')) }}" placeholder="123456789012345678">
                        <p class="text-muted small admin-help-tight">Unexpected outage alerts post here. {!! $yes($meta['server_alert_ready']) !!}</p>
                    </div>
                    <div class="form-group col-sm-8">
                        <label>Launcher channel ID</label>
                        <input type="text" class="form-control" name="server[discord][launcher_channel_id]" value="{{ old('server.discord.launcher_channel_id', data_get($config, 'server.discord.launcher_channel_id')) }}" placeholder="channel for the user-facing launcher">
                        <p class="text-muted small admin-help-tight">Users click the launcher here to get their private server health. {!! $yes($meta['server_launcher_ready']) !!}</p>
                    </div>
                    <div class="form-group col-sm-4">
                        <label>Launcher message ID</label>
                        <input type="text" class="form-control" value="{{ data_get($config, 'server.discord.launcher_message_id') ?: 'Not synced yet' }}" readonly>
                    </div>
                </div>
                <div class="dd-card-title" style="margin-top:6px"><span class="material-icons-round">restart_alt</span>Auto restart</div>
                <div class="row">
                    <div class="form-group col-sm-3">
                        <label>Default for new servers</label>
                        <select class="form-control" name="server[auto_restart_default_enabled]">
                            <option value="1" @if(data_get($config, 'server.auto_restart_default_enabled')) selected @endif>Enabled</option>
                            <option value="0" @if(!data_get($config, 'server.auto_restart_default_enabled')) selected @endif>Disabled</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-3">
                        <label>Delay (seconds)</label>
                        <input type="number" class="form-control" min="10" max="600" name="server[auto_restart_delay_seconds]" value="{{ old('server.auto_restart_delay_seconds', data_get($config, 'server.auto_restart_delay_seconds', 30)) }}">
                    </div>
                    <div class="form-group col-sm-3">
                        <label>Max restarts</label>
                        <input type="number" class="form-control" min="1" max="20" name="server[auto_restart_max_attempts]" value="{{ old('server.auto_restart_max_attempts', data_get($config, 'server.auto_restart_max_attempts', 3)) }}">
                    </div>
                    <div class="form-group col-sm-3">
                        <label>Within (minutes)</label>
                        <input type="number" class="form-control" min="1" max="1440" name="server[auto_restart_window_minutes]" value="{{ old('server.auto_restart_window_minutes', data_get($config, 'server.auto_restart_window_minutes', 15)) }}">
                    </div>
                </div>
                <p class="text-muted small">Existing servers keep their own auto-restart setting.</p>
            </form>
            <div class="dd-foot">
                <div class="dd-left">
                    <form method="POST" action="{{ route('admin.down-detector.check', ['scope' => 'server']) }}">@csrf<button type="submit" class="btn btn-default btn-sm">Run check &amp; send report</button></form>
                    <form method="POST" action="{{ route('admin.down-detector.sync-server-launcher') }}">@csrf<button type="submit" class="btn btn-default btn-sm">Sync launcher</button></form>
                </div>
                <button type="submit" form="dd-server-form" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>

        <div class="dd-grid2">
            @include('admin.down-detector.partials.lists', ['monitors' => $downMonitors['servers'], 'incidents' => $recentIncidents['servers'], 'kind' => 'server'])
        </div>
    @else
        <div class="dd-card">
            <div class="dd-card-title"><span class="material-icons-round">tune</span>Core runtime</div>
            <form method="POST" action="{{ route('admin.down-detector.update') }}" id="dd-core-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="tab" value="settings">
                <div class="row">
                    <div class="form-group col-sm-6">
                        <label>Detector</label>
                        <select class="form-control" name="enabled">
                            <option value="1" @if($config['enabled']) selected @endif>Enabled</option>
                            <option value="0" @if(!$config['enabled']) selected @endif>Disabled</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-6">
                        <label>Check every (seconds)</label>
                        <input type="number" class="form-control" name="interval_seconds" value="{{ old('interval_seconds', $config['interval_seconds']) }}" min="60" max="3600">
                    </div>
                    <div class="form-group col-sm-6">
                        <label>TCP / Wings timeout (ms)</label>
                        <input type="number" class="form-control" name="probe_timeout_ms" value="{{ old('probe_timeout_ms', $config['probe_timeout_ms']) }}" min="1000" max="30000">
                    </div>
                    <div class="form-group col-sm-3">
                        <label>Fail after</label>
                        <input type="number" class="form-control" name="failure_threshold" value="{{ old('failure_threshold', $config['failure_threshold']) }}" min="1" max="10">
                        <p class="text-muted small admin-help-tight">failed checks</p>
                    </div>
                    <div class="form-group col-sm-3">
                        <label>Recover after</label>
                        <input type="number" class="form-control" name="recovery_threshold" value="{{ old('recovery_threshold', $config['recovery_threshold']) }}" min="1" max="10">
                        <p class="text-muted small admin-help-tight">good checks</p>
                    </div>
                </div>
            </form>
            <div class="dd-foot">
                <button type="submit" form="dd-core-form" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    @endif
@endsection
