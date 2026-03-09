@extends('layouts.admin')

@section('title')
    Settings
@endsection

@section('content-header')
    <h1>Panel Settings<small>Configure Pterodactyl to your liking.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    @include('admin.settings.partials.notice')

    <div class="row">
        <div class="col-xs-12">
            @include('admin.settings.partials.general', ['languages' => $languages])
            @include('admin.settings.partials.mail')
            @include('admin.settings.partials.advanced')
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent

    @include('admin.settings.partials.mail-scripts')
@endsection
