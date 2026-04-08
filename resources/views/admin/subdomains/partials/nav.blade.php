<div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
        <li class="{{ ($activeTab ?? 'index') === 'index' ? 'active' : '' }}">
            <a href="{{ route('admin.subdomains.index') }}">Overview</a>
        </li>
        <li class="{{ ($activeTab ?? '') === 'domain-create' ? 'active' : '' }}">
            <a href="{{ route('admin.subdomains.domains.create') }}">New Domain</a>
        </li>
        <li class="{{ ($activeTab ?? '') === 'record-create' ? 'active' : '' }}">
            <a href="{{ route('admin.subdomains.records.create') }}">New Template</a>
        </li>
    </ul>
</div>
