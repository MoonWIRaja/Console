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
                @foreach(['general' => 'General & Personality', 'providers' => 'Providers', 'models' => 'Models', 'skills' => 'Skills', 'discord' => 'Discord Bot'] as $key => $label)
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
                                    <div class="form-group col-md-4">
                                        <label class="control-label">Status</label>
                                        <select name="ai:enabled" class="form-control">
                                            <option value="true" @if($aiEnabled) selected @endif>Enabled</option>
                                            <option value="false" @if(!$aiEnabled) selected @endif>Disabled</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="control-label">Assistant name</label>
                                        <input type="text" name="ai:persona_name" class="form-control" value="{{ old('ai:persona_name', config('ai.persona_name')) }}">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="control-label">Max tool steps per reply</label>
                                        <input type="number" min="1" max="30" name="ai:max_steps" class="form-control" value="{{ old('ai:max_steps', config('ai.max_steps', 12)) }}">
                                    </div>
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
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover">
                                <thead><tr><th>Account</th><th>Type</th><th>Models</th><th>Token</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                @forelse($providers as $provider)
                                    @php($def = $provider->definition())
                                    <tr>
                                        <td><strong>{{ $provider->name }}</strong>@if($provider->last_error)<br><small class="text-danger">{{ $provider->last_error }}</small>@endif</td>
                                        <td>{{ ($def['group'] ?? '') === 'subscription' ? 'Subscription' : 'Free' }}<br><small class="text-muted">{{ ($def['flow'] ?? '') === 'apikey' ? 'API key' : 'OAuth' }}</small></td>
                                        <td>{{ $provider->models_count }}</td>
                                        <td><small>{{ $provider->token_expires_at ? 'expires ' . $provider->token_expires_at->diffForHumans() : 'no expiry' }}</small></td>
                                        <td>@if($provider->enabled)<span class="label label-success">Enabled</span>@else<span class="label label-default">Disabled</span>@endif</td>
                                        <td class="text-right" style="white-space:nowrap">
                                            <form class="inline-form" method="POST" action="{{ route('admin.ai.providers.sync', $provider->id) }}">@csrf<button class="btn btn-xs btn-info">Reload models</button></form>
                                            <button type="button" class="btn btn-xs btn-default" data-toggle="collapse" data-target="#provider-{{ $provider->id }}">Edit</button>
                                            <form class="inline-form" method="POST" action="{{ route('admin.ai.providers.destroy', $provider->id) }}" onsubmit="return confirm('Disconnect {{ $provider->name }}?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Disconnect</button></form>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="provider-{{ $provider->id }}">
                                        <td colspan="6">
                                            <form method="POST" action="{{ route('admin.ai.providers.update', $provider->id) }}" class="row">
                                                @csrf @method('PATCH')
                                                <div class="form-group col-md-4"><label>Name</label><input class="form-control input-sm" name="name" value="{{ $provider->name }}"></div>
                                                @if(($def['flow'] ?? '') === 'apikey')
                                                    <div class="form-group col-md-4"><label>New API key</label><input class="form-control input-sm" name="api_key" autocomplete="new-password" placeholder="blank keeps the current key"></div>
                                                @endif
                                                <div class="form-group col-md-2"><label>Priority</label><input type="number" class="form-control input-sm" name="priority" value="{{ $provider->priority }}"></div>
                                                <div class="form-group col-md-2"><label>Enabled</label><select class="form-control input-sm" name="enabled"><option value="1" @if($provider->enabled) selected @endif>Yes</option><option value="0" @if(!$provider->enabled) selected @endif>No</option></select></div>
                                                <div class="col-md-12"><button class="btn btn-primary btn-sm">Save</button></div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Nothing connected yet. Pick a provider below.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
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
                @if($tab === 'models')
                    <p class="text-muted">Enable the models users can choose in the chat. The <strong>default</strong> model is used first; if it fails, the next enabled models (by priority) are tried automatically. Turn off <em>Tools</em> for models that do not support function calling (they can still chat, but cannot look at servers).</p>
                    <div class="form-group" style="max-width:320px">
                        <input type="text" class="form-control input-sm" placeholder="Filter models..." oninput="var q=this.value.toLowerCase();document.querySelectorAll('.ai-model-row').forEach(function(r){r.style.display=r.dataset.search.indexOf(q)>-1?'':'none'})">
                    </div>
                    <table class="table table-hover">
                        <thead><tr><th>Model</th><th>Provider</th><th>Label</th><th>Enabled</th><th>Tools</th><th>Priority</th><th></th></tr></thead>
                        <tbody>
                        @forelse($models as $model)
                            <tr class="ai-model-row" data-search="{{ strtolower($model->model_id . ' ' . $model->provider->name . ' ' . $model->label) }}">
                                <form method="POST" action="{{ route('admin.ai.models.update', $model->id) }}" id="model-form-{{ $model->id }}">@csrf @method('PATCH')</form>
                                <td class="model-id">{{ $model->model_id }} @if($model->is_default)<span class="label label-primary">default</span>@endif</td>
                                <td>{{ $model->provider->name }}</td>
                                <td><input form="model-form-{{ $model->id }}" class="form-control input-sm" name="label" value="{{ $model->label }}" style="min-width:140px"></td>
                                <td><select form="model-form-{{ $model->id }}" class="form-control input-sm" name="enabled"><option value="1" @if($model->enabled) selected @endif>Yes</option><option value="0" @if(!$model->enabled) selected @endif>No</option></select></td>
                                <td><select form="model-form-{{ $model->id }}" class="form-control input-sm" name="supports_tools"><option value="1" @if($model->supports_tools) selected @endif>Yes</option><option value="0" @if(!$model->supports_tools) selected @endif>No</option></select></td>
                                <td><input form="model-form-{{ $model->id }}" type="number" class="form-control input-sm" name="priority" value="{{ $model->priority }}" style="width:80px"></td>
                                <td class="text-right" style="white-space:nowrap">
                                    <button form="model-form-{{ $model->id }}" class="btn btn-xs btn-primary">Save</button>
                                    <form class="inline-form" method="POST" action="{{ route('admin.ai.models.test', $model->id) }}">@csrf<button class="btn btn-xs btn-info">Test</button></form>
                                    @unless($model->is_default)
                                        <form class="inline-form" method="POST" action="{{ route('admin.ai.models.default', $model->id) }}">@csrf<button class="btn btn-xs btn-default">Make default</button></form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No models yet. Add a provider first.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                @endif

                {{-- SKILLS --}}
                @if($tab === 'skills')
                    <div class="clearfix" style="margin-bottom:12px">
                        <p class="text-muted pull-left" style="margin:6px 0 0">Skills are instructions {{ config('ai.persona_name') ?: 'Anney' }} follows. Every enabled skill is added to each chat, so keep them short and relevant.</p>
                        <button type="button" class="btn btn-primary btn-sm pull-right" id="aiSkillCreate"><i class="fa fa-plus"></i> Create skill</button>
                    </div>
                    <table class="table table-hover">
                        <thead><tr><th>Skill</th><th>Where</th><th>Size</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse($skills as $skill)
                            <tr>
                                <td><strong>{{ $skill->name }}</strong><br><small class="text-muted">{{ $skill->description ?: $skill->slug }}</small></td>
                                <td>{{ ['both' => 'Panel & Discord', 'panel' => 'Panel', 'discord' => 'Discord'][$skill->scope] ?? $skill->scope }}</td>
                                <td><small>{{ number_format(strlen($skill->content) / 1024, 1) }} KB</small></td>
                                <td>@if($skill->enabled)<span class="label label-success">On</span>@else<span class="label label-default">Off</span>@endif</td>
                                <td class="text-right" style="white-space:nowrap">
                                    <button type="button" class="btn btn-xs btn-default ai-skill-edit" data-skill="{{ json_encode($skill->only(['id', 'name', 'slug', 'description', 'scope', 'enabled', 'content', 'source_url'])) }}">Edit</button>
                                    <form class="inline-form" method="POST" action="{{ route('admin.ai.skills.destroy', $skill->id) }}" onsubmit="return confirm('Delete this skill?')">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Delete</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No skills yet. Click Create skill.</td></tr>
                        @endforelse
                        </tbody>
                    </table>

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
