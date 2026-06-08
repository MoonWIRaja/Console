@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
<div class="row admin-full-row">
    <div class="col-xs-12 admin-full-col">
        <div class="box
            @if($version->isLatestPanel())
                box-success
            @else
                box-danger
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">System Information</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    You are running Pterodactyl Panel version <code>{{ config('app.version') }}</code>. Your panel is up-to-date!
                @else
                    Your panel is <strong>not up-to-date!</strong> The latest version is <a href="https://github.com/Pterodactyl/Panel/releases/v{{ $version->getPanel() }}" target="_blank"><code>{{ $version->getPanel() }}</code></a> and you are currently running version <code>{{ config('app.version') }}</code>.
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row admin-overview-actions">
    <div class="col-xs-6 col-sm-3 text-center admin-overview-action-col">
        <a href="{{ $version->getDiscord() }}" class="btn btn-warning admin-overview-action-btn"><i class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center admin-overview-action-col">
        <a href="https://pterodactyl.io" class="btn btn-primary admin-overview-action-btn"><i class="fa fa-fw fa-link"></i> Documentation</a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center admin-overview-action-col">
        <a href="https://github.com/pterodactyl/panel" class="btn btn-primary admin-overview-action-btn"><i class="fa fa-fw fa-support"></i> GitHub</a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center admin-overview-action-col">
        <a href="{{ $version->getDonations() }}" class="btn btn-success admin-overview-action-btn"><i class="fa fa-fw fa-money"></i> Support the Project</a>
    </div>
</div>
@endsection
