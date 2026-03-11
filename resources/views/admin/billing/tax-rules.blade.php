@extends('layouts.admin')

@section('title')
    Billing Tax Rules
@endsection

@section('content-header')
    <h1>Billing Tax Rules<small>Configure tax matching and calculation rules.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="row">
        <div class="col-md-4">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Create Rule</h3></div>
                <form method="POST" action="{{ route('admin.billing.tax-rules.store') }}">
                    @csrf
                    <div class="box-body">
                        <div class="form-group"><label>Name</label><input type="text" name="name" class="form-control"></div>
                        <div class="form-group"><label>Priority</label><input type="number" name="priority" class="form-control" value="100"></div>
                        <div class="form-group"><label>Country Code</label><input type="text" name="country_code" class="form-control"></div>
                        <div class="form-group"><label>Rate Type</label><select name="rate_type" class="form-control"><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select></div>
                        <div class="form-group"><label>Rate Value</label><input type="number" step="0.0001" min="0" name="rate_value" class="form-control" value="0"></div>
                        <div class="checkbox"><label><input type="checkbox" name="apply_to_new_orders" value="1" checked> Apply to new orders</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="apply_to_renewals" value="1" checked> Apply to renewals</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="apply_to_upgrades" value="1" checked> Apply to upgrades</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
                    </div>
                    <div class="box-footer"><button type="submit" class="btn btn-primary">Create Rule</button></div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="box">
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead><tr><th>Name</th><th>Country</th><th>Type</th><th>Value</th><th>Priority</th><th>Active</th></tr></thead>
                        <tbody>
                            @forelse($rules as $rule)
                                <tr>
                                    <td>{{ $rule->name }}</td>
                                    <td>{{ $rule->country_code ?: 'ANY' }}</td>
                                    <td>{{ strtoupper($rule->rate_type) }}</td>
                                    <td>{{ $rule->rate_value }}</td>
                                    <td>{{ $rule->priority }}</td>
                                    <td>{{ $rule->is_active ? 'Yes' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No tax rules found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
