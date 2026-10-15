@extends('layouts.admin')

@section('title')
    AI Assistant
@endsection

@section('content-header')
    <h1>AI Assistant<small>{{ config('ai.persona_name') ?: 'Anney' }} for the panel and Discord.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">AI Assistant</li>
    </ol>
@endsection

@section('content')
    @php($aiEnabled = filter_var(config('ai.enabled'), FILTER_VALIDATE_BOOLEAN))
    <style>
        .ai-admin textarea.code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
        .ai-admin .table > tbody > tr > td { vertical-align: middle; }
        .ai-admin .inline-form { display: inline; }
        .ai-admin .model-id { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; }
        .ai-admin .stat { font-size: 22px; font-weight: 700; }
        .ai-admin .ai-provider-grid { display: flex; flex-wrap: wrap; }
        .ai-admin .ai-provider-grid > [class*="col-"] { display: flex; float: none; margin-bottom: 18px; }
        .ai-admin .ai-provider-card { display: flex; flex-direction: column; width: 100%; margin-bottom: 0; }
        .ai-admin .ai-provider-card .box-body { display: flex; flex-direction: column; flex: 1; gap: 6px; }
        .ai-admin .ai-provider-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .ai-admin .ai-provider-head strong { font-size: 15px; }
        .ai-admin .ai-provider-head > span { white-space: nowrap; }
        .ai-admin .ai-provider-notes { flex: 1; line-height: 1.45; }
        .ai-admin .ai-provider-notes > div + div { margin-top: 4px; }
        .ai-admin .ai-provider-action { margin-top: 4px; }
    </style>

    <div class="ai-admin">
        @if(!$aiEnabled)
            <div class="alert alert-warning">The assistant is <strong>disabled</strong>. Add a provider, enable a model, then turn it on in the General tab.</div>
        @elseif($usableModels->isEmpty())
            <div class="alert alert-danger">The assistant is enabled but no model is usable. Enable at least one model in the Models tab.</div>
        @endif

        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                @foreach(['general' => 'General & Personality', 'providers' => 'Providers', 'models' => 'Models', 'skills' => 'Skills', 'discord' => 'Discord Bot', 'audit' => 'Audit Log'] as $key => $label)
                    <li class="@if($tab === $key) active @endif"><a href="{{ route('admin.ai', ['tab' => $key]) }}">{{ $label }}</a></li>
                @endforeach
            </ul>

            <div class="tab-content">
                {{-- GENERAL --}}
                @if($tab === 'general')
                    <div class="row">
                        <div class="col-md-8">
                            <form method="POST" action="{{ route('admin.ai.settings') }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="tab" value="general">
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label class="control-label">Status</label>
                                        <select name="ai:enabled" class="form-control">
                                            <option value="true" @if($aiEnabled) selected @endif>Enabled</option>
                                            <option value="false" @if(!$aiEnabled) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="control-label">Assistant name</label>
                                        <input type="text" name="ai:persona_name" class="form-control" value="{{ old('ai:persona_name', config('ai.persona_name')) }}">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="control-label">Brave Search API key</label>
                                    <input type="text" name="ai:brave_search_key" class="form-control" autocomplete="new-password" placeholder="{{ filled(config('ai.brave_search_key')) ? 'Saved. Leave blank to keep it.' : 'Brave Search API key (free plan)' }}">
                                    <p class="text-muted small">Lets Anney research prices, modpacks and news on the internet. Get a key from <a href="https://brave.com/search/api/" target="_blank" rel="noopener">brave.com/search/api</a>. Without it Anney cannot search the web. Enter <code>!e</code> to remove it.</p>
                                </div>
                                <div class="form-group">
                                    <label class="control-label">Personality & instructions</label>
                                    <textarea name="ai:persona_prompt" rows="14" class="form-control code">{{ old('ai:persona_prompt', config('ai.persona_prompt')) }}</textarea>
                                    <p class="text-muted small">This is the system prompt. The panel automatically adds the user's identity and memories, the attached server, the mode rules, the safety rules and enabled skills after it.</p>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm pull-right">Save</button>
                                <div class="clearfix"></div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="box box-default">
                                <div class="box-header with-border"><h3 class="box-title">Usage</h3></div>
                                <div class="box-body">
                                    <div class="row text-center">
                                        <div class="col-xs-6"><div class="stat">{{ $stats['conversations'] }}</div><small>conversations</small></div>
                                        <div class="col-xs-6"><div class="stat">{{ $stats['panel_users'] }}</div><small>panel users</small></div>
                                        <div class="col-xs-6"><div class="stat">{{ $stats['discord_chats'] }}</div><small>Discord chats</small></div>
                                        <div class="col-xs-6"><div class="stat">{{ $stats['memories'] }}</div><small>user memories</small></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box box-default">
                                <div class="box-header with-border"><h3 class="box-title">How it is kept safe</h3></div>
                                <div class="box-body small">
                                    <ul style="padding-left:16px;margin:0">
                                        <li>No shell or host access: every tool goes through the Wings API for one server, which is jailed to that container.</li>
                                        <li>Only servers the user owns or is a subuser of, with the same subuser permissions.</li>
                                        <li><strong>Ask</strong> mode is read-only. <strong>Agent</strong> mode shows an Approve / Reject card before any write, command or power action.</li>
                                        <li>Panel-managed folders (<code>.backups</code>, <code>.recycle_bin</code>, <code>.steam</code>) are blocked.</li>
                                        <li>Approved actions are written to the server activity log as <code>server:ai.*</code>.</li>
                                        <li>Discord is chat only.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- PROVIDERS --}}
                @if($tab === 'providers')
                    <p class="text-muted">Providers follow <a href="https://github.com/decolua/9router" target="_blank" rel="noopener">9router</a>: <strong>Subscription</strong> accounts log in with OAuth and use that plan's quota; <strong>Free</strong> ones are free tiers. You can connect several accounts of the same provider; when one fails, the next model by priority is used.</p>

                    <div class="box box-primary">
                        <div class="box-header with-border"><h3 class="box-title">Connected accounts</h3></div>
                        <div class="box-body">
                            <style>
                                .ai-acc-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
                                .ai-acc { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 16px; display: flex; flex-direction: column; gap: 10px; transition: border-color .15s; }
                                .ai-acc:hover { border-color: #2D4A3E; }
                                .ai-acc-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
                                .ai-acc-name { font-size: 14px; font-weight: 900; color: #742220; word-break: break-word; }
                                .ai-acc-sub { font-size: 11px; color: rgba(116,34,32,.65); margin-top: 2px; word-break: break-all; }
                                .ai-acc-facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
                                .ai-acc-fact { background: #FEF9E1; border: 1px solid #C8BCA0; border-radius: 8px; padding: 6px 8px; }
                                .ai-acc-fact span { display: block; font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(116,34,32,.55); }
                                .ai-acc-fact b { font-size: 12px; color: #4B3A2F; }
                                .ai-acc-error { font-size: 11px; color: #A03A2F; background: rgba(160,58,47,.06); border: 1px solid rgba(160,58,47,.35); border-radius: 8px; padding: 6px 8px; word-break: break-word; }
                                .ai-acc-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; padding-top: 4px; }
                                .ai-acc-actions form { display: inline; margin: 0; }
                                .ai-acc-edit { border-top: 2px dashed #C8BCA0; padding-top: 12px; }
                                .ai-acc-edit .row { margin: 0 -6px !important; }
                                .ai-acc-edit .row > [class*="col-"] { padding: 0 6px !important; }
                                .ai-acc-empty { text-align: center; padding: 28px 14px; border: 2px dashed #C8BCA0; border-radius: 12px; font-size: 12px; color: rgba(116,34,32,.6); }
                            </style>
                            @if($providers->isEmpty())
                                <div class="ai-acc-empty">Nothing connected yet. Pick a provider below.</div>
                            @else
                                <div class="ai-acc-grid">
                                    @foreach($providers as $provider)
                                        @php($def = $provider->definition())
                                        @php($label = $def['name'] ?? null)
                                        @php($email = preg_match('/\(([^)]+)\)\s*$/', $provider->name, $mm) ? $mm[1] : null)
                                        <div class="ai-acc">
                                            <div class="ai-acc-top">
                                                <div style="min-width:0">
                                                    <div class="ai-acc-name">{{ $email ? trim(str_replace('(' . $email . ')', '', $provider->name)) : $provider->name }}</div>
                                                    @if($email)<div class="ai-acc-sub">{{ $email }}</div>@endif
                                                </div>
                                                @if($provider->enabled)<span class="label label-success">Enabled</span>@else<span class="label label-default">Disabled</span>@endif
                                            </div>
                                            <div class="ai-acc-facts">
                                                <div class="ai-acc-fact"><span>Type</span><b>{{ ($def['group'] ?? '') === 'subscription' ? 'Subscription' : 'Free' }}</b></div>
                                                <div class="ai-acc-fact"><span>Models</span><b>{{ $provider->models_count }}</b></div>
                                                <div class="ai-acc-fact"><span>Priority</span><b>{{ $provider->priority }}</b></div>
                                            </div>
                                            <div class="ai-acc-sub">{{ ($def['flow'] ?? '') === 'apikey' ? 'API key' : 'OAuth' }} · token {{ $provider->token_expires_at ? 'expires ' . $provider->token_expires_at->diffForHumans() : 'has no expiry' }}</div>
                                            @if($provider->last_error)<div class="ai-acc-error">{{ $provider->last_error }}</div>@endif
                                            <div class="ai-acc-actions">
                                                <form method="POST" action="{{ route('admin.ai.providers.sync', $provider->id) }}">@csrf<button class="btn btn-xs btn-info">Reload models</button></form>
                                                <button type="button" class="btn btn-xs btn-default" data-toggle="collapse" data-target="#provider-{{ $provider->id }}">Edit</button>
                                                <form method="POST" action="{{ route('admin.ai.providers.destroy', $provider->id) }}" onsubmit="return confirm('Disconnect {{ $provider->name }}?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Disconnect</button></form>
                                            </div>
                                            <div class="collapse" id="provider-{{ $provider->id }}">
                                                <form method="POST" action="{{ route('admin.ai.providers.update', $provider->id) }}" class="ai-acc-edit">
                                                    @csrf @method('PATCH')
                                                    <div class="row">
                                                        <div class="form-group col-xs-12"><label>Name</label><input class="form-control input-sm" name="name" value="{{ $provider->name }}"></div>
                                                        @if(($def['flow'] ?? '') === 'apikey')
                                                            <div class="form-group col-xs-12"><label>New API key</label><input class="form-control input-sm" name="api_key" autocomplete="new-password" placeholder="blank keeps the current key"></div>
                                                        @endif
                                                        <div class="form-group col-xs-6"><label>Priority</label><input type="number" class="form-control input-sm" name="priority" value="{{ $provider->priority }}"></div>
                                                        <div class="form-group col-xs-6"><label>Enabled</label><select class="form-control input-sm" name="enabled"><option value="1" @if($provider->enabled) selected @endif>Yes</option><option value="0" @if(!$provider->enabled) selected @endif>No</option></select></div>
                                                    </div>
                                                    <div class="text-right"><button class="btn btn-primary btn-sm">Save</button></div>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    @foreach(['subscription' => ['Subscription (OAuth login)', 'Log in with an existing plan. Its quota is shared by every panel user who chats.'], 'free' => ['Free', 'Free tiers that log in with OAuth.'], 'apikey' => ['Claude API', 'Official API key, billed per use by Anthropic.']] as $group => [$title, $sub])
                        <h4 style="margin-top:22px">{{ $title }} <small>{{ $sub }}</small></h4>
                        <div class="row ai-provider-grid">
                            @foreach($catalog[$group] as $id => $def)
                                <div class="col-lg-4 col-md-6 col-sm-12">
                                    <div class="box box-default ai-provider-card">
                                        <div class="box-body">
                                            <div class="ai-provider-head">
                                                <strong>{{ $def['name'] }}</strong>
                                                <span>
                                                    @if($def['risk'])<span class="label label-warning" title="{{ \Pterodactyl\Services\Ai\Catalog\ProviderCatalog::RISK_NOTICE }}" style="cursor:help">RISK</span>@endif
                                                    <span class="label label-default">{{ $def['flow'] === 'apikey' ? 'API key' : 'OAuth' }}</span>
                                                </span>
                                            </div>
                                            <div class="text-muted small">{{ count($def['models']) }} models{{ !empty($def['models_fetcher']) ? ' + live free list' : '' }}@if($def['link']) · <a href="{{ $def['link'] }}" target="_blank" rel="noopener">{{ $def['flow'] === 'apikey' ? 'get a key' : 'website' }}</a>@endif</div>
                                            <div class="ai-provider-notes small">
                                                @if($def['notice'])<div>{{ $def['notice'] }}</div>@endif
                                                @if($def['risk'])<div class="text-warning" title="{{ \Pterodactyl\Services\Ai\Catalog\ProviderCatalog::RISK_NOTICE }}">Account may be restricted or banned by the provider.</div>@endif
                                            </div>
                                            <div class="ai-provider-action">

                                            @if($def['flow'] === 'apikey')
                                                <form method="POST" action="{{ route('admin.ai.providers.store') }}">
                                                    @csrf
                                                    <input type="hidden" name="preset" value="{{ $id }}">
                                                    <div class="input-group input-group-sm" style="margin-bottom:4px">
                                                        <input name="api_key" class="form-control" autocomplete="new-password" placeholder="API key">
                                                        <span class="input-group-btn"><button class="btn btn-success">Add</button></span>
                                                    </div>
                                                    @if(str_contains($def['base_url'], '{accountId}'))
                                                        <input name="account_id" class="form-control input-sm" style="margin-bottom:4px" placeholder="Account ID">
                                                    @endif
                                                    <input name="label" class="form-control input-sm" placeholder="Label (optional, e.g. account 2)">
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-sm btn-primary btn-block ai-connect" data-provider="{{ $id }}" data-name="{{ $def['name'] }}">Connect {{ $def['name'] }}</button>
                                            @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="modal fade" id="aiOauthModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Connect <span id="aiOauthName"></span></h4>
                                </div>
                                <div class="modal-body">
                                    <div id="aiOauthLoading">Starting login…</div>
                                    <div id="aiOauthError" class="alert alert-danger" style="display:none"></div>
                                    <div id="aiOauthDevice" style="display:none">
                                        <p>1. Open this page and sign in:</p>
                                        <p><a id="aiOauthDeviceLink" href="#" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open login page</a></p>
                                        <p>2. If it asks for a code, enter:</p>
                                        <p><code id="aiOauthCode" style="font-size:22px;letter-spacing:3px"></code></p>
                                        <p class="text-muted small"><i class="fa fa-spinner fa-spin"></i> Waiting for approval… this window updates by itself.</p>
                                    </div>
                                    <div id="aiOauthPaste" style="display:none">
                                        <p>1. <a id="aiOauthPasteLink" href="#" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open login page</a> and sign in.</p>
                                        <p>2. After signing in, the browser opens a <code>localhost</code> page that fails to load. <strong>That is expected.</strong> Copy the full URL from the address bar and paste it here:</p>
                                        <form method="POST" id="aiOauthPasteForm">
                                            @csrf
                                            <textarea name="url" class="form-control" rows="3" placeholder="http://localhost:.../callback?code=...&state=..."></textarea>
                                            <button class="btn btn-success btn-sm" style="margin-top:8px">Finish connecting</button>
                                        </form>
                                    </div>
                                    <div id="aiOauthRedirect" style="display:none">
                                        <p><a id="aiOauthRedirectLink" href="#" class="btn btn-primary btn-sm">Continue to login</a></p>
                                        <p class="text-muted small">You come back to this panel automatically after signing in.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var csrf = '{{ csrf_token() }}', pollTimer = null;
                            var base = '{{ url('/admin/ai/oauth') }}';
                            function show(id) { ['Loading', 'Error', 'Device', 'Paste', 'Redirect'].forEach(function (k) { document.getElementById('aiOauth' + k).style.display = k === id ? '' : 'none'; }); }
                            function fail(msg) { show('Error'); document.getElementById('aiOauthError').textContent = msg; }
                            $('#aiOauthModal').on('hidden.bs.modal', function () { clearTimeout(pollTimer); });
                            document.querySelectorAll('.ai-connect').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    document.getElementById('aiOauthName').textContent = btn.dataset.name;
                                    show('Loading');
                                    $('#aiOauthModal').modal('show');
                                    fetch(base + '/' + encodeURIComponent(btn.dataset.provider) + '/start', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
                                        .then(function (r) { return r.json(); })
                                        .then(function (d) {
                                            if (d.error) return fail(d.error);
                                            if (d.type === 'device') {
                                                document.getElementById('aiOauthDeviceLink').href = d.verification_uri;
                                                document.getElementById('aiOauthCode').textContent = d.user_code || '(none needed)';
                                                show('Device');
                                                var wait = Math.max(3, d.interval || 5) * 1000;
                                                var poll = function () {
                                                    fetch(base + '/poll/' + d.session, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                                                        .then(function (r) { return r.json(); })
                                                        .then(function (p) {
                                                            if (p.status === 'done') { window.location = p.redirect; }
                                                            else if (p.status === 'error') { fail(p.message || 'Login failed.'); }
                                                            else { pollTimer = setTimeout(poll, wait); }
                                                        }).catch(function () { pollTimer = setTimeout(poll, wait); });
                                                };
                                                pollTimer = setTimeout(poll, wait);
                                            } else if (d.type === 'paste') {
                                                document.getElementById('aiOauthPasteLink').href = d.auth_url;
                                                document.getElementById('aiOauthPasteForm').action = base + '/complete/' + d.session;
                                                show('Paste');
                                            } else {
                                                document.getElementById('aiOauthRedirectLink').href = d.auth_url;
                                                show('Redirect');
                                            }
                                        }).catch(function () { fail('Could not start the login.'); });
                                });
                            });
                        });
                    </script>
                @endif

                {{-- MODELS --}}
                @if(in_array($tab, ['models', 'skills'], true))
                    <style>
                        .aim-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #C8BCA0; }
                        .aim-title { font-size: 18px; font-weight: 900; color: #742220; margin: 0; }
                        .aim-count { font-size: 11px; color: rgba(116,34,32,.6); }
                        .aim-info { position: relative; display: inline-flex; }
                        .aim-info-btn { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #742220; color: #742220; background: #FEF9E1; font-size: 12px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: help; padding: 0; }
                        .aim-info-tip { position: absolute; left: -10px; top: calc(100% + 10px); width: 320px; max-width: 80vw; z-index: 30; background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 12px 14px; font-size: 11px; line-height: 1.6; color: #4B3A2F; opacity: 0; visibility: hidden; transition: opacity .15s; pointer-events: none; }
                        .aim-info:hover .aim-info-tip, .aim-info:focus-within .aim-info-tip { opacity: 1; visibility: visible; }
                        .aim-filter { margin-left: auto; width: 240px; max-width: 100%; }
                        .aim-scroll { overflow-y: auto; overflow-x: hidden; padding: 2px 6px 6px 2px; }
                        .aim-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); }
                        .aim-card { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 14px; display: flex; flex-direction: column; gap: 10px; transition: border-color .15s; }
                        .aim-card:hover { border-color: #2D4A3E; }
                        .aim-card.is-default { border-color: #2D4A3E; box-shadow: 3px 3px 0 0 #2D4A3E; }
                        .aim-card.is-off { opacity: .7; }
                        .aim-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
                        .aim-name { font-size: 13px; font-weight: 900; color: #742220; word-break: break-word; }
                        .aim-id { font-size: 10px; color: rgba(116,34,32,.6); word-break: break-all; margin-top: 2px; }
                        .aim-facts { display: flex; gap: 14px; font-size: 11px; color: #4B3A2F; }
                        .aim-facts b { font-size: 9px; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.6); margin-right: 4px; }
                        .aim-fields { display: grid; grid-template-columns: 1fr 1fr 80px; gap: 8px; }
                        .aim-fields label { display: block; font-size: 9px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.6); margin: 0 0 4px; }
                        .aim-fields .full { grid-column: 1 / -1; }
                        .aim-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; }
                        .aim-actions form { display: inline; margin: 0; }
                        .aim-card { gap: 12px !important; padding: 16px !important; }
                        .aim-card-top { align-items: center !important; gap: 10px !important; }
                        .aim-avatar { width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px; background: #742220; color: #FEF9E1; display: flex; align-items: center; justify-content: center; }
                        .aim-card.is-default .aim-avatar { background: #2D4A3E; }
                        .aim-avatar .material-icons-round { font-size: 20px; }
                        .aim-name { font-size: 14px !important; line-height: 1.25; }
                        .aim-id { font-family: ui-monospace, monospace; font-size: 10px; margin-top: 3px !important; }
                        .aim-badge { flex-shrink: 0; align-self: flex-start; border-radius: 999px; background: #2D4A3E; color: #FEF9E1; font-size: 9px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; padding: 3px 9px; }
                        .aim-account { display: flex; align-items: center; gap: 6px; background: #FEF9E1; border: 1px solid #C8BCA0; border-radius: 8px; padding: 6px 9px; font-size: 11px; color: #4B3A2F; min-width: 0; }
                        .aim-account .material-icons-round { font-size: 16px; color: #742220; flex-shrink: 0; }
                        .aim-account-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                        .aim-pills { display: flex; flex-wrap: wrap; gap: 6px; }
                        .aim-pill { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; border: 1px solid #C8BCA0; background: #FEF9E1; padding: 3px 9px; font-size: 10px; font-weight: 800; color: #4B3A2F; }
                        .aim-pill .material-icons-round { font-size: 13px; color: rgba(116,34,32,.7); }
                        .aim-pill.ok { border-color: rgba(45,106,79,.45); color: #2D6A4F; }
                        .aim-pill.ok .material-icons-round { color: #2D6A4F; }
                        .aim-actions { align-items: center; flex-wrap: nowrap !important; gap: 5px !important; padding-top: 10px; border-top: 1px dashed #C8BCA0; }
                        .aim-act { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; border: 2px solid #2D4A3E; background: #FEF9E1; color: #2D4A3E; padding: 3px 8px; font-size: 10px; font-weight: 900; letter-spacing: .04em; white-space: nowrap; text-transform: uppercase; cursor: pointer; transition: all .15s; }
                        .aim-act .material-icons-round { font-size: 14px; }
                        .aim-act:hover { background: #2D4A3E; color: #FEF9E1; }
                        .aim-act.danger { border-color: #A03A2F; color: #A03A2F; padding: 3px 6px; }
                        .aim-act.danger:hover { background: #A03A2F; color: #FEF9E1; }
                        .aim-drawer { border: 0; position: relative; }
                        .aim-card:has(.aim-drawer[open]) { position: relative; z-index: 25; }
                        .aim-drawer > summary { list-style: none; cursor: pointer; user-select: none; }
                        .aim-drawer > summary::-webkit-details-marker { display: none; }
                        .aim-drawer > summary:hover { border-color: #2D4A3E; }
                        .aim-acc-count { margin-left: auto; flex-shrink: 0; font-size: 9px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; color: rgba(116,34,32,.6); }
                        .aim-caret { color: #742220; transition: transform .15s; }
                        .aim-drawer[open] > summary { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
                        .aim-drawer[open] .aim-caret { transform: rotate(180deg); }
                        .aim-drawer-body { position: absolute; left: 0; right: 0; top: 100%; z-index: 30; background: #FEF9E1; border: 2px solid #2D4A3E; border-top: 1px solid #C8BCA0; border-radius: 0 0 10px 10px; padding: 6px; display: flex; flex-direction: column; gap: 4px; box-shadow: 4px 4px 0 0 #2D4A3E; }
                        .aim-drawer[open] > summary { border-color: #2D4A3E; border-width: 2px; }
                        .aim-acc-row { display: flex; align-items: center; gap: 8px; padding: 6px; border-radius: 6px; }
                        .aim-acc-row:hover { background: #F5EFD5; }
                        .aim-acc-row form { margin: 0; display: inline; }
                        .aim-acc-pos { width: 20px; height: 20px; flex-shrink: 0; border-radius: 999px; border: 2px solid #C8BCA0; color: #742220; font-size: 10px; font-weight: 900; display: flex; align-items: center; justify-content: center; }
                        .aim-acc-pos.primary { background: #2D4A3E; border-color: #2D4A3E; color: #FEF9E1; }
                        .aim-acc-name { font-size: 11px; font-weight: 800; color: #4B3A2F; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                        .aim-acc-role { font-size: 9px; color: rgba(116,34,32,.6); }
                        .aim-icon-btn { width: 24px; height: 24px; flex-shrink: 0; border-radius: 999px; border: 1px solid #C8BCA0; background: #FEF9E1; color: #2D4A3E; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; }
                        .aim-icon-btn .material-icons-round { font-size: 14px; }
                        .aim-icon-btn:hover { background: #2D4A3E; color: #FEF9E1; border-color: #2D4A3E; }
                        .aim-icon-btn.is-off { opacity: .3; cursor: default; }
                        .aim-icon-btn.is-off:hover { background: #FEF9E1; color: #2D4A3E; border-color: #C8BCA0; }
                        .aim-icon-btn.danger { color: #A03A2F; }
                        .aim-icon-btn.danger:hover { background: #A03A2F; color: #FEF9E1; border-color: #A03A2F; }
                        .aim-drawer-hint { margin: 2px 6px 4px; font-size: 10px; color: rgba(116,34,32,.6); }
                        .aim-desc { font-size: 11px; line-height: 1.55; color: rgba(75,58,47,.85); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
                        .aim-empty { text-align: center; padding: 28px 14px; border: 2px dashed #C8BCA0; border-radius: 12px; font-size: 12px; color: rgba(116,34,32,.6); }
                    </style>
                @endif
                @if($tab === 'models')
                    
                    @php($enabledModels = $models->where('enabled', true))
                    @php($availableModels = $models->where('enabled', false))
                    <div class="aim-head">
                        <h3 class="aim-title">Models</h3>
                        <span class="aim-info" tabindex="0">
                            <button type="button" class="aim-info-btn" aria-label="How models work">!</button>
                            <span class="aim-info-tip">These are the models users can choose in the chat. The <strong>default</strong> model is used first; if it fails, the next models (by priority, lower first) are tried automatically. Turn off <em>Tools</em> for models that do not support function calling (they can still chat, but cannot look at servers). Use <strong>Add model</strong> to offer another model from a connected provider.</span>
                        </span>
                        <span class="aim-count">{{ $enabledModels->unique('model_id')->count() }} in use · {{ $availableModels->count() }} available</span>
                        <input type="text" class="form-control input-sm aim-filter" placeholder="Filter models..." oninput="var q=this.value.toLowerCase();document.querySelectorAll('.ai-model-row').forEach(function(r){r.style.display=r.dataset.search.indexOf(q)>-1?'':'none'})">
                        <button type="button" class="btn btn-primary btn-sm" id="aimAdd" @if($availableModels->isEmpty()) disabled @endif><i class="fa fa-plus"></i> Add model</button>
                    </div>
                    <div class="aim-scroll" data-as-fill>
                        @if($enabledModels->isEmpty())
                            <div class="aim-empty">{{ $models->isEmpty() ? 'No models yet. Connect a provider first.' : 'No models in use. Click Add model to pick one.' }}</div>
                        @else
                            <div class="aim-grid">
                                @php($groups = $enabledModels->sortBy([['priority', 'asc'], ['id', 'asc']])->groupBy('model_id')->sortBy(fn ($g) => [$g->contains('is_default', true) ? 0 : 1, $g->min('priority')]))
                                @foreach($groups as $modelId => $rows)
                                    @php($model = $rows->first())
                                    @php($split = fn ($name) => preg_match('/^(.*?)\s*\(([^)]*@[^)]*)\)\s*$/', $name, $pm) ? [$pm[1], $pm[2]] : [$name, null])
                                    @php($account = $split($model->provider->name))
                                    <div class="aim-card ai-model-row @if($rows->contains('is_default', true)) is-default @endif" data-search="{{ strtolower($model->model_id . ' ' . $rows->map(fn ($r) => $r->provider->name)->implode(' ') . ' ' . $model->label) }}">
                                        <div class="aim-card-top">
                                            <span class="aim-avatar"><span class="material-icons-round">{{ $rows->contains('is_default', true) ? 'star' : 'psychology' }}</span></span>
                                            <div style="min-width:0;flex:1">
                                                <div class="aim-name">{{ $model->label ?: $model->model_id }}</div>
                                                <div class="aim-id">{{ $model->model_id }}</div>
                                            </div>
                                            @if($rows->contains('is_default', true))<span class="aim-badge">Default</span>@endif
                                        </div>
                                        <details class="aim-drawer">
                                            <summary class="aim-account" title="{{ $model->provider->name }}">
                                                <span class="material-icons-round">account_circle</span>
                                                <span class="aim-account-text"><b>{{ $account[0] }}</b>@if($account[1]) · {{ $account[1] }}@endif</span>
                                                <span class="aim-acc-count">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('account', $rows->count()) }}</span>
                                                <span class="material-icons-round aim-caret">expand_more</span>
                                            </summary>
                                            <div class="aim-drawer-body">
                                                @foreach($rows->values() as $i => $row)
                                                    @php($acc = $split($row->provider->name))
                                                    @php($ids = $rows->pluck('id')->values()->all())
                                                    <div class="aim-acc-row">
                                                        <span class="aim-acc-pos {{ $i === 0 ? 'primary' : '' }}">{{ $i + 1 }}</span>
                                                        <div style="min-width:0;flex:1">
                                                            <div class="aim-acc-name">{{ $acc[1] ?: $acc[0] }}</div>
                                                            <div class="aim-acc-role">{{ $i === 0 ? 'Used first' : 'Fallback ' . $i }}</div>
                                                        </div>
                                                        @if($rows->count() > 1)
                                                            @foreach([['up', -1, 'arrow_upward', $i > 0], ['down', 1, 'arrow_downward', $i < count($ids) - 1]] as [$dir, $step, $icon, $can])
                                                                @if($can)
                                                                    @php($order = $ids)
                                                                    @php([$order[$i], $order[$i + $step]] = [$order[$i + $step], $order[$i]])
                                                                    <form method="POST" action="{{ route('admin.ai.models.order') }}">@csrf
                                                                        @foreach($order as $oid)<input type="hidden" name="ids[]" value="{{ $oid }}">@endforeach
                                                                        <button class="aim-icon-btn" title="Move {{ $dir }}"><span class="material-icons-round">{{ $icon }}</span></button>
                                                                    </form>
                                                                @else
                                                                    <span class="aim-icon-btn is-off"><span class="material-icons-round">{{ $icon }}</span></span>
                                                                @endif
                                                            @endforeach
                                                            <form method="POST" action="{{ route('admin.ai.models.update', $row->id) }}" onsubmit="return confirm('Stop using this account for {{ $model->label ?: $model->model_id }}?')">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="label" value="{{ $row->label }}">
                                                                <input type="hidden" name="enabled" value="0">
                                                                <input type="hidden" name="supports_tools" value="{{ (int) $row->supports_tools }}">
                                                                <input type="hidden" name="priority" value="{{ $row->priority }}">
                                                                <button class="aim-icon-btn danger" title="Remove this account"><span class="material-icons-round">close</span></button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                @if($rows->count() === 1)
                                                    <p class="aim-drawer-hint">No fallback yet. Use <b>Add model</b> and pick {{ $model->label ?: $model->model_id }} from another account.</p>
                                                @endif
                                            </div>
                                        </details>
                                        <div class="aim-pills">
                                            <span class="aim-pill {{ $model->supports_tools ? 'ok' : '' }}"><span class="material-icons-round">{{ $model->supports_tools ? 'build' : 'chat' }}</span>{{ $model->supports_tools ? 'Tools on' : 'Chat only' }}</span>
                                            <span class="aim-pill"><span class="material-icons-round">low_priority</span>Priority {{ $model->priority }}</span>
                                        </div>
                                        <div class="aim-actions">
                                            <button type="button" class="aim-act aim-edit" data-model="{{ json_encode(['id' => $model->id, 'label' => $model->label, 'supports_tools' => (int) $model->supports_tools, 'priority' => $model->priority, 'name' => $model->model_id . ' · ' . $rows->count() . ' account(s)']) }}"><span class="material-icons-round">edit</span>Edit</button>
                                            <form method="POST" action="{{ route('admin.ai.models.test', $model->id) }}">@csrf<button class="aim-act"><span class="material-icons-round">play_arrow</span>Test</button></form>
                                            @unless($rows->contains('is_default', true))
                                                <form method="POST" action="{{ route('admin.ai.models.default', $model->id) }}">@csrf<button class="aim-act"><span class="material-icons-round">star_outline</span>Default</button></form>
                                            @endunless
                                            <form method="POST" action="{{ route('admin.ai.models.update', $model->id) }}" onsubmit="return confirm('Remove {{ $model->label ?: $model->model_id }} from the chat{{ $rows->count() > 1 ? ' on all ' . $rows->count() . ' accounts' : '' }}?')" style="margin-left:auto" class="aim-remove-all" data-ids="{{ $rows->pluck('id')->implode(',') }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="label" value="{{ $model->label }}">
                                                <input type="hidden" name="enabled" value="0">
                                                <input type="hidden" name="supports_tools" value="{{ (int) $model->supports_tools }}">
                                                <input type="hidden" name="priority" value="{{ $model->priority }}">
                                                <input type="hidden" name="group_disable" value="1">
                                                <button class="aim-act danger" title="Remove from chat"><span class="material-icons-round">delete_outline</span></button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="modal fade" id="aimModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog" role="document">
                            <form class="modal-content" method="POST" id="aimForm" action="">
                                @csrf @method('PATCH')
                                <input type="hidden" name="enabled" value="1">
                                <input type="hidden" name="group" value="1">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    <h4 class="modal-title" id="aimModalTitle">Add model</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group" id="aimPickGroup">
                                        <label class="control-label">Model</label>
                                        <p class="text-muted small" style="margin:0 0 6px">Grouped by connected account. <span id="aimAccount"></span></p>
                                        <select class="form-control" id="aimPick">
                                            @foreach($availableModels->groupBy('provider.name') as $account => $group)
                                                <optgroup label="{{ $account }}">
                                                    @foreach($group->sortBy(fn ($m) => $m->label ?: $m->model_id) as $m)
                                                        <option value="{{ $m->id }}" data-label="{{ $m->label }}" data-tools="{{ (int) $m->supports_tools }}" data-priority="{{ ($g = $enabledModels->where('model_id', $m->model_id))->isNotEmpty() ? min(10000, $g->max('priority') + 1) : $m->priority }}" data-account="{{ $account }}">{{ $m->label ?: $m->model_id }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <p class="text-muted small" id="aimEditName" style="display:none"></p>
                                    <div class="form-group">
                                        <label class="control-label">Label shown to users</label>
                                        <input class="form-control" name="label" id="aimLabel" maxlength="80">
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-xs-6">
                                            <label class="control-label">Tools</label>
                                            <select class="form-control" name="supports_tools" id="aimTools"><option value="1">Yes</option><option value="0">No</option></select>
                                        </div>
                                        <div class="form-group col-xs-6">
                                            <label class="control-label">Priority</label>
                                            <input type="number" min="0" max="10000" class="form-control" name="priority" id="aimPriority">
                                        </div>
                                    </div>
                                    <p class="text-muted small">Lower priority numbers are tried first when the default model fails.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-sm" id="aimSubmit">Add model</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('click', function (e) {
                            document.querySelectorAll('.aim-drawer[open]').forEach(function (d) {
                                if (!d.contains(e.target)) d.removeAttribute('open');
                            });
                        });
                        document.addEventListener('DOMContentLoaded', function () {
                            var base = @json(url('/admin/ai/models')) + '/';
                            var form = document.getElementById('aimForm');
                            var pick = document.getElementById('aimPick');
                            function fill(id, label, tools, priority) {
                                form.action = base + id;
                                document.getElementById('aimLabel').value = label || '';
                                document.getElementById('aimTools').value = String(tools);
                                document.getElementById('aimPriority').value = priority;
                            }
                            function fromPick() {
                                var o = pick.options[pick.selectedIndex];
                                if (o) {
                                    fill(o.value, o.dataset.label, o.dataset.tools, o.dataset.priority);
                                    document.getElementById('aimAccount').textContent = 'Account: ' + o.dataset.account;
                                }
                            }
                            if (pick) pick.addEventListener('change', fromPick);
                            var add = document.getElementById('aimAdd');
                            if (add) add.addEventListener('click', function () {
                                document.getElementById('aimModalTitle').textContent = 'Add model';
                                document.getElementById('aimSubmit').textContent = 'Add model';
                                document.getElementById('aimPickGroup').style.display = '';
                                document.getElementById('aimEditName').style.display = 'none';
                                fromPick();
                                $('#aimModal').modal('show');
                            });
                            document.querySelectorAll('.aim-edit').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    var m = JSON.parse(btn.dataset.model);
                                    document.getElementById('aimModalTitle').textContent = 'Edit model';
                                    document.getElementById('aimSubmit').textContent = 'Save';
                                    document.getElementById('aimPickGroup').style.display = 'none';
                                    var n = document.getElementById('aimEditName');
                                    n.textContent = m.name;
                                    n.style.display = '';
                                    fill(m.id, m.label, m.supports_tools, m.priority);
                                    $('#aimModal').modal('show');
                                });
                            });
                        });
                    </script>
                @endif

                {{-- AUDIT LOG --}}
                @if($tab === 'audit')
                    @php($eventMeta = [
                        'chat.message' => ['Message', 'chat', 'info'],
                        'tool.read' => ['Read', 'visibility', 'mute'],
                        'action.proposed' => ['Proposed', 'pending_actions', 'warn'],
                        'action.executed' => ['Approved & ran', 'check_circle', 'ok'],
                        'action.failed' => ['Approved, failed', 'error', 'bad'],
                        'action.rejected' => ['Rejected', 'block', 'bad'],
                    ])
                    <style>
                        .aud-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px solid #C8BCA0; }
                        .aud-title { font-size: 18px; font-weight: 900; color: #742220; margin: 0; }
                        .aud-note { font-size: 11px; color: rgba(116,34,32,.6); }
                        .aud-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
                        .aud-filters .form-control { width: 160px !important; min-width: 0; }
                        .aud-scroll { overflow: auto; border: 2px solid #C8BCA0; border-radius: 12px; background: #F5EFD5; }
                        .aud-table { width: 100%; border-collapse: collapse; font-size: 12px; }
                        .aud-table th { position: sticky; top: 0; z-index: 1; background: #EFE7C9; text-align: left; padding: 9px 10px; font-size: 10px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.75); border-bottom: 2px solid #2D4A3E; white-space: nowrap; }
                        .aud-table td { padding: 8px 10px; border-top: 1px solid #E2D8BD; vertical-align: top; color: #4B3A2F; }
                        .aud-table tr:hover td { background: rgba(254,249,225,.8); }
                        .aud-ev { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 2px 9px; font-size: 10px; font-weight: 800; border: 1px solid; white-space: nowrap; }
                        .aud-ev .material-icons-round { font-size: 13px; }
                        .aud-ev.warn { color: #9A5B00; border-color: rgba(202,138,4,.45); background: rgba(202,138,4,.12); }
                        .aud-ev.info { color: #2D4A3E; border-color: rgba(45,74,62,.35); background: rgba(45,74,62,.08); }
                        .aud-ev.ok { color: #2D6A4F; border-color: rgba(45,106,79,.4); background: rgba(45,106,79,.1); }
                        .aud-ev.bad { color: #A03A2F; border-color: rgba(160,58,47,.4); background: rgba(160,58,47,.08); }
                        .aud-ev.mute { color: #6B6257; border-color: rgba(107,98,87,.35); background: rgba(107,98,87,.08); }
                        .aud-target { font-family: ui-monospace, Menlo, monospace; font-size: 11px; word-break: break-all; max-width: 340px; }
                        .aud-sub { font-size: 10px; color: rgba(116,34,32,.55); }
                        .aud-diff { margin: 6px 0 0; max-height: 220px; overflow: auto; background: #FEF9E1; border: 1px solid #C8BCA0; border-radius: 8px; padding: 6px 8px; font-size: 11px; white-space: pre; }
                        .aud-diff .add { color: #2D6A4F; } .aud-diff .del { color: #A03A2F; }
                        .aud-pager { margin-left: auto; display: inline-flex; align-items: center; gap: 8px; font-size: 11px; color: rgba(116,34,32,.6); }
                        .aud-where { display: inline-flex; align-items: center; gap: 5px; border-radius: 8px; padding: 3px 8px; font-size: 10.5px; font-weight: 800; white-space: nowrap; border: 1px solid; }
                        .aud-where .material-icons-round { font-size: 14px; }
                        .aud-where.panel { color: #742220; border-color: rgba(116,34,32,.3); background: rgba(116,34,32,.06); }
                        .aud-where.server { color: #2D4A3E; border-color: rgba(45,74,62,.35); background: rgba(45,74,62,.08); }
                        .aud-where.discord { color: #4752C4; border-color: rgba(88,101,242,.4); background: rgba(88,101,242,.08); }
                    </style>
                    <div class="aud-head">
                        <h3 class="aud-title">Audit Log</h3>
                        <span class="aud-note">Who used {{ config('ai.persona_name') ?: 'Anney' }}, on which server, and every action it ran. Chat text is never stored here (PDPA). Kept {{ \Pterodactyl\Models\Ai\AiAuditLog::RETENTION_DAYS }} days.</span>
                    </div>
                    <form method="GET" action="{{ route('admin.ai') }}" class="aud-filters">
                        <input type="hidden" name="tab" value="audit">
                        <select name="event" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="">All events</option>
                            <option value="chat" @selected(request('event') === 'chat')>Messages</option>
                            <option value="tool" @selected(request('event') === 'tool')>Reads</option>
                            <option value="action" @selected(request('event') === 'action')>All actions</option>
                            @foreach(['action.proposed', 'action.executed', 'action.failed', 'action.rejected'] as $e)
                                <option value="{{ $e }}" @selected(request('event') === $e)>{{ $eventMeta[$e][0] }}</option>
                            @endforeach
                        </select>
                        <select name="where" class="form-control input-sm" onchange="this.form.submit()">
                            <option value="">Everywhere</option>
                            @foreach(['panel' => 'Panel (all)', 'panel-general' => 'Panel · general chat', 'panel-server' => 'Panel · server chat', 'discord' => 'Discord (all)', 'discord-dm' => 'Discord · DM', 'discord-channel' => 'Discord · channels'] as $k => $v)
                                <option value="{{ $k }}" @selected(request('where') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="user" value="{{ request('user') }}" class="form-control input-sm" placeholder="User id or email">
                        <input type="text" name="server" value="{{ request('server') }}" class="form-control input-sm" placeholder="Server id or name">
                        <button class="btn btn-sm btn-primary">Filter</button>
                        @if(request()->hasAny(['event', 'where', 'user', 'server']))<a href="{{ route('admin.ai', ['tab' => 'audit']) }}" class="btn btn-sm btn-default">Reset</a>@endif
                        <span class="aud-pager">
                            <span>{{ number_format($audit->total()) }} entries</span>
                            @if($audit->previousPageUrl())<a class="btn btn-xs btn-default" href="{{ $audit->previousPageUrl() }}">‹ Newer</a>@endif
                            <span>{{ $audit->currentPage() }} / {{ max(1, $audit->lastPage()) }}</span>
                            @if($audit->nextPageUrl())<a class="btn btn-xs btn-default" href="{{ $audit->nextPageUrl() }}">Older ›</a>@endif
                        </span>
                    </form>
                    <div class="aud-scroll" data-as-fill>
                        <table class="aud-table">
                            <thead><tr><th>Time</th><th>Where</th><th>Event</th><th>User</th><th>Server</th><th>Tool / target</th><th>Model</th></tr></thead>
                            <tbody>
                            @forelse($audit as $log)
                                @php($ev = $eventMeta[$log->event] ?? [$log->event, 'info', 'mute'])
                                <tr>
                                    @php($loc = $log->location ?: ($log->channel === 'discord' ? 'Discord' : 'Panel'))
                                    @php($locParts = explode(' · ', $loc, 2))
                                    @php($locKind = $log->channel === 'discord' ? ['discord', str_contains($loc, 'DM') ? 'mail' : 'forum'] : (str_contains($loc, 'server') ? ['server', 'dns'] : ['panel', 'chat']))
                                    <td style="white-space:nowrap">{{ $log->created_at?->format('d M') }}<div class="aud-sub">{{ $log->created_at?->format('H:i:s') }}</div></td>
                                    <td><span class="aud-where {{ $locKind[0] }}" title="{{ $loc }}"><span class="material-icons-round">{{ $locKind[1] }}</span>{{ $locParts[0] }}</span>@if(isset($locParts[1]))<div class="aud-sub" style="margin-top:3px">{{ $locParts[1] }}</div>@endif</td>
                                    <td><span class="aud-ev {{ $ev[2] }}"><span class="material-icons-round">{{ $ev[1] }}</span>{{ $ev[0] }}</span>
                                        @if($log->event === 'chat.message')<div class="aud-sub">{{ $log->status }} · {{ $log->detail['chars'] ?? 0 }} chars{{ !empty($log->detail['attachments']) ? ' · ' . $log->detail['attachments'] . ' file(s) ' . implode(',', $log->detail['attachment_types'] ?? []) : '' }}</div>@endif
                                    </td>
                                    <td>@if($log->user)<a href="{{ route('admin.users.view', $log->user_id) }}">{{ $log->user->username }}</a><div class="aud-sub">#{{ $log->user_id }}</div>@else<span class="aud-sub">{{ $log->user_id ? '#' . $log->user_id : 'unlinked' }}</span>@endif</td>
                                    <td>@if($log->server)<a href="{{ route('admin.servers.view', $log->server_id) }}">{{ $log->server->name }}</a><div class="aud-sub">#{{ $log->server_id }} · {{ $log->server->uuidShort }}</div>@elseif($log->server_id)<span class="aud-sub">#{{ $log->server_id }} (deleted)</span>@else<span class="aud-sub">—</span>@endif</td>
                                    <td>
                                        @if($log->tool)<strong>{{ $log->tool }}</strong>@endif
                                        @if($log->target)<div class="aud-target">{{ $log->target }}</div>@endif
                                        @if(!empty($log->detail['error']))<div class="aud-sub" style="color:#A03A2F">{{ $log->detail['error'] }}</div>@endif
                                        @if(!empty($log->detail['diff']))
                                            @php($d = $log->detail['diff'])
                                            <details><summary class="aud-sub" style="cursor:pointer">{{ $d['new_file'] ? 'new file' : 'changed' }} · +{{ $d['lines_added'] }} / −{{ $d['lines_removed'] }} lines · {{ number_format($d['bytes_before']) }} → {{ number_format($d['bytes_after']) }} bytes</summary>
                                                <div class="aud-diff">@foreach(explode("\n", (string) $d['diff']) as $line)<span class="{{ str_starts_with($line, '+') ? 'add' : (str_starts_with($line, '-') ? 'del' : '') }}">{{ $line }}</span>
@endforeach</div>
                                            </details>
                                        @endif
                                        @if(!$log->tool && !$log->target)<span class="aud-sub">—</span>@endif
                                    </td>
                                    <td class="aud-sub">{{ $log->model ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" style="text-align:center;padding:30px;color:rgba(116,34,32,.6)">No audit entries yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- SKILLS --}}
                @if($tab === 'skills')
                    <div class="aim-head">
                        <h3 class="aim-title">Skills</h3>
                        <span class="aim-info" tabindex="0">
                            <button type="button" class="aim-info-btn" aria-label="How skills work">!</button>
                            <span class="aim-info-tip">Skills are instructions {{ config('ai.persona_name') ?: 'Anney' }} follows. Every enabled skill is added to each chat (panel, Discord or both, as chosen), so keep them short and relevant. You can write one or import a file; imported skills are reviewed by {{ config('ai.persona_name') ?: 'Anney' }} first.</span>
                        </span>
                        <span class="aim-count">{{ $skills->where('enabled', true)->count() }} on · {{ number_format($skills->where('enabled', true)->sum(fn ($k) => strlen($k->content)) / 1024, 1) }} KB added to each chat</span>
                        <input type="text" class="form-control input-sm aim-filter" placeholder="Filter skills..." oninput="var q=this.value.toLowerCase();document.querySelectorAll('.ai-skill-row').forEach(function(r){r.style.display=r.dataset.search.indexOf(q)>-1?'':'none'})">
                        <button type="button" class="btn btn-primary btn-sm" id="aiSkillCreate"><i class="fa fa-plus"></i> Create skill</button>
                    </div>
                    <div class="aim-scroll" data-as-fill>
                        @if($skills->isEmpty())
                            <div class="aim-empty">No skills yet. Click Create skill to write or import one.</div>
                        @else
                            <div class="aim-grid">
                                @foreach($skills as $skill)
                                    <div class="aim-card ai-skill-row @unless($skill->enabled) is-off @endunless" data-search="{{ strtolower($skill->name . ' ' . $skill->slug . ' ' . $skill->description) }}">
                                        <div class="aim-card-top">
                                            <span class="aim-avatar" @unless($skill->enabled) style="background:#8a7f6a" @endunless><span class="material-icons-round">auto_awesome</span></span>
                                            <div style="min-width:0;flex:1">
                                                <div class="aim-name">{{ $skill->name }}</div>
                                                <div class="aim-id">{{ $skill->slug }}</div>
                                            </div>
                                            <span class="aim-badge" @unless($skill->enabled) style="background:#8a7f6a" @endunless>{{ $skill->enabled ? 'On' : 'Off' }}</span>
                                        </div>
                                        <div class="aim-desc">{{ $skill->description ?: 'No description.' }}</div>
                                        <div class="aim-pills">
                                            <span class="aim-pill"><span class="material-icons-round">{{ ['both' => 'devices', 'panel' => 'dashboard', 'discord' => 'forum'][$skill->scope] ?? 'devices' }}</span>{{ ['both' => 'Panel & Discord', 'panel' => 'Panel', 'discord' => 'Discord'][$skill->scope] ?? $skill->scope }}</span>
                                            <span class="aim-pill"><span class="material-icons-round">description</span>{{ number_format(strlen($skill->content) / 1024, 1) }} KB</span>
                                            @if($skill->source_url)<span class="aim-pill" title="{{ $skill->source_url }}"><span class="material-icons-round">download</span>Imported</span>@endif
                                        </div>
                                        <div class="aim-actions">
                                            <button type="button" class="aim-act ai-skill-edit" data-skill="{{ json_encode($skill->only(['id', 'name', 'slug', 'description', 'scope', 'enabled', 'content', 'source_url'])) }}"><span class="material-icons-round">edit</span>Edit</button>
                                            <form method="POST" action="{{ route('admin.ai.skills.destroy', $skill->id) }}" onsubmit="return confirm('Delete the skill {{ $skill->name }}?')" style="margin-left:auto">@csrf @method('DELETE')<button class="aim-act danger" title="Delete skill"><span class="material-icons-round">delete_outline</span></button></form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="modal fade" id="aiSkillModal" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title" id="aiSkillTitle">Create skill</h4>
                                </div>
                                <div class="modal-body">
                                    <ul class="nav nav-tabs" id="aiSkillTabs" style="margin-bottom:14px">
                                        <li class="active"><a href="#" data-pane="write">Write</a></li>
                                        <li><a href="#" data-pane="import">Import file</a></li>
                                    </ul>

                                    <form method="POST" id="aiSkillForm" data-pane="write" action="{{ route('admin.ai.skills.store') }}">
                                        @csrf
                                        <input type="hidden" name="_method" value="POST" id="aiSkillMethod">
                                        <div class="row">
                                            <div class="form-group col-md-6"><label>Name</label><input name="name" class="form-control" required></div>
                                            <div class="form-group col-md-6"><label>Slug <span class="field-optional"></span></label><input name="slug" class="form-control"></div>
                                        </div>
                                        <div class="form-group"><label>Short description</label><input name="description" class="form-control"></div>
                                        <div class="row">
                                            <div class="form-group col-md-6"><label>Used in</label>
                                                <select name="scope" class="form-control">
                                                    <option value="both">Panel &amp; Discord</option>
                                                    <option value="panel">Panel only</option>
                                                    <option value="discord">Discord only</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-6"><label>Status</label>
                                                <select name="enabled" class="form-control">
                                                    <option value="1">Enabled</option>
                                                    <option value="0">Disabled</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group"><label>Instructions (Markdown)</label><textarea name="content" rows="14" class="form-control code" required></textarea></div>
                                        <p class="text-muted small" id="aiSkillSource" style="display:none"></p>
                                        <div class="text-right"><button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button> <button class="btn btn-primary btn-sm">Save skill</button></div>
                                    </form>

                                    <div data-pane="import" style="display:none">
                                        <form id="aiSkillImportForm" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <label>Skill file</label>
                                                <input type="file" name="file" class="form-control" accept=".md,.markdown,.txt,.zip" required>
                                                <p class="text-muted small" style="margin-top:6px">A <code>SKILL.md</code>, a Markdown/text file, or a <code>.zip</code> containing a SKILL.md plus reference <code>.md</code> files (max 2&nbsp;MB). Scripts and other files in a zip are ignored and never run.</p>
                                            </div>
                                            <div class="form-group" style="max-width:260px"><label>Used in</label>
                                                <select name="scope" class="form-control">
                                                    <option value="both">Panel &amp; Discord</option>
                                                    <option value="panel">Panel only</option>
                                                    <option value="discord">Discord only</option>
                                                </select>
                                            </div>
                                            <p class="small">{{ config('ai.persona_name') ?: 'Anney' }} reviews the file first (safety, relevance to game-server support, prompt size) and adapts it to her tools. It is only added if the review passes.</p>
                                            <div id="aiSkillImportStatus"></div>
                                            <div class="text-right"><button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button> <button class="btn btn-success btn-sm" id="aiSkillImportBtn">Review &amp; import</button></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var modal = $('#aiSkillModal'), form = document.getElementById('aiSkillForm');
                            var storeUrl = '{{ route('admin.ai.skills.store') }}', updateBase = '{{ url('/admin/ai/skills') }}/';
                            var importUrl = '{{ route('admin.ai.skills.import') }}', csrf = '{{ csrf_token() }}', busy = false;

                            function pane(name) {
                                document.querySelectorAll('#aiSkillTabs li').forEach(function (li) { li.classList.toggle('active', li.firstElementChild.dataset.pane === name); });
                                document.querySelectorAll('#aiSkillModal [data-pane]').forEach(function (el) { if (el.tagName !== 'A') el.style.display = el.dataset.pane === name ? '' : 'none'; });
                            }
                            document.querySelectorAll('#aiSkillTabs a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); if (!busy) pane(a.dataset.pane); }); });

                            function open(skill) {
                                form.reset();
                                form.action = skill ? updateBase + skill.id : storeUrl;
                                document.getElementById('aiSkillMethod').value = skill ? 'PATCH' : 'POST';
                                document.getElementById('aiSkillTitle').textContent = skill ? 'Edit: ' + skill.name : 'Create skill';
                                document.getElementById('aiSkillTabs').style.display = skill ? 'none' : '';
                                ['name', 'slug', 'description', 'scope', 'content'].forEach(function (k) { form.elements[k].value = skill ? (skill[k] || '') : (k === 'scope' ? 'both' : ''); });
                                form.elements.enabled.value = skill ? (skill.enabled ? '1' : '0') : '1';
                                var src = document.getElementById('aiSkillSource');
                                src.style.display = skill && skill.source_url ? '' : 'none';
                                src.textContent = skill && skill.source_url ? 'Source: ' + skill.source_url : '';
                                document.getElementById('aiSkillImportStatus').innerHTML = '';
                                pane('write');
                                modal.modal('show');
                            }
                            document.getElementById('aiSkillCreate').addEventListener('click', function () { open(null); });
                            document.querySelectorAll('.ai-skill-edit').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.skill)); }); });

                            modal.on('hide.bs.modal', function (e) { if (busy) e.preventDefault(); });

                            document.getElementById('aiSkillImportForm').addEventListener('submit', function (e) {
                                e.preventDefault();
                                var status = document.getElementById('aiSkillImportStatus'), btn = document.getElementById('aiSkillImportBtn');
                                var data = new FormData(this);
                                busy = true; btn.disabled = true;
                                status.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Reviewing the skill with {{ config('ai.persona_name') ?: 'Anney' }}… this can take up to a minute. Please keep this window open.</div>';
                                fetch(importUrl, { method: 'POST', body: data, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
                                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                                    .then(function (res) {
                                        busy = false; btn.disabled = false;
                                        var box = document.createElement('div');
                                        box.className = 'alert ' + (res.ok ? 'alert-success' : 'alert-danger');
                                        box.textContent = res.body.message || (res.ok ? 'Skill added.' : 'Import failed.');
                                        status.innerHTML = ''; status.appendChild(box);
                                        if (res.ok) setTimeout(function () { window.location = '{{ route('admin.ai', ['tab' => 'skills']) }}'; }, 1600);
                                    })
                                    .catch(function () {
                                        busy = false; btn.disabled = false;
                                        status.innerHTML = '<div class="alert alert-danger">The review request failed. Try again.</div>';
                                    });
                            });
                        });
                    </script>
                @endif

                {{-- DISCORD --}}
                @if($tab === 'discord')
                    @php($dEnabled = filter_var(config('ai.discord.enabled'), FILTER_VALIDATE_BOOLEAN))
                    <div class="row">
                        <div class="col-md-7">
                            <form method="POST" action="{{ route('admin.ai.settings') }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="tab" value="discord">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>Status</label>
                                        <select name="ai:discord:enabled" class="form-control">
                                            <option value="true" @if($dEnabled) selected @endif>Enabled</option>
                                            <option value="false" @if(!$dEnabled) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Bot token (separate bot for {{ config('ai.persona_name') ?: 'Anney' }})</label>
                                        <input name="ai:discord:bot_token" class="form-control" autocomplete="new-password" placeholder="{{ filled(config('ai.discord.bot_token')) ? 'Stored securely. Leave blank to keep.' : 'paste bot token' }}">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>Model for Discord</label>
                                        <select name="ai:discord:model_id" class="form-control">
                                            <option value="">Same as panel default</option>
                                            @foreach($usableModels as $m)
                                                <option value="{{ $m->id }}" @if((string) config('ai.discord.model_id') === (string) $m->id) selected @endif>{{ $m->displayName() }} ({{ $m->provider->name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Only answer in these channel IDs <span class="field-optional"></span></label>
                                        <input name="ai:discord:channel_ids" class="form-control" value="{{ config('ai.discord.channel_ids') }}" placeholder="123..., 456...">
                                    </div>
                                </div>
                                <p class="text-muted small">In server channels the bot only answers when it is @mentioned or when someone replies to one of its messages. Direct messages are always answered. Leave the channel list empty to allow every channel, or list channel IDs to limit where it answers. It is chat only. People who linked Discord to their panel account are recognised with the same memories as in the panel.</p>
                                <div class="form-group" style="max-width:360px">
                                    <label>Bridge secret</label>
                                    <select name="regenerate_secret" class="form-control">
                                        <option value="0" selected>Keep current secret</option>
                                        <option value="1">Regenerate (bot service must be updated)</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary btn-sm pull-right">Save</button>
                                <div class="clearfix"></div>
                            </form>
                        </div>
                        <div class="col-md-5">
                            <div class="box box-default">
                                <div class="box-header with-border"><h3 class="box-title">Bot service</h3></div>
                                <div class="box-body small">
                                    <p>Runs as the <code>anney-bot</code> systemd service on this machine and talks to the panel through <code>/api/internal/ai/discord/*</code>.</p>
                                    <p>Bridge secret: @if(filled(config('ai.discord.bridge_secret')))<span class="label label-success">set</span>@else<span class="label label-warning">not set - save once to generate</span>@endif</p>
                                    <p>In the Discord Developer Portal enable the <strong>Message Content</strong> intent for the bot, then invite it with the <em>bot</em> scope and the Send Messages + Read Message History permissions.</p>
                                    <p>After changing the token: <code>systemctl restart anney-bot</code></p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
