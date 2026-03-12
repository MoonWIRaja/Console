@extends('layouts.admin')

@section('title')
    Billing Gateway
@endsection

@section('content-header')
    <h1>Billing Gateway<small>Configure Fiuu and billing lifecycle values.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Gateway</li>
    </ol>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    @php
        $defaultReturnUrl = url('/fiuu-return.php');
        $defaultCallbackUrl = route('billing.gateway.fiuu.callback');
        $defaultRequeryUrl = 'https://api.fiuu.com/RMS/API/gate-query/index.php';
        $defaultRecurringUrl = 'https://pay.fiuu.com/RMS/API/token/index.php';
        $defaultRefundUrl = 'https://api.fiuu.com/RMS/API/refundAPI/index.php';
    @endphp

    <div class="callout callout-info">
        <p><strong>How this panel uses Fiuu:</strong> hosted checkout redirects the customer to Fiuu, then Fiuu returns the browser to <code>Return URL</code> and sends server-to-server payment status to <code>Callback URL</code>.</p>
        <p style="margin: 6px 0 0;">Verified payment flow depends on <code>Merchant ID</code>, <code>Verify Key</code>, <code>Secret Key</code>, and a working <code>Requery URL</code>. Auto-renew and admin refund need <code>Recurring URL</code> and <code>Refund URL</code>.</p>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Fiuu Settings</h3>
        </div>
        <form method="POST" action="{{ route('admin.billing.gateway.update') }}">
            @csrf
            @method('PATCH')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Enabled</label>
                        <select name="billing:fiuu:enabled" class="form-control">
                            <option value="1" {{ config('billing.fiuu.enabled') ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !config('billing.fiuu.enabled') ? 'selected' : '' }}>No</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Master switch for Fiuu checkout. Leave this off until every credential and callback URL below is valid.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Sandbox</label>
                        <select name="billing:fiuu:sandbox" class="form-control">
                            <option value="1" {{ config('billing.fiuu.sandbox') ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !config('billing.fiuu.sandbox') ? 'selected' : '' }}>No</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Use this for Fiuu test mode. Official Fiuu installation docs note sandbox Merchant IDs commonly start with <code>SB_</code>.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Currency</label>
                        <input type="text" name="billing:currency" class="form-control" value="{{ config('billing.currency', 'MYR') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Panel billing phase 1 is designed for <code>MYR</code>. Keep this as <code>MYR</code> unless you also change gateway and pricing assumptions in code.</p>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Enabled Methods</label>
                        <input type="text" name="billing:fiuu:enabled_methods" class="form-control" value="{{ implode(',', config('billing.fiuu.enabled_methods', [])) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Comma-separated Fiuu channel codes approved on your merchant account. If you enter exactly <strong>one</strong> code, the panel locks checkout to that method. If you leave this blank, enter multiple codes, or use <code>all</code>, the panel will not send a fixed <code>channel</code> and the payer can choose the payment method directly on the Fiuu page.</p>
                        <p class="text-muted small" style="margin: 6px 0 0;">Important: <code>all</code> does not force card, QR, FPX, and every other method to appear. Fiuu will only display channels that are actually enabled on your merchant account. Auto-renew also needs a tokenized card-capable channel, not just QR or online banking.</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Merchant ID</label>
                        <input type="text" name="billing:fiuu:merchant_id" class="form-control" value="{{ config('billing.fiuu.merchant_id') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Your Fiuu merchant identifier. This is sent in checkout, callback verification, requery, recurring charge, and refund requests.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Verify Key</label>
                        <input type="password" name="billing:fiuu:verify_key" class="form-control" value="{{ config('billing.fiuu.verify_key') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Used by this panel to generate checkout <code>vcode</code> and to validate callback signatures. If this is wrong, payments will arrive but not verify safely.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Extended Vcode</label>
                        <select name="billing:fiuu:extended_vcode" class="form-control">
                            <option value="1" {{ config('billing.fiuu.extended_vcode') ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !config('billing.fiuu.extended_vcode') ? 'selected' : '' }}>No</option>
                        </select>
                        <p class="text-muted small" style="margin: 6px 0 0;">Controls whether checkout <code>vcode</code> is generated with the extended currency format. Start with <strong>No</strong> unless your Fiuu merchant explicitly enables the extended verify format in merchant settings.</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Secret Key</label>
                        <input type="password" name="billing:fiuu:secret_key" class="form-control" value="{{ config('billing.fiuu.secret_key') }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Used for secure server-to-server operations such as payment requery, recurring charging, and refund requests.</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Return URL</label>
                        <input type="url" name="billing:fiuu:return_url" class="form-control" value="{{ old('billing:fiuu:return_url', config('billing.fiuu.return_url') ?: $defaultReturnUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Auto-filled with a lightweight browser-return endpoint that forwards the customer back to <code>/billing</code> after payment. Current default: <code>{{ $defaultReturnUrl }}</code></p>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Callback URL</label>
                        <input type="url" name="billing:fiuu:callback_url" class="form-control" value="{{ old('billing:fiuu:callback_url', config('billing.fiuu.callback_url') ?: $defaultCallbackUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Auto-filled with the panel callback route. This must stay publicly reachable over HTTPS. Current default: <code>{{ $defaultCallbackUrl }}</code></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Requery URL</label>
                        <input type="url" name="billing:fiuu:requery_url" class="form-control" value="{{ old('billing:fiuu:requery_url', config('billing.fiuu.requery_url') ?: $defaultRequeryUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Auto-filled with Fiuu's official Direct Status Requery endpoint. Source: <a href="https://docs.fiuu.dev/reference/direct-status-requery" target="_blank" rel="noreferrer">docs.fiuu.dev/reference/direct-status-requery</a></p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Recurring URL</label>
                        <input type="url" name="billing:fiuu:recurring_url" class="form-control" value="{{ old('billing:fiuu:recurring_url', config('billing.fiuu.recurring_url') ?: $defaultRecurringUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Auto-filled with Fiuu's official token API endpoint. This panel uses it as the default recurring/token URL, but recurring charges still require tokenization approval on your Fiuu merchant. Source: <a href="https://docs.fiuu.dev/reference/payment-token-api" target="_blank" rel="noreferrer">docs.fiuu.dev/reference/payment-token-api</a></p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Refund URL</label>
                        <input type="url" name="billing:fiuu:refund_url" class="form-control" value="{{ old('billing:fiuu:refund_url', config('billing.fiuu.refund_url') ?: $defaultRefundUrl) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Auto-filled with Fiuu's official Advanced Full/Partial Refund endpoint. Source: <a href="https://docs.fiuu.dev/reference/advanced-fullpartial-refund" target="_blank" rel="noreferrer">docs.fiuu.dev/reference/advanced-fullpartial-refund</a></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Invoice Lead Days</label>
                        <input type="number" min="1" name="billing:invoice_lead_days" class="form-control" value="{{ config('billing.invoice_lead_days', 7) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">How many days before <code>renews_at</code> the panel should issue a renewal invoice and begin reminder emails.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Suspend Grace Hours</label>
                        <input type="number" min="1" name="billing:suspend_grace_hours" class="form-control" value="{{ config('billing.suspend_grace_hours', 24) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after an unpaid renewal due date before the subscription moves into panel suspension.</p>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Delete Grace Hours</label>
                        <input type="number" min="1" name="billing:delete_grace_hours" class="form-control" value="{{ config('billing.delete_grace_hours', 72) }}">
                        <p class="text-muted small" style="margin: 6px 0 0;">Hours after the unpaid grace window before the subscription is marked for deletion. Keep this higher than suspend grace.</p>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Save Gateway Settings</button>
            </div>
        </form>
    </div>
@endsection
