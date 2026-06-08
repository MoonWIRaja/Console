@extends('layouts.admin')

@section('title')
    Discord Settings
@endsection

@section('content-header')
    <h1>Discord Settings<small>Configure bot access, application values, and community auto-join.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Discord</li>
    </ol>
@endsection

@section('content')
    @php($communityEnabled = filter_var(old('services:discord:community_enabled', config('services.discord.community_enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($oauthReady = filter_var(config('services.discord.enabled', false), FILTER_VALIDATE_BOOLEAN) && filled(config('services.discord.client_id')) && filled(config('services.discord.client_secret')))
    @php($botReady = filled(config('services.discord.guild_id')) && filled(config('services.discord.bot_token')))

    <style>
        .discord-admin-shell {
            position: relative;
            padding: 18px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16);
            border-radius: 18px;
            background:
                radial-gradient(520px 220px at 0% 0%, rgba(var(--admin-primary-rgb), 0.12), transparent 68%),
                radial-gradient(480px 200px at 100% 0%, rgba(var(--admin-primary-rgb), 0.08), transparent 70%),
                linear-gradient(180deg, rgba(var(--admin-card-rgb), 0.94), rgba(var(--admin-card-rgb), 0.8));
            box-shadow: 0 28px 50px -36px rgba(0, 0, 0, 0.92);
            overflow: hidden;
        }

        .discord-admin-shell::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.015), transparent 34%),
                linear-gradient(0deg, rgba(0, 0, 0, 0.08), transparent 35%);
            opacity: 0.95;
        }

        .discord-admin {
            position: relative;
            z-index: 1;
        }

        .discord-admin.nav-tabs-custom.discord-tabs {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs {
            display: flex;
            gap: 10px;
            margin: 0 0 16px !important;
            padding: 8px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background: rgba(var(--admin-card-rgb), 0.88) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li {
            float: none !important;
            margin: 0 !important;
            border-top: 0 !important;
            border-radius: 10px !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li > a {
            margin: 0 !important;
            border: 1px solid transparent !important;
            border-radius: 10px !important;
            padding: 11px 16px !important;
            font-weight: 600;
            color: var(--admin-muted-foreground) !important;
            transition: all 0.18s ease;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li > a:hover {
            background: rgba(var(--admin-primary-rgb), 0.08) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.14) !important;
            color: var(--admin-foreground) !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li.active {
            border-top-color: transparent !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li.active > a,
        .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs > li.active:hover > a {
            background:
                linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.22), rgba(var(--admin-primary-rgb), 0.08))
                rgba(var(--admin-card-rgb), 0.98) !important;
            border-color: rgba(var(--admin-primary-rgb), 0.24) !important;
            color: var(--admin-primary) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02), 0 10px 22px -18px rgba(var(--admin-primary-rgb), 0.9);
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content {
            padding: 20px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-radius: 14px !important;
            background:
                linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.05), rgba(var(--admin-primary-rgb), 0.01) 26%),
                rgba(var(--admin-card-rgb), 0.92) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.85);
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content > .tab-pane,
        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content > .tab-pane.active {
            background: transparent !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content > .tab-pane.active {
            padding: 18px;
            border: 1px solid rgba(var(--admin-primary-rgb), 0.1) !important;
            border-radius: 14px !important;
            background:
                linear-gradient(160deg, rgba(var(--admin-primary-rgb), 0.06), transparent 45%),
                rgba(var(--admin-card-rgb), 0.6) !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.015) !important;
        }

        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content > .tab-pane > form,
        .discord-admin.nav-tabs-custom.discord-tabs > .tab-content > .tab-pane.active > form {
            background: transparent !important;
        }

        .discord-admin .box {
            border: 1px solid rgba(var(--admin-primary-rgb), 0.16) !important;
            border-top-width: 1px !important;
            border-radius: 14px !important;
            overflow: hidden;
            background: rgba(var(--admin-card-rgb), 0.92) !important;
            box-shadow: 0 20px 36px -30px rgba(0, 0, 0, 0.88);
        }

        .discord-admin .box .box-header,
        .discord-admin .box .box-body,
        .discord-admin .box .box-footer {
            background: rgba(var(--admin-card-rgb), 0.42) !important;
            color: var(--admin-foreground) !important;
        }

        .discord-admin .box .box-header.with-border,
        .discord-admin .box .box-footer {
            border-color: rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .discord-admin .box.box-primary .box-header,
        .discord-admin .box.box-info .box-header {
            background: linear-gradient(180deg, rgba(var(--admin-primary-rgb), 0.16), rgba(var(--admin-primary-rgb), 0.05)) !important;
        }

        .discord-admin .box.box-info {
            border-color: rgba(91, 180, 255, 0.2) !important;
            box-shadow: 0 20px 36px -30px rgba(15, 72, 117, 0.56);
        }

        .discord-admin .box.box-info .box-header {
            background: linear-gradient(180deg, rgba(91, 180, 255, 0.18), rgba(91, 180, 255, 0.05)) !important;
        }

        .discord-admin .box.box-warning {
            border-color: rgba(255, 193, 86, 0.22) !important;
            box-shadow: 0 20px 36px -30px rgba(125, 82, 10, 0.56);
        }

        .discord-admin .box.box-warning .box-header {
            background: linear-gradient(180deg, rgba(255, 193, 86, 0.2), rgba(255, 193, 86, 0.06)) !important;
        }

        .discord-admin .box .box-title,
        .discord-admin label,
        .discord-admin strong {
            color: var(--admin-foreground) !important;
        }

        .discord-admin .box-body,
        .discord-admin .box-footer,
        .discord-admin .tab-content,
        .discord-admin .tab-pane,
        .discord-admin p,
        .discord-admin li,
        .discord-admin div {
            color: var(--admin-foreground);
        }

        .discord-admin .text-muted,
        .discord-admin .small,
        .discord-admin .help-block {
            color: var(--admin-muted-foreground) !important;
        }

        .discord-admin a {
            color: var(--admin-primary);
        }

        .discord-admin a:hover,
        .discord-admin a:focus {
            color: var(--admin-foreground);
        }

        .discord-admin .form-control {
            border-radius: 10px !important;
            border-color: rgba(var(--admin-primary-rgb), 0.18) !important;
            background: rgba(var(--admin-card-rgb), 0.72) !important;
            color: var(--admin-foreground) !important;
            box-shadow: none !important;
        }

        .discord-admin .form-control:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.42) !important;
            box-shadow: 0 0 0 3px rgba(var(--admin-primary-rgb), 0.12) !important;
        }

        .discord-admin select.form-control option {
            background: rgba(18, 24, 16, 0.98);
            color: #f4f6ef;
        }

        .discord-admin .btn {
            border-radius: 10px !important;
            font-weight: 600;
            box-shadow: none !important;
        }

        .discord-admin .btn-primary {
            border-color: rgba(var(--admin-primary-rgb), 0.38) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 0.95), rgba(var(--admin-primary-rgb), 0.72)) !important;
            color: #081105 !important;
        }

        .discord-admin .btn-primary:hover,
        .discord-admin .btn-primary:focus {
            border-color: rgba(var(--admin-primary-rgb), 0.48) !important;
            background: linear-gradient(135deg, rgba(var(--admin-primary-rgb), 1), rgba(var(--admin-primary-rgb), 0.82)) !important;
            color: #081105 !important;
        }

        .discord-admin code {
            background: rgba(var(--admin-primary-rgb), 0.12) !important;
            color: var(--admin-primary) !important;
            border-radius: 8px;
        }

        @media (max-width: 767px) {
            .discord-admin.nav-tabs-custom.discord-tabs > .nav-tabs {
                flex-direction: column;
            }
        }
    </style>

    <div class="discord-admin-shell">
        <div class="nav-tabs-custom discord-tabs discord-admin">
            <ul class="nav nav-tabs">
                <li class="@if($activeTab === 'bot') active @endif">
                    <a href="{{ route('admin.discord', ['tab' => 'bot']) }}">Bot Setup</a>
                </li>
                <li class="@if($activeTab === 'community') active @endif">
                    <a href="{{ route('admin.discord', ['tab' => 'community']) }}">Community</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane @if($activeTab === 'bot') active @endif">
                    <form method="POST" action="{{ route('admin.discord.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="tab" value="bot">
                        <input type="hidden" name="services:discord:community_enabled" value="{{ $communityEnabled ? 'true' : 'false' }}">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Bot & Application</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Guild ID</label>
                                                <input type="text" class="form-control" name="services:discord:guild_id" value="{{ old('services:discord:guild_id', config('services.discord.guild_id')) }}" placeholder="123456789012345678">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Bot Token</label>
                                                <input type="text" class="form-control" name="services:discord:bot_token" value="" autocomplete="new-password" placeholder="{{ filled(config('services.discord.bot_token')) ? 'Stored securely. Leave blank to keep.' : 'paste bot token here' }}">
                                                <p class="text-muted small admin-help-tight">Leave blank to keep the current bot token. Enter <code>!e</code> to clear it.</p>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Application ID</label>
                                                <input type="text" class="form-control" name="services:discord:application_id" value="{{ old('services:discord:application_id', config('services.discord.application_id')) }}" placeholder="123456789012345678">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Application Public Key</label>
                                                <input type="text" class="form-control" name="services:discord:application_public_key" value="{{ old('services:discord:application_public_key', config('services.discord.application_public_key')) }}" placeholder="discord application public key">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary">Save Bot Setup</button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="box box-info">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Status</h3>
                                    </div>
                                    <div class="box-body">
                                        <p><strong>Discord OAuth ready:</strong> {{ $oauthReady ? 'Yes' : 'No' }}</p>
                                        <p><strong>Bot ready:</strong> {{ $botReady ? 'Yes' : 'No' }}</p>
                                        <p class="text-muted admin-text-last">Ticket launcher and other Discord bridge features still use their dedicated settings inside <a href="{{ route('admin.tickets.settings') }}">Support Tickets</a>.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane @if($activeTab === 'community') active @endif">
                    <form method="POST" action="{{ route('admin.discord.update') }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="tab" value="community">

                        <div class="row">
                            <div class="col-md-8">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Discord Community</h3>
                                    </div>
                                    <div class="box-body">
                                        <div class="form-group">
                                            <label class="control-label">Status</label>
                                            <select class="form-control" name="services:discord:community_enabled">
                                                <option value="true" @if($communityEnabled) selected @endif>Enabled</option>
                                                <option value="false" @if(!$communityEnabled) selected @endif>Disabled</option>
                                            </select>
                                            <p class="text-muted small admin-help-tight">Allow linked Discord users to join your Discord server and receive a role automatically from the account page.</p>
                                        </div>
                                        <div class="row">
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Guild ID</label>
                                                <input type="text" class="form-control" name="services:discord:guild_id" value="{{ old('services:discord:guild_id', config('services.discord.guild_id')) }}" placeholder="123456789012345678">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label class="control-label">Role ID</label>
                                                <input type="text" class="form-control" name="services:discord:role_id" value="{{ old('services:discord:role_id', config('services.discord.role_id')) }}" placeholder="123456789012345678">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="control-label">Invite URL</label>
                                            <input type="text" class="form-control" name="services:discord:invite_url" value="{{ old('services:discord:invite_url', config('services.discord.invite_url')) }}" placeholder="https://discord.gg/example">
                                        </div>
                                        <div class="form-group admin-form-group-tight">
                                            <label class="control-label">Bot Token</label>
                                            <input type="text" class="form-control" name="services:discord:bot_token" value="" autocomplete="new-password" placeholder="{{ filled(config('services.discord.bot_token')) ? 'Stored securely. Leave blank to keep.' : 'paste bot token here' }}">
                                        </div>
                                    </div>
                                    <div class="box-footer">
                                        <button type="submit" class="btn btn-primary">Save Community Settings</button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="box box-warning">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Requirements</h3>
                                    </div>
                                    <div class="box-body">
                                        <p><strong>Discord OAuth</strong> must be enabled and configured in <a href="{{ route('admin.oauth') }}">OAuth Settings</a>.</p>
                                        <p><strong>Bot token</strong> must belong to a bot already inside the guild.</p>
                                        <p class="text-muted admin-text-last">The bot must be able to add members and manage the configured role.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
