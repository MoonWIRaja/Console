<div class="nav-tabs-custom admin-gap-bottom-xl">
    <ul class="nav nav-tabs">
        <li class="{{ request()->routeIs('admin.billing') ? 'active' : '' }}"><a href="{{ route('admin.billing') }}">Overview</a></li>
        <li class="{{ request()->routeIs('admin.billing.gateway*') ? 'active' : '' }}"><a href="{{ route('admin.billing.gateway') }}">Settings</a></li>
        <li class="{{ request()->routeIs('admin.billing.invoices*') ? 'active' : '' }}"><a href="{{ route('admin.billing.invoices') }}">Invoices</a></li>
        <li class="{{ request()->routeIs('admin.billing.payments*') ? 'active' : '' }}"><a href="{{ route('admin.billing.payments') }}">Payments</a></li>
        <li class="{{ request()->routeIs('admin.billing.refunds*') ? 'active' : '' }}"><a href="{{ route('admin.billing.refunds') }}">Refunds</a></li>
        <li class="{{ request()->routeIs('admin.billing.subscriptions*') ? 'active' : '' }}"><a href="{{ route('admin.billing.subscriptions') }}">Subscriptions</a></li>
        <li class="{{ request()->routeIs('admin.billing.reconciliation*') ? 'active' : '' }}"><a href="{{ route('admin.billing.reconciliation') }}">Reconciliation</a></li>
        <li class="{{ request()->routeIs('admin.billing.tax-rules*') ? 'active' : '' }}"><a href="{{ route('admin.billing.tax-rules') }}">Tax Rules</a></li>
        <li class="{{ request()->routeIs('admin.billing.coupons*') ? 'active' : '' }}"><a href="{{ route('admin.billing.coupons') }}">Coupons</a></li>
        <li class="{{ request()->routeIs('admin.billing.webhook-events*') ? 'active' : '' }}"><a href="{{ route('admin.billing.webhook-events') }}">Webhook History</a></li>
        <li class="{{ request()->routeIs('admin.billing.provision-failures*') ? 'active' : '' }}"><a href="{{ route('admin.billing.provision-failures') }}">Provision Failures</a></li>
    </ul>
</div>

{{-- Shared look for every billing page inside the side-menu layout. --}}
<style>
    .bl-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #C8BCA0; }
    .bl-title { font-size: 18px; font-weight: 900; color: #742220; margin: 0; }
    .bl-head-btn { margin-left: auto; }
    .bl-info { position: relative; display: inline-flex; }
    .bl-info-btn { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #742220; color: #742220; background: #FEF9E1; font-size: 12px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: help; padding: 0; }
    .bl-info-tip { position: absolute; left: -10px; top: calc(100% + 10px); width: 340px; max-width: 80vw; z-index: 30; background: #FEF9E1; border: 2px solid #2D4A3E; border-radius: 12px; box-shadow: 4px 4px 0 0 #2D4A3E; padding: 12px 14px; font-size: 11px; line-height: 1.6; color: #4B3A2F; opacity: 0; visibility: hidden; transition: opacity .15s; pointer-events: none; }
    .bl-info:hover .bl-info-tip, .bl-info:focus-within .bl-info-tip { opacity: 1; visibility: visible; }
    .bl-stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); margin-bottom: 18px; }
    .bl-stat { background: #F5EFD5; border: 2px solid #C8BCA0; border-radius: 12px; padding: 12px 14px; }
    .bl-stat span { display: block; font-size: 9px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: rgba(116,34,32,.6); }
    .bl-stat strong { display: block; margin-top: 4px; font-size: 20px; font-weight: 900; color: #4B3A2F; }
    .bl-stat small { font-size: 10px; color: rgba(116,34,32,.6); }
    .bl-stat.warn strong { color: #B7791F; }
    .bl-stat.bad strong { color: #A03A2F; }
    .bl-stat.good strong { color: #2D6A4F; }
    body.skin-blue #asMain.as-tabs .box, body.skin-blue #asMain.as-tabs .box[class*="box-"] { background: #F5EFD5 !important; background-image: none !important; border: 2px solid #C8BCA0 !important; border-top: 2px solid #C8BCA0 !important; border-radius: 12px !important; box-shadow: none !important; padding: 0 !important; margin: 0 0 18px !important; overflow: hidden !important; }
    body.skin-blue #asMain.as-tabs .box > .box-header { background: transparent !important; padding: 12px 16px !important; margin: 0 !important; border-bottom: 1px solid #C8BCA0 !important; }
    body.skin-blue #asMain.as-tabs .box > .box-header .box-title { font-size: 14px !important; letter-spacing: 0 !important; text-transform: none !important; }
    body.skin-blue #asMain.as-tabs .box .box-body { padding: 14px 16px !important; }
    body.skin-blue #asMain.as-tabs .box .box-body .row { margin-left: -8px !important; margin-right: -8px !important; }
    body.skin-blue #asMain.as-tabs .box .box-body .row > [class*="col-"] { padding-left: 8px !important; padding-right: 8px !important; }
    body.skin-blue #asMain.as-tabs > .nav-tabs-custom { margin: 0 !important; }
    body.skin-blue #asMain.as-tabs .box .box-body.no-padding, body.skin-blue #asMain.as-tabs .box .box-body.table-responsive { padding: 0 !important; overflow-x: auto !important; }
    body.skin-blue #asMain.as-tabs .box .box-footer { background: transparent !important; padding: 10px 16px !important; margin: 0 !important; border-top: 1px solid #C8BCA0 !important; }
    body.skin-blue #asMain.as-tabs .box .table { margin: 0; background: transparent !important; }
    body.skin-blue #asMain.as-tabs .box .table th { background: #EFE7C9 !important; font-size: 10px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; color: rgba(116,34,32,.75) !important; border-bottom: 2px solid #2D4A3E !important; white-space: nowrap; }
    body.skin-blue #asMain.as-tabs .box .table td { background: transparent !important; font-size: 12px; vertical-align: middle !important; border-top: 1px solid #E2D8BD !important; }
    body.skin-blue #asMain.as-tabs .box .table > tbody > tr:hover > td { background: rgba(254,249,225,.8) !important; }
    body.skin-blue #asMain.as-tabs .box .table th:first-child, body.skin-blue #asMain.as-tabs .box .table td:first-child { padding-left: 16px !important; }
    body.skin-blue #asMain.as-tabs .box .table th:last-child, body.skin-blue #asMain.as-tabs .box .table td:last-child { padding-right: 16px !important; }
    body.skin-blue #asMain.as-tabs .box .table td:last-child { white-space: nowrap; }
    body.skin-blue #asMain.as-tabs .box .table td:last-child form { display: inline-block; margin: 0 0 0 4px; }
    body.skin-blue #asMain.as-tabs .row > .col-md-6 > .box { height: calc(100% - 18px); }
</style>
