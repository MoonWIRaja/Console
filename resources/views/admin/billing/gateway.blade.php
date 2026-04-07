@extends('layouts.admin')

@section('title')
    Billing Settings
@endsection

@section('content-header')
    <h1>Billing Settings<small>Configure hosted checkout providers and invoice lifecycle values.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    @php
        $bclWebhookUrl = route('billing.gateway.bcl.webhook');
        $bclBillingBaseUrl = url('/billing?section=invoices');
        $bclSuccessUrl = $bclBillingBaseUrl . '&bcl=success';
        $bclFailedUrl = $bclBillingBaseUrl . '&bcl=failed';
        $bclPendingUrl = $bclBillingBaseUrl . '&bcl=pending';
        $bclCustomFields = implode(PHP_EOL, [
            'panel_checkout_reference',
            'panel_invoice_id',
            'panel_invoice_number',
            'panel_amount',
            'panel_currency',
            'panel_signature',
        ]);
        $bclPayerFields = implode(PHP_EOL, [
            'name',
            'email',
            'phone',
            'amount',
            'currency',
            'description',
        ]);
    @endphp

    <div class="callout callout-info">
        @if (config('billing.gateway.manual_mode', true))
            <p><strong>Current operating mode:</strong> manual billing mode is enabled. New server orders, renewals, and upgrades create invoices inside the panel and stay pending until billing admin records payment manually.</p>
            <p style="margin: 6px 0 0;">Hosted providers such as BCL stay configured for later use, but manual mode overrides them for all new checkout attempts.</p>
        @elseif (config('billing.gateway.default') === 'bcl')
            <p><strong>Current operating mode:</strong> BCL hosted checkout is active for new invoices. Customers are redirected to your configured BCL form, and payment confirmation returns through the BCL webhook endpoint.</p>
            <p style="margin: 6px 0 0;">Manual billing remains available as a fallback when you need to disable hosted checkout temporarily.</p>
        @else
            <p><strong>Current operating mode:</strong> hosted checkout is enabled, but the default provider is not configured for BCL yet.</p>
        @endif
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Gateway Mode</h3>
        </div>
        <form method="POST" action="{{ route('admin.billing.gateway.update') }}">
            @csrf
            @method('PATCH')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Default Billing Provider</label>
                        <select name="billing:gateway:default" class="form-control">
                            <option value="manual" {{ config('billing.gateway.default', 'manual') === 'manual' ? 'selected' : '' }}>Manual</option>
                            <option value="bcl" {{ config('billing.gateway.default') === 'bcl' ? 'selected' : '' }}>BCL Hosted Checkout</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Choose the provider new invoices should use when manual mode is disabled.</p>
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

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>BCL Provider Enabled</label>
                        <select name="billing:bcl:enabled" class="form-control">
                            <option value="1" {{ config('billing.bcl.enabled', false) ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ !config('billing.bcl.enabled', false) ? 'selected' : '' }}>Disabled</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Enable this before setting BCL as the default hosted checkout provider.</p>
                    </div>
                    <div class="col-md-8 form-group">
                        <label>BCL Form URL</label>
                        <input type="url" name="billing:bcl:form_url" class="form-control" value="{{ config('billing.bcl.form_url') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Use the public BCL form page URL, for example <code>https://merchant.bcl.my/form/billing</code>. The panel opens this page with a <code>GET</code> request and passes the fields listed below.</p>
                    </div>
                </div>

                <div class="callout callout-warning" style="margin-top: 0;">
                    <p style="margin: 0;"><strong>BCL custom field contract:</strong> your BCL form must accept these hidden fields exactly as named: <code>panel_checkout_reference</code>, <code>panel_invoice_id</code>, <code>panel_invoice_number</code>, <code>panel_amount</code>, <code>panel_currency</code>, <code>panel_signature</code>.</p>
                    <p style="margin: 6px 0 0;">Expected payer fields for the hosted form are <code>name</code>, <code>email</code>, <code>phone</code>, <code>amount</code>, <code>currency</code>, and <code>description</code>.</p>
                    <p style="margin: 6px 0 0;">Webhook URL to register in BCL: <code>{{ $bclWebhookUrl }}</code></p>
                </div>

                <div class="box box-default" style="margin-bottom: 20px;">
                    <div class="box-header with-border">
                        <h3 class="box-title">BCL Copy &amp; Paste Setup</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted" style="margin-top: 0;">Copy these values directly into your BCL form settings. The redirect URLs return the customer to the billing page, while the webhook URL is what lets the panel verify payment and start provisioning.</p>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Webhook URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select();" value="{{ $bclWebhookUrl }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>BCL Form URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select();" value="{{ config('billing.bcl.form_url') }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Success URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select();" value="{{ $bclSuccessUrl }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Failed URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select();" value="{{ $bclFailedUrl }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Pending URL</label>
                                <input type="text" class="form-control" readonly onclick="this.select();" value="{{ $bclPendingUrl }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Custom Fields</label>
                                <textarea class="form-control" rows="7" readonly onclick="this.select();">{{ $bclCustomFields }}</textarea>
                                <p class="text-muted small" style="margin: 6px 0 0;">Create each of these in BCL as <code>Text Input</code>, enabled, with the same label and label ID.</p>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Expected Payer Fields</label>
                                <textarea class="form-control" rows="7" readonly onclick="this.select();">{{ $bclPayerFields }}</textarea>
                                <p class="text-muted small" style="margin: 6px 0 0;">These are the values the panel sends to the BCL form during checkout.</p>
                            </div>
                        </div>

                        <div class="callout callout-info" style="margin-bottom: 0;">
                            <p style="margin: 0;"><strong>Note:</strong> BCL <em>Test Webhook</em> may still return <code>422</code> because the sample payload does not include a real <code>panel_checkout_reference</code>. Validate with a real checkout transaction and then review <strong>Webhook Events</strong> in admin billing.</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Currency</label>
                        <input type="text" name="billing:currency" class="form-control" value="{{ config('billing.currency', 'MYR') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Customer invoices and hosted checkout totals are currently standardised around <code>MYR</code>.</p>
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
                <li>Use <strong>Invoices</strong> and <strong>Payments</strong> to review hosted checkout results or record manual fallback payments.</li>
                <li>BCL webhook deliveries are logged under <strong>Webhook Events</strong> and can be replayed if processing fails.</li>
                <li>Use <strong>Refunds</strong> for audit only on BCL payments unless you have completed the provider-side refund outside the panel.</li>
            </ul>
        </div>
    </div>
@endsection
