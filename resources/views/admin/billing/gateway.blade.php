@extends('layouts.admin')

@section('title')
    Billing Gateway
@endsection

@section('content-header')
    <h1>Billing Gateway<small>Configure Stripe checkout, portal, webhooks, and billing lifecycle values.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Gateway</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    @php
        $defaultWebhookUrl = route('billing.gateway.stripe.webhook');
        $defaultSuccessUrl = route('billing.gateway.stripe.return');
        $defaultCancelUrl = rtrim(config('app.url'), '/') . '/billing';
        $defaultPortalReturnUrl = rtrim(config('app.url'), '/') . '/billing';
    @endphp

    <div class="callout callout-info">
        <p><strong>How this panel uses Stripe:</strong> new billed servers and Stripe migration renewals start in Stripe Checkout, billing details and saved cards are managed through Stripe Customer Portal, and <code>invoice.paid</code> webhooks are the source of truth before the panel provisions or upgrades a server.</p>
        <p style="margin: 6px 0 0;">Keep the webhook URL below registered in Stripe, use card-capable recurring payment methods only, and enable Stripe Tax only if your Stripe account country supports it.</p>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Stripe Settings</h3>
        </div>
        <form method="POST" action="{{ route('admin.billing.gateway.update') }}">
            @csrf
            @method('PATCH')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Enabled</label>
                        <select name="billing:stripe:enabled" class="form-control">
                            <option value="1" {{ config('billing.stripe.enabled') ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !config('billing.stripe.enabled') ? 'selected' : '' }}>No</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Master switch for Stripe billing. Leave this off until keys and webhook secret are valid.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Mode</label>
                        <select name="billing:stripe:mode" class="form-control">
                            <option value="test" {{ config('billing.stripe.mode', 'test') === 'test' ? 'selected' : '' }}>Test</option>
                            <option value="live" {{ config('billing.stripe.mode', 'test') === 'live' ? 'selected' : '' }}>Live</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Use <code>test</code> until webhook delivery, checkout, renewal, upgrade, and refund flows are proven end to end.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Currency</label>
                        <input type="text" name="billing:currency" class="form-control" value="{{ config('billing.currency', 'MYR') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Stripe migration v1 is designed for <code>MYR</code>. Keep this aligned with your Stripe prices and tax settings.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Stripe Tax</label>
                        <select name="billing:stripe:automatic_tax_enabled" class="form-control">
                            <option value="1" {{ config('billing.stripe.automatic_tax_enabled', false) ? 'selected' : '' }}>Enabled</option>
                            <option value="0" {{ !config('billing.stripe.automatic_tax_enabled', false) ? 'selected' : '' }}>Disabled</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Enable this only if your Stripe account supports Stripe Tax in its country. Otherwise checkout can fail and the panel will fall back to local tax-free checkout.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Publishable Key</label>
                        <input type="text" name="billing:stripe:publishable_key" class="form-control" value="{{ config('billing.stripe.publishable_key') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Used by the front-end only if you later choose Stripe.js flows. Current hosted checkout still keeps the server as the source of truth.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Secret Key</label>
                        <input type="password" name="billing:stripe:secret_key" class="form-control" value="{{ config('billing.stripe.secret_key') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Required for Checkout Session creation, subscription sync, invoice sync, refunds, and portal sessions.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Webhook Secret</label>
                        <input type="password" name="billing:stripe:webhook_secret" class="form-control" value="{{ config('billing.stripe.webhook_secret') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Used to verify incoming Stripe webhooks. Without this, the panel cannot trust <code>invoice.paid</code> and related lifecycle events.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Portal Configuration ID</label>
                        <input type="text" name="billing:stripe:portal_configuration_id" class="form-control" value="{{ config('billing.stripe.portal_configuration_id') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Optional Stripe Billing Portal configuration. Use this to lock the portal down to billing details, tax ID, payment methods, and invoices only.</p>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Portal Return URL</label>
                        <input type="url" class="form-control" value="{{ $defaultPortalReturnUrl }}" readonly>
                        <p class="text-muted small" style="margin: 6px 0 0;">Read-only return path for Stripe Customer Portal sessions. The panel sends users back to <code>/billing</code>.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Webhook URL</label>
                        <input type="url" class="form-control" value="{{ $defaultWebhookUrl }}" readonly>
                        <p class="text-muted small" style="margin: 6px 0 0;">Register this exact HTTPS endpoint in Stripe for recurring invoice, subscription, and refund events.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Checkout Success URL</label>
                        <input type="url" name="billing:stripe:success_url" class="form-control" value="{{ old('billing:stripe:success_url', config('billing.stripe.success_url') ?: $defaultSuccessUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Browser redirect after Stripe Checkout. Source of truth still remains the webhook, not this redirect.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Checkout Cancel URL</label>
                        <input type="url" name="billing:stripe:cancel_url" class="form-control" value="{{ old('billing:stripe:cancel_url', config('billing.stripe.cancel_url') ?: $defaultCancelUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Where the payer returns if Checkout is cancelled before payment is completed.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Invoice Lead Days</label>
                        <input type="number" min="1" name="billing:invoice_lead_days" class="form-control" value="{{ config('billing.invoice_lead_days', 7) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Used by legacy subscriptions and migration windows before Stripe renewal cutover.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Suspend Grace Hours</label>
                        <input type="number" min="1" name="billing:suspend_grace_hours" class="form-control" value="{{ config('billing.suspend_grace_hours', 24) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after unpaid renewal before the panel suspends the server.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Delete Grace Hours</label>
                        <input type="number" min="1" name="billing:delete_grace_hours" class="form-control" value="{{ config('billing.delete_grace_hours', 72) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after due date before the overdue server is deleted.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Refund Suspend Hours</label>
                        <input type="number" min="1" name="billing:refund_suspend_hours" class="form-control" value="{{ config('billing.refund_suspend_hours', 5) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after a full base-server refund before the server is suspended.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Refund Delete After Suspend</label>
                        <input type="number" min="1" name="billing:refund_delete_after_suspend_hours" class="form-control" value="{{ config('billing.refund_delete_after_suspend_hours', 24) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after refund suspension before the refunded server is deleted.</p>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Save Gateway Settings</button>
            </div>
        </form>
    </div>
@endsection
