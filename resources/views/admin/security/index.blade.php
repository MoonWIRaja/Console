@extends('layouts.admin')

@section('title')
    Security Center
@endsection

@section('content-header')
    <h1>Security Center<small>Custom control plane for threat rules, verdicts, agent heartbeats, quarantine, and runtime response policy.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Security</li>
    </ol>
@endsection

@section('content')
    <style>
        .security-center-admin .box {
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-top-width: 1px !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.92) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.88);
            overflow: hidden;
        }

        .security-center-admin .box .box-header,
        .security-center-admin .box .box-body,
        .security-center-admin .box .box-footer {
            background: transparent !important;
            color: var(--admin-foreground) !important;
        }

        .security-center-admin .box .box-header.with-border,
        .security-center-admin .box .box-footer {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .security-center-admin .box .box-title,
        .security-center-admin label,
        .security-center-admin strong,
        .security-center-admin .table > thead > tr > th {
            color: var(--admin-foreground) !important;
        }

        .security-center-admin .text-muted,
        .security-center-admin .small,
        .security-center-admin .help-block {
            color: var(--admin-muted-foreground) !important;
        }

        .security-center-admin .form-control {
            border-radius: 10px !important;
            border-color: rgba(var(--admin-primary-rgb), 0.18) !important;
            background: rgba(var(--admin-card-rgb), 0.72) !important;
            color: var(--admin-foreground) !important;
            box-shadow: none !important;
        }

        .security-center-admin .form-control:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.42) !important;
            box-shadow: 0 0 0 3px rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .security-center-admin .form-control[readonly] {
            background: rgba(var(--admin-card-rgb), 0.58) !important;
            color: var(--admin-muted-foreground) !important;
        }

        .security-center-admin select.form-control option {
            background: rgb(var(--admin-card-rgb));
            color: var(--admin-foreground);
        }

        .security-center-admin .btn {
            border-radius: 10px !important;
            font-weight: 700;
        }

        .security-center-admin .btn-primary {
            border-color: rgba(var(--admin-primary-rgb), 0.38) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.95), rgba(var(--admin-primary-rgb), 0.72)) !important;
            color: #081105 !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 0 0 16px !important;
            padding: 8px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.88) !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs > li {
            float: none !important;
            margin: 0 !important;
            border-top: 0 !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs > li > a {
            margin: 0 !important;
            border: 1px solid transparent !important;
            border-radius: 10px !important;
            padding: 11px 15px !important;
            font-weight: 700;
            color: var(--admin-muted-foreground) !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs > li.active > a,
        .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs > li.active:hover > a {
            background: rgba(var(--admin-primary-rgb), 0.14) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.24) !important;
            color: var(--admin-primary) !important;
        }

        .security-center-admin .nav-tabs-custom.security-tabs > .tab-content {
            padding: 20px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background:
                linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.05), rgba(var(--admin-primary-rgb), 0.01) 26%),
                rgba(var(--admin-card-rgb), 0.92) !important;
            color: var(--admin-foreground) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .security-center-admin .metric-card {
            padding: 18px;
            min-height: 150px;
        }

        .security-center-admin .metric-card .eyebrow {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(var(--admin-primary-rgb), 0.12);
            color: var(--admin-primary);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .security-center-admin .metric-card .value {
            margin-top: 18px;
            font-size: 34px;
            line-height: 1;
            font-weight: 800;
            color: var(--admin-foreground);
        }

        .security-center-admin .metric-card .copy {
            margin-top: 10px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--admin-muted-foreground);
        }

        .security-center-admin .table-responsive {
            border: 0 !important;
        }

        .security-center-admin .table {
            margin-bottom: 0 !important;
        }

        .security-center-admin .table > thead > tr > th,
        .security-center-admin .table > tbody > tr > td {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .security-center-admin .table > thead > tr > th {
            background: rgba(var(--admin-primary-rgb), 0.06) !important;
        }

        .security-center-admin .table-hover > tbody > tr:hover {
            background: rgba(var(--admin-primary-rgb), 0.06) !important;
        }

        .security-center-admin .box.box-primary .box-header,
        .security-center-admin .box.box-default .box-header {
            background: linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.16), rgba(var(--admin-primary-rgb), 0.05)) !important;
        }

        .security-center-admin .security-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .security-center-admin .security-badge.verdict-blocked,
        .security-center-admin .security-badge.status-contained,
        .security-center-admin .security-badge.status-isolated {
            background: rgba(224, 74, 74, 0.16);
            color: #ff9292;
        }

        .security-center-admin .security-badge.verdict-quarantined {
            background: rgba(255, 183, 77, 0.16);
            color: #ffca72;
        }

        .security-center-admin .security-badge.verdict-rate_limited,
        .security-center-admin .security-badge.verdict-contained,
        .security-center-admin .security-badge.status-monitoring,
        .security-center-admin .security-badge.status-dispatched {
            background: rgba(90, 190, 255, 0.14);
            color: #8fd6ff;
        }

        .security-center-admin .security-badge.verdict-challenged,
        .security-center-admin .security-badge.verdict-observed_only,
        .security-center-admin .security-badge.status-open,
        .security-center-admin .security-badge.status-active,
        .security-center-admin .security-badge.status-completed {
            background: rgba(var(--admin-primary-rgb), 0.14);
            color: var(--admin-primary);
        }

        .security-center-admin .security-badge.status-failed {
            background: rgba(255, 255, 255, 0.08);
            color: var(--admin-foreground);
        }

        .security-center-admin .security-badge.status-pass {
            background: rgba(57, 201, 122, 0.16);
            color: #88efb2;
        }

        .security-center-admin .security-badge.status-fail {
            background: rgba(224, 74, 74, 0.16);
            color: #ff9292;
        }

        .security-center-admin .security-secret {
            display: block;
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(0, 0, 0, 0.26);
            color: #f6f6f4;
            font-family: "Geist Mono", monospace;
            word-break: break-all;
        }

        .security-center-admin code {
            background: rgba(var(--admin-primary-rgb), 0.12) !important;
            color: var(--admin-primary) !important;
            border-radius: 8px;
        }

        .security-center-admin .alert-success {
            border: 1px solid rgba(var(--admin-primary-rgb), 0.24) !important;
            background:
                linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.16), rgba(var(--admin-primary-rgb), 0.05))
                rgba(var(--admin-card-rgb), 0.9) !important;
            color: var(--admin-foreground) !important;
        }

        .security-center-admin .agent-node-chip {
            display: inline-flex;
            align-items: center;
            margin: 0 8px 8px 0;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.18);
            background: rgba(var(--admin-primary-rgb), 0.08);
            color: var(--admin-foreground);
            font-size: 12px;
            font-weight: 600;
        }

        .security-center-admin .agent-node-chip small {
            margin-left: 6px;
            color: var(--admin-muted-foreground);
            font-size: 11px;
        }

        @media (max-width: 991px) {
            .security-center-admin .nav-tabs-custom.security-tabs > .nav-tabs {
                flex-direction: column;
            }
        }
    </style>

    <div class="security-center-admin">
        @if(!empty($provisionedAgents))
            <div class="alert alert-success">
                <strong>{{ count($provisionedAgents) > 1 ? 'Agent bootstrap secrets' : 'Agent bootstrap secret' }}</strong>
                <div class="small admin-text-gap-top-sm">Store these secrets securely. They are only shown immediately after provisioning or rotation.</div>
                @foreach($provisionedAgents as $provisioned)
                    <div class="small admin-text-gap-top-md">
                        {{ $provisioned['name'] ?? 'Security Agent' }}
                        @if(!empty($provisioned['node_name']))
                            for {{ $provisioned['node_name'] }}
                        @endif
                    </div>
                    <span class="security-secret">{{ $provisioned['secret'] }}</span>
                @endforeach
            </div>
        @endif

        <div class="row admin-row-gap-lg">
            <div class="col-md-3 col-sm-6">
                <div class="box metric-card">
                    <span class="eyebrow">Open Incidents</span>
                    <div class="value">{{ number_format($summary['open_incidents']) }}</div>
                    <div class="copy">Incidents that still need monitoring, containment, or resolution.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="box metric-card">
                    <span class="eyebrow">Blocked 24h</span>
                    <div class="value">{{ number_format($summary['blocked_events_24h']) }}</div>
                    <div class="copy">Events that ended with a blocked verdict within the last day.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="box metric-card">
                    <span class="eyebrow">Active Agents</span>
                    <div class="value">{{ number_format($summary['active_agents']) }}</div>
                    <div class="copy">Security agents that are currently alive and heartbeating to the panel.</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="box metric-card">
                    <span class="eyebrow">Quarantine</span>
                    <div class="value">{{ number_format($summary['quarantined_artifacts']) }}</div>
                    <div class="copy">Artifacts quarantined plus {{ number_format($summary['pending_actions']) }} pending action(s).</div>
                </div>
            </div>
        </div>

        <div class="nav-tabs-custom security-tabs">
            <ul class="nav nav-tabs">
                @foreach($tabs as $tab)
                    <li class="@if($selectedTab === $tab) active @endif">
                        <a href="{{ route('admin.security', ['tab' => $tab]) }}">{{ ucwords(str_replace('-', ' ', $tab)) }}</a>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @if($selectedTab === 'overview')
                    <div class="row">
                        <div class="col-md-7">
                            <div class="box box-primary">
                                <div class="box-header with-border admin-billing-box-header">
                                    <h3 class="box-title">Latest Incidents</h3>
                                    @include('admin.billing.partials.table-filter', $overviewIncidentFilter)
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>Threat</th>
                                            <th>Ancaman Jenis Apa</th>
                                            <th>Berjaya Dihalang?</th>
                                            <th>Source</th>
                                            <th>Last Seen</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($overviewIncidents as $incident)
                                            <tr>
                                                <td>
                                                    <strong>{{ $incident->title }}</strong>
                                                    <div class="small text-muted">{{ $incident->surface }} / {{ $incident->threat_id }}</div>
                                                </td>
                                                <td>
                                                    <div>{{ $incident->class }}</div>
                                                    <div class="small text-muted">{{ ucfirst($incident->severity) }} / confidence {{ $incident->confidence }}%</div>
                                                </td>
                                                <td>
                                                    <span class="security-badge verdict-{{ $incident->verdict }}">{{ $incident->verdict }}</span>
                                                </td>
                                                <td>
                                                    <div>{{ $incident->source_ip ?: 'n/a' }}</div>
                                                    <div class="small text-muted">{{ $incident->fingerprint ?: 'no fingerprint' }}</div>
                                                </td>
                                                <td>{{ optional($incident->last_seen_at)?->format('Y-m-d H:i:s T') ?? 'Never' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No incidents recorded yet.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="box-footer clearfix">
                                    @include('admin.billing.partials.table-pagination', ['paginator' => $overviewIncidents])
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Control Plane Summary</h3>
                                </div>
                                <div class="box-body">
                                    <table class="table table-condensed">
                                        <tbody>
                                        <tr>
                                            <th>Security Center</th>
                                            <td>{{ $settings['general']['enabled'] ? 'Enabled' : 'Disabled' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Trusted Networks</th>
                                            <td>{{ implode(', ', $settings['general']['trusted_networks']) ?: 'None' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Break-glass</th>
                                            <td>{{ implode(', ', $settings['break_glass']['trusted_networks']) ?: 'None' }}</td>
                                        </tr>
                                        <tr>
                                            <th>Agent Heartbeat TTL</th>
                                            <td>{{ $settings['agent']['heartbeat_ttl_seconds'] }} seconds</td>
                                        </tr>
                                        <tr>
                                            <th>Runtime IP Deny</th>
                                            <td>{{ $settings['runtime']['ip_deny_minutes'] }} minutes</td>
                                        </tr>
                                        <tr>
                                            <th>Suspicious Upload Quarantine</th>
                                            <td>{{ $settings['upload']['quarantine_on_suspicious'] ? 'Enabled' : 'Observe Only' }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <p class="text-muted admin-text-last">
                                        Control plane ini menyimpan rule, incident, verdict, action queue, dan quarantine state. Node agent beroperasi dengan model pull API bertandatangan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($selectedTab === 'rules')
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Security Rules</h3>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>Class</th>
                                    <th>Surface</th>
                                    <th>Mode</th>
                                    <th>Threshold</th>
                                    <th>Window</th>
                                    <th>Weight</th>
                                    <th>Policy</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($rules as $rule)
                                    <tr>
                                        <td>
                                            <strong>{{ $rule->name }}</strong>
                                            <div class="small text-muted">{{ $rule->key }}</div>
                                        </td>
                                        <td>{{ $rule->class }}</td>
                                        <td>{{ $rule->surface }}</td>
                                        <td>{{ $rule->enabled ? $rule->mode : 'disabled' }}</td>
                                        <td>{{ $rule->threshold }}</td>
                                        <td>{{ $rule->window_seconds }}s</td>
                                        <td>{{ $rule->weight }}</td>
                                        <td>{{ implode(', ', $rule->response_policy ?? []) ?: 'observe' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($selectedTab === 'incidents')
                    <div class="box box-primary">
                        <div class="box-header with-border admin-billing-box-header">
                            <h3 class="box-title">Incidents</h3>
                            @include('admin.billing.partials.table-filter', $incidentFilter)
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Threat</th>
                                    <th>Ancaman Jenis Apa</th>
                                    <th>Berjaya Dihalang?</th>
                                    <th>Events</th>
                                    <th>Last Seen</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($incidents as $incident)
                                    <tr>
                                        <td>
                                            <strong>{{ $incident->title }}</strong>
                                            <div class="small text-muted">{{ $incident->summary }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $incident->class }}</div>
                                            <div class="small text-muted">{{ $incident->surface }} / {{ $incident->severity }}</div>
                                        </td>
                                        <td>
                                            <span class="security-badge verdict-{{ $incident->verdict }}">{{ $incident->verdict }}</span>
                                            <div class="small text-muted admin-text-gap-top-xs">{{ $incident->blocked ? 'Mitigated or blocked.' : 'Observed or waiting for action.' }}</div>
                                        </td>
                                        <td>{{ $incident->event_count }}</td>
                                        <td>{{ optional($incident->last_seen_at)?->format('Y-m-d H:i:s T') ?? 'Never' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No incidents yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer clearfix">
                        @include('admin.billing.partials.table-pagination', ['paginator' => $incidents])
                    </div>
                </div>
                @elseif($selectedTab === 'live-events')
                    <div class="box box-primary">
                        <div class="box-header with-border admin-billing-box-header">
                            <h3 class="box-title">Live Events</h3>
                            @include('admin.billing.partials.table-filter', $eventFilter)
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Rule</th>
                                    <th>Surface</th>
                                    <th>Verdict</th>
                                    <th>Blocked</th>
                                    <th>Evidence</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($events as $event)
                                    <tr>
                                        <td>{{ optional($event->created_at)?->format('Y-m-d H:i:s T') ?? 'Unknown' }}</td>
                                        <td>
                                            <div>{{ $event->rule?->name ?? 'Security Event' }}</div>
                                            <div class="small text-muted">{{ $event->rule?->key ?? $event->class }}</div>
                                        </td>
                                        <td>{{ $event->surface }}</td>
                                        <td><span class="security-badge verdict-{{ $event->verdict }}">{{ $event->verdict }}</span></td>
                                        <td>{{ $event->blocked ? 'Yes' : 'No' }}</td>
                                        <td class="small text-muted">{{ json_encode($event->evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No security events captured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer clearfix">
                        @include('admin.billing.partials.table-pagination', ['paginator' => $events])
                    </div>
                </div>
                @elseif($selectedTab === 'agents')
                    <div class="row">
                        <div class="col-md-4">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Auto Detect Nodes</h3>
                                </div>
                                <div class="box-body">
                                    <div class="admin-stat-display">
                                        {{ number_format($missingNodes->count()) }}
                                    </div>
                                    <p class="text-muted admin-text-gap-top-sm">
                                        Node tanpa agent akan dikesan automatik di sini. Sekali klik, sistem akan jana agent, pautkan ke node, dan guna capability default.
                                    </p>
                                    @if($missingNodes->isNotEmpty())
                                        <div class="admin-gap-top-lg">
                                            @foreach($missingNodes as $node)
                                                <span class="agent-node-chip">
                                                    {{ $node->name }}
                                                    <small>#{{ $node->id }}</small>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted admin-text-last">Semua node sudah ada linked security agent.</p>
                                    @endif
                                </div>
                                <div class="box-footer">
                                    <form method="POST" action="{{ route('admin.security.agents.auto-provision') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" @disabled($missingNodes->isEmpty())>Auto Provision Missing Nodes</button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.security.agents.store') }}">
                                @csrf
                                <div class="box box-primary" data-security-agent-form data-default-capabilities="{{ $defaultAgentCapabilities }}">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Provision Agent</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Name</label>
                                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Auto-generated from selected node if left blank">
                                            <p class="help-block">Jika pilih node dan biarkan kosong, nama agent akan dijana automatik.</p>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Node</label>
                                            <select name="node_id" class="form-control" data-security-agent-node>
                                                <option value="">No linked node</option>
                                                @foreach($nodes as $node)
                                                    <option
                                                        value="{{ $node->id }}"
                                                        data-agent-name="{{ $node->name }} Security Agent"
                                                        @selected((string) old('node_id') === (string) $node->id)
                                                    >{{ $node->name }} (#{{ $node->id }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Capabilities</label>
                                            <input type="text" class="form-control" name="capabilities" value="{{ old('capabilities', $defaultAgentCapabilities) }}" placeholder="{{ $defaultAgentCapabilities }}">
                                            <p class="help-block">Comma-separated capability labels announced during heartbeat and pull-action handling. Default akan dipakai automatik jika dibiarkan kosong.</p>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary">Create Agent</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-8">
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Agents</h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>Agent</th>
                                            <th>Status</th>
                                            <th>Node</th>
                                            <th>Capabilities</th>
                                            <th>Heartbeat</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($agents as $agent)
                                            <tr>
                                                <td>
                                                    <strong>{{ $agent->name }}</strong>
                                                    <div class="small text-muted">{{ $agent->uuid }}</div>
                                                </td>
                                                <td><span class="security-badge status-{{ $agent->status }}">{{ $agent->status }}</span></td>
                                                <td>
                                                    <div>{{ $agent->node?->name ?? 'Unlinked' }}</div>
                                                    <div class="small text-muted">{{ $agent->node?->fqdn ?? 'No node binding' }}</div>
                                                </td>
                                                <td class="small text-muted">{{ implode(', ', $agent->capabilities ?? []) ?: 'None' }}</td>
                                                <td>
                                                    <div>{{ optional($agent->last_heartbeat_at)?->format('Y-m-d H:i:s T') ?? 'Never' }}</div>
                                                    <div class="small text-muted">{{ $agent->last_ip ?: 'no IP yet' }}</div>
                                                </td>
                                                <td class="admin-action-col">
                                                    <form method="POST" action="{{ route('admin.security.agents.rotate-secret', $agent->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-default btn-sm">Rotate Secret</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No agents provisioned yet.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Action Queue</h3>
                                </div>
                                <div class="box-body table-responsive no-padding">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Agent</th>
                                            <th>Action</th>
                                            <th>Status</th>
                                            <th>Result</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($actions->take(25) as $action)
                                            <tr>
                                                <td>#{{ $action->id }}</td>
                                                <td>{{ $action->agent?->name ?? 'Local' }}</td>
                                                <td>{{ $action->action }}</td>
                                                <td><span class="security-badge status-{{ $action->status }}">{{ $action->status }}</span></td>
                                                <td class="small text-muted">{{ json_encode($action->result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No action records yet.</td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif($selectedTab === 'quarantine')
                    <div class="box box-primary">
                        <div class="box-header with-border admin-billing-box-header">
                            <h3 class="box-title">Quarantine Artifacts</h3>
                            @include('admin.billing.partials.table-filter', $artifactFilter)
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Target</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Path</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($artifacts as $artifact)
                                    <tr>
                                        <td>{{ optional($artifact->quarantined_at)?->format('Y-m-d H:i:s T') ?? 'Unknown' }}</td>
                                        <td>
                                            <div>{{ class_basename($artifact->target_type) }} #{{ $artifact->target_id }}</div>
                                            <div class="small text-muted">{{ $artifact->original_name ?: 'n/a' }}</div>
                                        </td>
                                        <td>{{ $artifact->reason }}</td>
                                        <td><span class="security-badge verdict-{{ $artifact->status }}">{{ $artifact->status }}</span></td>
                                        <td class="small text-muted">{{ $artifact->disk ? $artifact->disk . ':' : '' }}{{ $artifact->path ?: 'n/a' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No quarantine artifacts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer clearfix">
                        @include('admin.billing.partials.table-pagination', ['paginator' => $artifacts])
                    </div>
                </div>
                @elseif($selectedTab === 'settings')
                    <form method="POST" action="{{ route('admin.security.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">General</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Security Center Enabled</label>
                                            <select class="form-control" name="security:enabled">
                                                <option value="true" @selected($settings['general']['enabled'])>Enabled</option>
                                                <option value="false" @selected(!$settings['general']['enabled'])>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Trusted Networks</label>
                                            <input type="text" class="form-control" name="security:trusted_networks" value="{{ old('security:trusted_networks', implode(', ', $settings['general']['trusted_networks'])) }}" placeholder="127.0.0.1, 10.0.0.0/8">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Break-glass Networks</label>
                                            <input type="text" class="form-control" name="security:break_glass:trusted_networks" value="{{ old('security:break_glass:trusted_networks', implode(', ', $settings['break_glass']['trusted_networks'])) }}" placeholder="203.0.113.0/24">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Emergency Recovery Token</label>
                                            <input type="text" class="form-control" name="security:break_glass:emergency_token" value="" placeholder="{{ $settings['break_glass']['emergency_token'] ? 'Stored. Enter a new value to rotate.' : 'Set a recovery token' }}">
                                            <p class="help-block">Header used for audited break-glass bypass: <code>X-Security-Recovery-Token</code>.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="box box-default">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Runtime Policy</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">IP Deny Minutes</label>
                                            <input type="number" class="form-control" name="security:runtime:ip_deny_minutes" value="{{ old('security:runtime:ip_deny_minutes', $settings['runtime']['ip_deny_minutes']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Fingerprint Deny Minutes</label>
                                            <input type="number" class="form-control" name="security:runtime:fingerprint_deny_minutes" value="{{ old('security:runtime:fingerprint_deny_minutes', $settings['runtime']['fingerprint_deny_minutes']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Route Hold Minutes</label>
                                            <input type="number" class="form-control" name="security:runtime:route_hold_minutes" value="{{ old('security:runtime:route_hold_minutes', $settings['runtime']['route_hold_minutes']) }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="box box-default">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Auth / API / Bridge / Webhook</h3>
                                    </div>
                                    <div class="box-body">
                                        @foreach([
                                            'security:auth:enabled' => ['label' => 'Auth Protection', 'value' => $settings['auth']['enabled']],
                                            'security:auth:auto_challenge' => ['label' => 'Auth Auto Challenge', 'value' => $settings['auth']['auto_challenge']],
                                            'security:auth:auto_temp_block' => ['label' => 'Auth Auto Temp Block', 'value' => $settings['auth']['auto_temp_block']],
                                            'security:api:enabled' => ['label' => 'API Protection', 'value' => $settings['api']['enabled']],
                                            'security:api:revoke_token_on_ip_anomaly' => ['label' => 'Revoke API Token On IP Anomaly', 'value' => $settings['api']['revoke_token_on_ip_anomaly']],
                                            'security:bridge:enabled' => ['label' => 'Internal Bridge Protection', 'value' => $settings['bridge']['enabled']],
                                            'security:webhook:enabled' => ['label' => 'Webhook Protection', 'value' => $settings['webhook']['enabled']],
                                        ] as $key => $field)
                                            <div class="form-group">
                                                <label class="control-label">{{ $field['label'] }}</label>
                                                <select class="form-control" name="{{ $key }}">
                                                    <option value="true" @selected($field['value'])>Enabled</option>
                                                    <option value="false" @selected(!$field['value'])>Disabled</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="box box-default">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Upload / Malware Policy</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Upload Inspection</label>
                                            <select class="form-control" name="security:upload:enabled">
                                                <option value="true" @selected($settings['upload']['enabled'])>Enabled</option>
                                                <option value="false" @selected(!$settings['upload']['enabled'])>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Quarantine Suspicious Uploads</label>
                                            <select class="form-control" name="security:upload:quarantine_on_suspicious">
                                                <option value="true" @selected($settings['upload']['quarantine_on_suspicious'])>Enabled</option>
                                                <option value="false" @selected(!$settings['upload']['quarantine_on_suspicious'])>Observe Only</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Suspicious Extensions</label>
                                            <input type="text" class="form-control" name="security:upload:suspicious_extensions" value="{{ old('security:upload:suspicious_extensions', implode(', ', $settings['upload']['suspicious_extensions'])) }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="box box-default">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Origin / DDoS</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Origin Detection Enabled</label>
                                            <select class="form-control" name="security:origin:enabled">
                                                <option value="true" @selected($settings['origin']['enabled'])>Enabled</option>
                                                <option value="false" @selected(!$settings['origin']['enabled'])>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Not Controllable Threshold</label>
                                            <input type="number" class="form-control" name="security:origin:not_controllable_threshold" value="{{ old('security:origin:not_controllable_threshold', $settings['origin']['not_controllable_threshold']) }}">
                                            <p class="help-block">Reports above this threshold should be marked <code>not_controllable_at_origin</code> by the agent/reporting layer.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="box box-default">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Agent Policy / Retention</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Agent Module</label>
                                            <select class="form-control" name="security:agent:enabled">
                                                <option value="true" @selected($settings['agent']['enabled'])>Enabled</option>
                                                <option value="false" @selected(!$settings['agent']['enabled'])>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Heartbeat TTL Seconds</label>
                                            <input type="number" class="form-control" name="security:agent:heartbeat_ttl_seconds" value="{{ old('security:agent:heartbeat_ttl_seconds', $settings['agent']['heartbeat_ttl_seconds']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Clock Skew Seconds</label>
                                            <input type="number" class="form-control" name="security:agent:clock_skew_seconds" value="{{ old('security:agent:clock_skew_seconds', $settings['agent']['clock_skew_seconds']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Secret Rotation Grace Seconds</label>
                                            <input type="number" class="form-control" name="security:agent:secret_rotation_grace_seconds" value="{{ old('security:agent:secret_rotation_grace_seconds', $settings['agent']['secret_rotation_grace_seconds']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Nonce TTL Seconds</label>
                                            <input type="number" class="form-control" name="security:agent:nonce_ttl_seconds" value="{{ old('security:agent:nonce_ttl_seconds', $settings['agent']['nonce_ttl_seconds']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Event Retention Days</label>
                                            <input type="number" class="form-control" name="security:retention:event_days" value="{{ old('security:retention:event_days', $settings['retention']['event_days']) }}">
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Incident Retention Days</label>
                                            <input type="number" class="form-control" name="security:retention:incident_days" value="{{ old('security:retention:incident_days', $settings['retention']['incident_days']) }}">
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary">Save Security Settings</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            const form = document.querySelector('[data-security-agent-form]');
            if (!form) {
                return;
            }

            const nodeSelect = form.querySelector('[data-security-agent-node]');
            const nameInput = form.querySelector('input[name="name"]');
            const capabilitiesInput = form.querySelector('input[name="capabilities"]');
            const defaultCapabilities = form.getAttribute('data-default-capabilities') || '';

            if (!nodeSelect || !nameInput || !capabilitiesInput) {
                return;
            }

            const fillFromNode = () => {
                const selected = nodeSelect.options[nodeSelect.selectedIndex];
                if (!selected || !selected.value) {
                    return;
                }

                if (nameInput.value.trim() === '' && selected.dataset.agentName) {
                    nameInput.value = selected.dataset.agentName;
                }

                if (capabilitiesInput.value.trim() === '') {
                    capabilitiesInput.value = defaultCapabilities;
                }
            };

            nodeSelect.addEventListener('change', fillFromNode);

            if (nodeSelect.value) {
                fillFromNode();
            }
        })();
    </script>
@endsection
