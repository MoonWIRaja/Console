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
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Sandbox</label>
                        <select name="billing:fiuu:sandbox" class="form-control">
                            <option value="1" {{ config('billing.fiuu.sandbox') ? 'selected' : '' }}>Yes</option>
                            <option value="0" {{ !config('billing.fiuu.sandbox') ? 'selected' : '' }}>No</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Currency</label>
                        <input type="text" name="billing:currency" class="form-control" value="{{ config('billing.currency', 'MYR') }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Enabled Methods</label>
                        <input type="text" name="billing:fiuu:enabled_methods" class="form-control" value="{{ implode(',', config('billing.fiuu.enabled_methods', [])) }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Merchant ID</label>
                        <input type="text" name="billing:fiuu:merchant_id" class="form-control" value="{{ config('billing.fiuu.merchant_id') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Verify Key</label>
                        <input type="password" name="billing:fiuu:verify_key" class="form-control" value="{{ config('billing.fiuu.verify_key') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Secret Key</label>
                        <input type="password" name="billing:fiuu:secret_key" class="form-control" value="{{ config('billing.fiuu.secret_key') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Return URL</label>
                        <input type="url" name="billing:fiuu:return_url" class="form-control" value="{{ config('billing.fiuu.return_url') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Callback URL</label>
                        <input type="url" name="billing:fiuu:callback_url" class="form-control" value="{{ config('billing.fiuu.callback_url') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Requery URL</label>
                        <input type="url" name="billing:fiuu:requery_url" class="form-control" value="{{ config('billing.fiuu.requery_url') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Recurring URL</label>
                        <input type="url" name="billing:fiuu:recurring_url" class="form-control" value="{{ config('billing.fiuu.recurring_url') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Refund URL</label>
                        <input type="url" name="billing:fiuu:refund_url" class="form-control" value="{{ config('billing.fiuu.refund_url') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Invoice Lead Days</label>
                        <input type="number" min="1" name="billing:invoice_lead_days" class="form-control" value="{{ config('billing.invoice_lead_days', 7) }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Suspend Grace Hours</label>
                        <input type="number" min="1" name="billing:suspend_grace_hours" class="form-control" value="{{ config('billing.suspend_grace_hours', 24) }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Delete Grace Hours</label>
                        <input type="number" min="1" name="billing:delete_grace_hours" class="form-control" value="{{ config('billing.delete_grace_hours', 72) }}">
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary">Save Gateway Settings</button>
            </div>
        </form>
    </div>
@endsection
