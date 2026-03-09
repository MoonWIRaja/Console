@extends('layouts.admin')

@section('title')
    Billing
@endsection

@section('content-header')
    <h1>Billing<small>Coming soon.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Billing</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-body" style="padding: 32px;">
                    <div style="max-width: 720px; margin: 0 auto; text-align: center;">
                        <div style="display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; border-radius: 999px; background: rgba(60, 141, 188, 0.12); color: #3c8dbc; margin-bottom: 18px;">
                            <i class="fa fa-clock-o" style="font-size: 30px;"></i>
                        </div>
                        <h2 style="margin: 0 0 12px; font-size: 28px; font-weight: 700;">Billing Is Coming Soon</h2>
                        <p class="text-muted" style="font-size: 15px; line-height: 1.7; margin: 0;">
                            The billing module is temporarily disabled while it is being reworked.
                            Setup, ordering, and approval flows are paused for now.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
