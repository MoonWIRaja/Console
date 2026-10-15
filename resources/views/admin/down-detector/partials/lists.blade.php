<div class="dd-card">
    <div class="dd-card-title"><span class="material-icons-round">error_outline</span>Current outages<span class="dd-count">{{ count($monitors) }}</span></div>
    <div class="dd-list">
        @forelse($monitors as $monitor)
            <div class="dd-row">
                <span class="dd-dot"></span>
                <div class="dd-row-main">
                    <div class="dd-row-name">{{ $monitor['name'] }}</div>
                    <div class="dd-row-msg">{{ $monitor['message'] }}</div>
                </div>
                <div class="dd-row-meta"><code>{{ $monitor['reason'] ?: 'n/a' }}</code><br>since {{ $monitor['changed_at'] }}<br>checked {{ $monitor['checked_at'] }}</div>
            </div>
        @empty
            <div class="dd-empty">No active {{ $kind }} outages.</div>
        @endforelse
    </div>
</div>
<div class="dd-card">
    <div class="dd-card-title"><span class="material-icons-round">history</span>Recent incidents<span class="dd-count">{{ count($incidents) }}</span></div>
    <div class="dd-list">
        @forelse($incidents as $incident)
            <div class="dd-row">
                <span class="dd-dot {{ $incident['to_status'] === 'up' ? 'up' : '' }}"></span>
                <div class="dd-row-main">
                    <div class="dd-row-name">{{ $incident['name'] }}</div>
                    <div class="dd-row-msg">{{ $incident['summary'] }}</div>
                </div>
                <div class="dd-row-meta"><code>{{ $incident['from_status'] }}</code> → <code>{{ $incident['to_status'] }}</code><br>{{ $incident['created_at'] }}</div>
            </div>
        @empty
            <div class="dd-empty">No {{ $kind }} incidents recorded yet.</div>
        @endforelse
    </div>
</div>
