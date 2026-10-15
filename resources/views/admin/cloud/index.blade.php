@extends('layouts.admin')

@section('title')
    Cloud Storage
@endsection

@section('content-header')
    <h1>Cloud Storage<small>Google Drive accounts that store user uploads.</small></h1>
@endsection

@section('content')
    @php($enabled = filter_var(config('cloud.enabled'), FILTER_VALIDATE_BOOLEAN))
    @php($human = fn ($b) => $b === null ? 'unlimited' : ($b >= 1099511627776 ? round($b / 1099511627776, 2) . ' TB' : ($b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : round($b / 1048576, 1) . ' MB')))
    @php($knownAccounts = $accounts->whereNotNull('quota_checked_at'))
    @php($poolTotal = $knownAccounts->whereNotNull('quota_limit')->sum('quota_limit'))
    @php($poolUsed = $knownAccounts->sum('quota_usage'))
    @php($poolPct = $poolTotal ? min(100, round($poolUsed / $poolTotal * 100)) : 0)

    <style>
        .ca { color: #742220; font-family: var(--font-sans, 'Geist Mono', monospace); }
        .ca-panel { background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 20px; }
        .content-header { display: none !important; }
        .ca-shell { position: relative; margin: -12px -15px -30px; padding: 24px; background: #D6D2C7; display: flex; flex-direction: column; }
        .ca-shell::before { content: ''; position: absolute; inset: 0; pointer-events: none; background-image: repeating-linear-gradient(0deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(60deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px), repeating-linear-gradient(120deg, transparent, transparent 4.5px, rgba(116,34,32,.05) 4.5px, rgba(116,34,32,.05) 5px); }
        .ca-layout { position: relative; z-index: 1; flex: 1 1 auto; display: grid; gap: 24px; grid-template-columns: 1fr; }
        @media (min-width: 992px) { .ca-layout { grid-template-columns: 280px minmax(0, 1fr); } }
        .ca-side { display: flex; flex-direction: column; padding: 18px; }
        .ca-main { min-width: 0; padding: 24px; }
        .ca-new, .ca-new:hover, .ca-new span { color: #FEF9E1 !important; }
        .ca-new { display: flex; align-items: center; justify-content: center; gap: 10px; border-radius: 999px; border: 2px solid #5a1a18; background: #742220; padding: 13px 18px; font-size: 11px; font-weight: 900; letter-spacing: .22em; text-transform: uppercase; box-shadow: 3px 3px 0 0 #5a1a18; text-decoration: none !important; transition: all .2s; }
        .ca-new:hover { transform: translate(-1px,-1px); box-shadow: 5px 5px 0 0 #5a1a18; }
        .ca-new.disabled { opacity: .5; pointer-events: none; }
        .ca-nav { display: flex; flex-direction: column; gap: 4px; margin-top: 18px; }
        .ca-nav-item, .ca-nav-item:hover, .ca-nav-item span { color: #742220 !important; }
        .ca-nav-item.active, .ca-nav-item.active span { color: #2D4A3E !important; }
        .ca-nav-item { display: flex; align-items: center; gap: 12px; border: 2px solid transparent; border-radius: 999px; padding: 9px 14px; font-size: 12px; font-weight: 800; text-decoration: none !important; }
        .ca-nav-item:hover { background: rgba(45,74,62,.08); }
        .ca-nav-item.active { background: #F5EFD5; border-color: #2D4A3E; }
        .ca-settings { display: grid; gap: 28px; grid-template-columns: 1fr; }
        @media (min-width: 1200px) { .ca-settings { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
        .ca-side-storage { margin-top: auto; padding-top: 16px; border-top: 2px solid #C8BCA0; }
        @media (max-width: 991px) { .ca-side-storage { margin-top: 18px; } }
        .ca-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: rgba(116,34,32,.55); }
        .ca-title { margin: 4px 0 0; font-size: 18px; font-weight: 900; color: #742220; }
        .ca-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
        .ca-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 999px; border: 2px solid #5a1a18; background: #742220; color: #FEF9E1 !important; padding: 9px 18px; font-size: 11px; font-weight: 900; letter-spacing: .18em; text-transform: uppercase; box-shadow: 3px 3px 0 0 #5a1a18; text-decoration: none !important; cursor: pointer; transition: all .15s; }
        .ca-btn:hover { transform: translate(-1px,-1px); box-shadow: 5px 5px 0 0 #5a1a18; }
        .ca-btn.disabled { opacity: .5; pointer-events: none; }
        .ca-btn-sm { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; border: 2px solid #2D4A3E; background: #F5EFD5; color: #2D4A3E; padding: 4px 11px; font-size: 10px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; cursor: pointer; }
        .ca-btn-sm:hover { background: #E5F0EB; }
        .ca-btn-sm.danger { border-color: #A03A2F; color: #A03A2F; background: #FEF9E1; }
        .ca-btn-sm.danger:hover { background: rgba(160,58,47,.08); }
        .ca-pool { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .ca-stat { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 10px; padding: 12px 14px; }
        .ca-stat strong { display: block; margin-top: 4px; font-size: 18px; font-weight: 900; color: #4B3A2F; }
        .ca-meter { height: 10px; border-radius: 999px; background: #EDE6D0; border: 1px solid #C8BCA0; overflow: hidden; }
        .ca-meter > div { height: 100%; border-radius: 999px; }
        .ca-list { display: flex; flex-direction: column; gap: 12px; }
        .ca-account { display: grid; grid-template-columns: 26px 38px minmax(0, 1fr); gap: 12px; align-items: start; background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 14px 16px; transition: border-color .15s, box-shadow .15s; }
        .ca-account:hover { border-color: #2D4A3E; }
        .ca-account.dragging { opacity: .45; }
        .ca-account.drag-over { border-color: #742220; box-shadow: 0 -3px 0 0 #742220; }
        .ca-drag { cursor: grab; color: rgba(116,34,32,.45); padding-top: 6px; }
        .ca-pos { width: 34px; height: 34px; border-radius: 999px; background: #742220; color: #FEF9E1; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 13px; }
        .ca-email { font-size: 14px; font-weight: 900; color: #4B3A2F; word-break: break-all; }
        .ca-figures { display: flex; flex-wrap: wrap; gap: 6px 18px; margin: 8px 0; font-size: 11px; color: rgba(116,34,32,.7); }
        .ca-figures b { color: #4B3A2F; }
        .ca-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 9px; font-size: 9px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; border: 1px solid; }
        .ca-badge.on { color: #166534; border-color: rgba(16,185,129,.45); background: rgba(16,185,129,.14); }
        .ca-badge.off { color: #525252; border-color: rgba(115,115,115,.45); background: rgba(115,115,115,.12); }
        .ca-error { margin-top: 8px; padding: 8px 10px; border-radius: 8px; border: 1px solid rgba(160,58,47,.4); background: rgba(160,58,47,.06); color: #A03A2F; font-size: 11px; word-break: break-word; }
        .ca-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .ca-actions form { display: inline; margin: 0; }
        .ca-empty { text-align: center; padding: 34px 14px; border: 2px dashed #C8BCA0; border-radius: 12px; font-size: 12px; color: rgba(116,34,32,.6); }
        .ca-field { display: block; width: 100%; border: 2px solid #C8BCA0; border-radius: 10px; background: #F5EFD5; color: #4B3A2F; padding: 9px 12px; font-size: 13px; font-weight: 700; font-family: inherit; }
        .ca-field:focus { outline: none; border-color: #2D4A3E; }
        .ca-label { display: block; margin: 0 0 6px; }
        .ca-note { font-size: 11px; line-height: 1.6; color: rgba(116,34,32,.7); }
        .ca-steps { margin: 8px 0 0; padding-left: 18px; font-size: 11px; line-height: 1.7; color: #4B3A2F; }
        .ca-steps code { background: #F5EFD5; border: 1px solid #C8BCA0; color: #742220; border-radius: 4px; padding: 1px 5px; word-break: break-all; }
        .ca-alert { margin-bottom: 18px; border-radius: 10px; border: 2px solid #A03A2F; background: #FEF9E1; color: #A03A2F; padding: 10px 14px; font-size: 12px; font-weight: 700; }
    </style>

    <div class="ca-shell ca">
        @if(!$googleConfigured)
            <div class="ca-alert">The Google OAuth client is not configured. Set it up in <a href="{{ route('admin.oauth') }}">OAuth Settings</a> first.</div>
        @endif
        <div class="ca-layout">
            <aside class="ca-panel ca-side">
                <a href="{{ route('admin.cloud.connect') }}" class="ca-new @if(!$googleConfigured) disabled @endif">
                    <span class="material-icons-round" style="font-size:18px">add</span> <span>Connect account</span>
                </a>
                <nav class="ca-nav">
                    @foreach(['pool' => ['Storage pool', 'cloud'], 'settings' => ['Settings', 'tune']] as $key => [$label, $icon])
                        <a href="{{ route('admin.cloud', ['tab' => $key]) }}" class="ca-nav-item {{ $tab === $key ? 'active' : '' }}">
                            <span class="material-icons-round" style="font-size:20px">{{ $icon }}</span> {{ $label }}
                            @if($key === 'pool')<span style="margin-left:auto;font-size:10px">{{ $accounts->count() }}</span>@endif
                        </a>
                    @endforeach
                </nav>
                <div class="ca-side-storage">
                    <div class="ca-eyebrow" style="display:flex;align-items:center;gap:6px"><span class="material-icons-round" style="font-size:14px">cloud_queue</span> Pool storage</div>
                    <div class="ca-meter" style="margin:10px 0 8px"><div style="width: {{ $poolPct }}%; background: {{ $poolPct > 90 ? '#A03A2F' : '#2D4A3E' }}"></div></div>
                    <div style="font-size:12px;font-weight:800;color:#4B3A2F">{{ $knownAccounts->isEmpty() ? 'Usage unknown' : $human($poolUsed) . ' of ' . $human($poolTotal) . ' used' }}</div>
                    <div style="font-size:10px;margin-top:4px;color:rgba(116,34,32,.55)">{{ $knownAccounts->isEmpty() ? 'Refresh an account to load its quota' : $human(max(0, $poolTotal - $poolUsed)) . ' free · ' . $accounts->where('enabled', true)->count() . ' active account(s)' }}</div>
                </div>
            </aside>

            <section class="ca-panel ca-main">
                <div class="ca-main-body">
                @if($tab === 'pool')
                    @if($accounts->isNotEmpty())
                        <div class="ca-pool">
                            <div class="ca-stat"><span class="ca-eyebrow">Total</span><strong>{{ $knownAccounts->isEmpty() ? 'unknown' : $human($poolTotal) }}</strong></div>
                            <div class="ca-stat"><span class="ca-eyebrow">Used</span><strong>{{ $knownAccounts->isEmpty() ? 'unknown' : $human($poolUsed) }}</strong></div>
                            <div class="ca-stat"><span class="ca-eyebrow">Free</span><strong>{{ $knownAccounts->isEmpty() ? 'unknown' : $human(max(0, $poolTotal - $poolUsed)) }}</strong></div>
                            <div class="ca-stat"><span class="ca-eyebrow">Accounts</span><strong>{{ $accounts->where('enabled', true)->count() }} / {{ $accounts->count() }} active</strong></div>
                        </div>
                        @if($knownAccounts->isNotEmpty() && $poolTotal)
                            <div class="ca-meter" style="margin-bottom:20px"><div style="width: {{ $poolPct }}%; background: {{ $poolPct > 90 ? '#A03A2F' : '#2D4A3E' }}"></div></div>
                        @endif
                    @endif

                    <div class="ca-list" id="cloudAccounts">
                        @forelse($accounts as $account)
                            @php($pct = $account->quota_limit ? min(100, round($account->quota_usage / $account->quota_limit * 100)) : 0)
                            @php($known = $account->quota_checked_at !== null)
                            <div class="ca-account" draggable="true" data-id="{{ $account->id }}">
                                <span class="ca-drag material-icons-round" title="Drag to change the order">drag_indicator</span>
                                <span class="ca-pos cloud-pos">{{ $loop->iteration }}</span>
                                <div style="min-width:0">
                                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                                        <span class="ca-email">{{ $account->email }}</span>
                                        <span class="ca-badge {{ $account->enabled ? 'on' : 'off' }}">{{ $account->enabled ? 'Accepting uploads' : 'Disabled' }}</span>
                                    </div>
                                    <div class="ca-figures">
                                        <span>Total <b>{{ !$known ? 'unknown' : $human($account->quota_limit) }}</b></span>
                                        <span>Used <b>{{ $known ? $human($account->quota_usage) . ($account->quota_limit ? ' (' . $pct . '%)' : '') : 'unknown' }}</b></span>
                                        <span>Free <b>{{ !$known ? 'unknown' : ($account->quota_limit === null ? 'unlimited' : $human($account->freeBytes())) }}</b></span>
                                        <span>User files <b>{{ $account->files_count }}</b></span>
                                        <span>Checked <b>{{ $account->quota_checked_at?->diffForHumans() ?? 'never' }}</b></span>
                                    </div>
                                    <div class="ca-meter"><div style="width: {{ $pct }}%; background: {{ $pct > 90 ? '#A03A2F' : '#2D4A3E' }}"></div></div>
                                    @if($account->last_error)
                                        <div class="ca-error">{{ $account->last_error }}</div>
                                    @endif
                                    <div class="ca-actions">
                                        <form method="POST" action="{{ route('admin.cloud.refresh', $account->id) }}">@csrf<button class="ca-btn-sm"><span class="material-icons-round" style="font-size:13px">refresh</span> Refresh</button></form>
                                        <form method="POST" action="{{ route('admin.cloud.toggle', $account->id) }}">@csrf<button class="ca-btn-sm">{{ $account->enabled ? 'Disable' : 'Enable' }}</button></form>
                                        <form method="POST" action="{{ route('admin.cloud.destroy', $account->id) }}" onsubmit="return confirm('Disconnect {{ $account->email }}?')">@csrf @method('DELETE')<button class="ca-btn-sm danger">Disconnect</button></form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="ca-empty">No Google account connected yet. Click <b>Connect Google account</b> to add one.</div>
                        @endforelse
                    </div>

                    <p class="ca-note" style="margin:14px 0 0">
                        <span id="cloudOrderStatus" style="float:right;font-weight:800"></span>
                        Drag accounts to set the order. New files go to account #1 until it is full, then #2, and so on. Files live in a "Burhan Cloud" folder, one sub-folder per user.
                    </p>
                @else
                    <div class="ca-settings">
                        <div>
                            <div class="ca-eyebrow">Configuration</div>
                            <h3 class="ca-title" style="margin-bottom:14px">Settings</h3>
                            <form method="POST" action="{{ route('admin.cloud.settings') }}">
                                @csrf @method('PATCH')
                                <label class="ca-label ca-eyebrow">Status</label>
                                <select name="cloud:enabled" class="ca-field">
                                    <option value="true" @if($enabled) selected @endif>Enabled</option>
                                    <option value="false" @if(!$enabled) selected @endif>Disabled</option>
                                </select>
                                <label class="ca-label ca-eyebrow" style="margin-top:14px">Storage per user (GB)</label>
                                <input type="number" step="0.1" min="0.1" name="cloud:quota_gb" class="ca-field" value="{{ config('cloud.quota_gb', 5) }}">
                                <p class="ca-note" style="margin:10px 0 16px">No file size limit: files go straight from the browser to Google Drive, so only this per-user storage limits them. Anney chat attachments are limited to 100 MB.</p>
                                <button class="ca-btn" style="width:100%">Save settings</button>
                            </form>
                        </div>
                        <div>
                            <div class="ca-eyebrow">Usage</div>
                            <h3 class="ca-title" style="margin-bottom:14px">What users store</h3>
                            <div class="ca-pool" style="margin:0">
                                <div class="ca-stat"><span class="ca-eyebrow">Files</span><strong>{{ $stats['files'] }}</strong></div>
                                <div class="ca-stat"><span class="ca-eyebrow">Stored</span><strong>{{ $human($stats['bytes']) }}</strong></div>
                                <div class="ca-stat"><span class="ca-eyebrow">Users</span><strong>{{ $stats['users'] }}</strong></div>
                                <div class="ca-stat"><span class="ca-eyebrow">Shared</span><strong>{{ $stats['shared'] }}</strong></div>
                            </div>

                            <div class="ca-eyebrow" style="margin-top:22px">One-time setup</div>
                            <h3 class="ca-title">Google Cloud Console</h3>
                            <ol class="ca-steps">
                                <li>Enable the <b>Google Drive API</b> for the project (APIs &amp; Services &rarr; Library).</li>
                                <li>No new redirect URI is needed: the connection reuses the sign-in callback <code>{{ $redirectUri }}</code>.</li>
                                <li>The panel only asks for <code>drive.file</code>, so it can only see files it created.</li>
                            </ol>
                        </div>
                    </div>
                @endif
                </div>
            </section>
        </div>
    </div>

    <script>
        // Fill exactly the space between the top bar and the sticky footer so the page does not scroll.
        (function () {
            function fit() {
                var shell = document.querySelector('.ca-shell');
                if (!shell || window.innerWidth < 992) { if (shell) shell.style.minHeight = ''; return; }
                var footer = document.querySelector('.admin-sticky-footer');
                var header = document.querySelector('.admin-topbar');
                var main = document.querySelector('.admin-main-shell');
                var footH = footer ? footer.getBoundingClientRect().height : 0;
                // Reserve only the footer's own height below the page, not the extra layout padding.
                if (main) main.style.paddingBottom = footH + 'px';
                var rect = shell.getBoundingClientRect();
                // Mirror the gap above the panels (top bar to panels) below them too.
                var gapTop = parseFloat(getComputedStyle(shell).paddingTop) || 0;
                if (header) gapTop = Math.max(gapTop, rect.top + gapTop - header.getBoundingClientRect().bottom);
                shell.style.paddingBottom = gapTop + 'px';
                var h = Math.max(420, window.innerHeight - footH - (rect.top + window.scrollY));
                shell.style.minHeight = h + 'px';
                // Trim any leftover overflow so the page never scrolls.
                var over = document.documentElement.scrollHeight - window.innerHeight;
                if (over > 0) shell.style.minHeight = Math.max(420, h - over) + 'px';
            }
            document.addEventListener('DOMContentLoaded', fit);
            window.addEventListener('resize', fit);
        })();

        document.addEventListener('DOMContentLoaded', function () {
            var list = document.getElementById('cloudAccounts'), dragged = null, status = document.getElementById('cloudOrderStatus');
            if (!list) return;
            list.querySelectorAll('.ca-account').forEach(function (item) {
                item.addEventListener('dragstart', function (e) { dragged = item; item.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
                item.addEventListener('dragend', function () { item.classList.remove('dragging'); list.querySelectorAll('.drag-over').forEach(function (r) { r.classList.remove('drag-over'); }); });
                item.addEventListener('dragover', function (e) { e.preventDefault(); if (item !== dragged) item.classList.add('drag-over'); });
                item.addEventListener('dragleave', function () { item.classList.remove('drag-over'); });
                item.addEventListener('drop', function (e) {
                    e.preventDefault(); item.classList.remove('drag-over');
                    if (!dragged || dragged === item) return;
                    var items = Array.prototype.slice.call(list.children);
                    list.insertBefore(dragged, items.indexOf(dragged) < items.indexOf(item) ? item.nextSibling : item);
                    var order = Array.prototype.map.call(list.querySelectorAll('.ca-account'), function (r, i) { r.querySelector('.cloud-pos').textContent = i + 1; return r.dataset.id; });
                    status.textContent = 'Saving…';
                    fetch('{{ route('admin.cloud.reorder') }}', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ order: order }) })
                        .then(function (r) { status.textContent = r.ok ? 'Order saved' : 'Could not save'; })
                        .catch(function () { status.textContent = 'Could not save'; });
                });
            });
        });
    </script>
@endsection
