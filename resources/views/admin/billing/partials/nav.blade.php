<div class="nav-tabs-custom" style="margin-bottom: 20px;">
    <ul class="nav nav-tabs">
        <li class="{{ request()->routeIs('admin.billing') ? 'active' : '' }}"><a href="{{ route('admin.billing') }}">Overview</a></li>
        <li class="{{ request()->routeIs('admin.billing.gateway*') ? 'active' : '' }}"><a href="{{ route('admin.billing.gateway') }}">Gateway</a></li>
        <li class="{{ request()->routeIs('admin.billing.invoices*') ? 'active' : '' }}"><a href="{{ route('admin.billing.invoices') }}">Invoices</a></li>
        <li class="{{ request()->routeIs('admin.billing.payments*') ? 'active' : '' }}"><a href="{{ route('admin.billing.payments') }}">Payments</a></li>
        <li class="{{ request()->routeIs('admin.billing.refunds*') ? 'active' : '' }}"><a href="{{ route('admin.billing.refunds') }}">Refunds</a></li>
        <li class="{{ request()->routeIs('admin.billing.subscriptions*') ? 'active' : '' }}"><a href="{{ route('admin.billing.subscriptions') }}">Subscriptions</a></li>
        <li class="{{ request()->routeIs('admin.billing.reconciliation*') ? 'active' : '' }}"><a href="{{ route('admin.billing.reconciliation') }}">Reconciliation</a></li>
        <li class="{{ request()->routeIs('admin.billing.tax-rules*') ? 'active' : '' }}"><a href="{{ route('admin.billing.tax-rules') }}">Tax Rules</a></li>
        <li class="{{ request()->routeIs('admin.billing.webhook-events*') ? 'active' : '' }}"><a href="{{ route('admin.billing.webhook-events') }}">Webhook Events</a></li>
        <li class="{{ request()->routeIs('admin.billing.provision-failures*') ? 'active' : '' }}"><a href="{{ route('admin.billing.provision-failures') }}">Provision Failures</a></li>
    </ul>
</div>
