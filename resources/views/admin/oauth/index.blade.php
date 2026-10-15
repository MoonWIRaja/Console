@extends('layouts.admin')

@section('title')
    OAuth Settings
@endsection

@section('content-header')
    <h1>OAuth Settings<small>Configure Google and Discord sign-in for panel users.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">OAuth</li>
    </ol>
@endsection

@section('content')
    @php($googleEnabled = filter_var(old('services:google:enabled', config('services.google.enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($discordEnabled = filter_var(old('services:discord:enabled', config('services.discord.enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($googleConfigured = filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
    @php($discordConfigured = filled(config('services.discord.client_id')) && filled(config('services.discord.client_secret')))

    <div class="row">
        <div class="col-xs-12">
            <form method="POST" action="{{ route('admin.oauth.update') }}">
                @csrf
                @method('PATCH')

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Google OAuth</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">Google OAuth is only for Google account login and onboarding.</p>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Status</label>
                                <select class="form-control" name="services:google:enabled">
                                    <option value="true" @if($googleEnabled) selected @endif>Enabled</option>
                                    <option value="false" @if(!$googleEnabled) selected @endif>Disabled</option>
                                </select>
                                <p class="text-muted small admin-help-tight">
                                    Allow users to sign in with Google after linking or during onboarding.
                                    <br>
                                    Current configuration: <strong>{{ $googleConfigured ? 'Ready' : 'Missing credentials' }}</strong>
                                </p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Client ID</label>
                                <input type="text" class="form-control" name="services:google:client_id" value="{{ old('services:google:client_id', config('services.google.client_id')) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Client Secret</label>
                                <input type="text" class="form-control" name="services:google:client_secret" value="{{ old('services:google:client_secret') }}" autocomplete="new-password">
                                <p class="text-muted small admin-help-tight">Leave blank to keep the current secret. Enter <code>!e</code> to clear it.</p>
                            </div>
                        </div>
                        <div class="form-group admin-form-group-tight">
                            <label class="control-label">Callback URL</label>
                            <input type="text" readonly class="form-control" value="{{ route('auth.oauth.callback', ['provider' => 'google']) }}">
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Discord OAuth</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">Discord OAuth is only for Discord account linking and login. The Discord bot, guild, community auto-join and other bot features are managed from <a href="{{ route('admin.discord') }}">Discord Settings</a>.</p>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Status</label>
                                <select class="form-control" name="services:discord:enabled">
                                    <option value="true" @if($discordEnabled) selected @endif>Enabled</option>
                                    <option value="false" @if(!$discordEnabled) selected @endif>Disabled</option>
                                </select>
                                <p class="text-muted small admin-help-tight">
                                    Allow users to sign in with Discord and link Discord to panel features.
                                    <br>
                                    Current configuration: <strong>{{ $discordConfigured ? 'Ready' : 'Missing credentials' }}</strong>
                                </p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Client ID</label>
                                <input type="text" class="form-control" name="services:discord:client_id" value="{{ old('services:discord:client_id', config('services.discord.client_id')) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Client Secret</label>
                                <input type="text" class="form-control" name="services:discord:client_secret" value="{{ old('services:discord:client_secret') }}" autocomplete="new-password">
                                <p class="text-muted small admin-help-tight">Leave blank to keep the current secret. Enter <code>!e</code> to clear it.</p>
                            </div>
                        </div>
                        <div class="form-group admin-form-group-tight">
                            <label class="control-label">Callback URL</label>
                            <input type="text" readonly class="form-control" value="{{ route('auth.oauth.callback', ['provider' => 'discord']) }}">
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Save OAuth Settings</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
@endsection
