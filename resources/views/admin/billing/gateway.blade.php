@extends('layouts.admin')

@section('title')
    Billing Settings
@endsection

@section('content-header')
    <h1>Billing Settings<small>Configure manual billing mode and invoice lifecycle values.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    <div class="callout callout-info">
        <p><strong>Current operating mode:</strong> this panel is running in manual billing mode. New server orders, renewals, and upgrades create invoices inside the panel and stay pending until billing admin records payment manually.</p>
        <p style="margin: 6px 0 0;">Legacy Stripe and Fiuu records can remain for historical audit and refunds, but they are not used for new checkout flow while manual mode is enabled.</p>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Manual Billing Mode</h3>
        </div>
        <form method="POST" action="{{ route('admin.billing.gateway.update') }}">
            @csrf
            @method('PATCH')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Default Billing Provider</label>
                        <input type="text" class="form-control" value="manual" readonly>
                        <p class="text-muted small" style="margin: 6px 0 0;">All new invoices now default to the internal manual billing workflow.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Manual Billing Mode</label>
                        <select name="billing:gateway:manual_mode" class="form-control">
                            <option value="1" {{ config('billing.gateway.manual_mode', true) ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ !config('billing.gateway.manual_mode', true) ? 'selected' : '' }}>Disabled</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Keep this enabled to block hosted checkout and force invoice review inside admin billing.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Manual Provider Enabled</label>
                        <select name="billing:manual:enabled" class="form-control">
                            <option value="1" {{ config('billing.manual.enabled', true) ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ !config('billing.manual.enabled', true) ? 'selected' : '' }}>Disabled</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Administrative switch for the internal manual provider record used by invoices.</p>
                    </div>
                </div>

                <input type="hidden" name="billing:gateway:default" value="manual">

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Currency</label>
                        <input type="text" name="billing:currency" class="form-control" value="{{ config('billing.currency', 'MYR') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Manual billing is currently standardised around <code>MYR</code>.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Invoice Lead Days</label>
                        <input type="number" min="1" name="billing:invoice_lead_days" class="form-control" value="{{ config('billing.invoice_lead_days', 7) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">How early renewal invoices are prepared before the due window.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Suspend Grace Hours</label>
                        <input type="number" min="1" name="billing:suspend_grace_hours" class="form-control" value="{{ config('billing.suspend_grace_hours', 24) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after unpaid renewal before the server is suspended.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Delete Grace Hours</label>
                        <input type="number" min="1" name="billing:delete_grace_hours" class="form-control" value="{{ config('billing.delete_grace_hours', 72) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after due date before an overdue server is deleted.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Refund Suspend Hours</label>
                        <input type="number" min="1" name="billing:refund_suspend_hours" class="form-control" value="{{ config('billing.refund_suspend_hours', 5) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after a full base refund before the refunded server is suspended.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Refund Delete After Suspend</label>
                        <input type="number" min="1" name="billing:refund_delete_after_suspend_hours" class="form-control" value="{{ config('billing.refund_delete_after_suspend_hours', 24) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after refund suspension before the refunded server is deleted.</p>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Save Billing Settings</button>
            </div>
        </form>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Operational Notes</h3>
        </div>
        <div class="box-body">
            <ul style="margin-bottom: 0; padding-left: 18px;">
                <li>Use <strong>Invoices</strong> and <strong>Payments</strong> to record or verify incoming manual payment.</li>
                <li>Use <strong>Refunds</strong> for refund audit and settlement tracking after admin action.</li>
                <li><strong>Webhook Events</strong> now act as historical reference only while manual billing mode stays enabled.</li>
            </ul>
        </div>
    </div>
@endsection
