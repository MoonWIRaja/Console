@extends('layouts.admin')

@section('title')
    Minecraft MOTD
@endsection

@section('content-header')
    <h1>Minecraft MOTD<small>Configure live MOTD and icon sync for Minecraft servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Minecraft MOTD</li>
    </ol>
@endsection

@section('content')
    <form id="alwaysmotd-settings-form" method="POST" action="{{ route('admin.always-motd.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')

        <div class="row">
            <div class="col-md-8">
                <div class="callout callout-info">
                    <h4 class="admin-callout-title">How It Works</h4>
                    <p class="admin-text-gap-sm">
                        This page stores a live Minecraft MOTD config at <code>{{ $meta['runtime_config_path'] }}</code>.
                        Saving the form pushes the current MOTD and icon into matching Minecraft servers through Wings.
                    </p>
                    <p class="admin-text-last">
                        Players only see this MOTD while a server is running. This mode does not provide offline fallback MOTD.
                    </p>
                </div>

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Live Sync</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Live MOTD Status</label>
                                @php($liveEnabled = old('live.enabled', data_get($config, 'live.enabled', true)) ? '1' : '0')
                                <select class="form-control" name="live[enabled]">
                                    <option value="1" @if($liveEnabled === '1') selected @endif>Enabled</option>
                                    <option value="0" @if($liveEnabled === '0') selected @endif>Disabled</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Sync Icon To Servers</label>
                                @php($syncIcon = old('live.syncServerIcon', data_get($config, 'live.syncServerIcon', true)) ? '1' : '0')
                                <select class="form-control" name="live[syncServerIcon]">
                                    <option value="1" @if($syncIcon === '1') selected @endif>Yes</option>
                                    <option value="0" @if($syncIcon === '0') selected @endif>No</option>
                                </select>
                                <p class="text-muted small admin-help-tight">
                                    When enabled, matching Minecraft servers also receive the current icon as <code>server-icon.png</code>.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Server Matching</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Nest Names</label>
                                <input type="text" class="form-control" name="detection[nestNamesInput]" value="{{ old('detection.nestNamesInput', $meta['detection_nest_names_input']) }}" placeholder="Minecraft">
                                <p class="text-muted small admin-help-tight">
                                    Optional comma-separated nest names. Leave all matcher fields blank to rely on automatic Minecraft detection.
                                </p>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Nest IDs</label>
                                <input type="text" class="form-control" name="detection[nestIdsInput]" value="{{ old('detection.nestIdsInput', $meta['detection_nest_ids_input']) }}" placeholder="1, 2">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Egg Names</label>
                                <input type="text" class="form-control" name="detection[eggNamesInput]" value="{{ old('detection.eggNamesInput', $meta['detection_egg_names_input']) }}" placeholder="Paper, Fabric">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Egg IDs</label>
                                <input type="text" class="form-control" name="detection[eggIdsInput]" value="{{ old('detection.eggIdsInput', $meta['detection_egg_ids_input']) }}" placeholder="5, 7">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Excluded Egg IDs</label>
                                <input type="text" class="form-control" name="excludeEggsInput" value="{{ old('excludeEggsInput', $meta['exclude_eggs_input']) }}" placeholder="28, 31">
                                <p class="text-muted small admin-help-tight">
                                    These eggs are always ignored even if they match the nest or egg filters.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Server MOTD</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group admin-form-group-tight">
                            <label>Description</label>
                            <textarea class="form-control" name="live[runningDescription]" rows="4" placeholder="§6Your Network\n§7Powered by your panel">{{ old('live.runningDescription', data_get($config, 'live.runningDescription')) }}</textarea>
                            <p class="text-muted small admin-help-tight">
                                This MOTD is written into <code>server.properties</code> for matching Minecraft servers. Bedrock uses a plain one-line version automatically.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">Save Minecraft MOTD</button>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Branding</h3>
                    </div>
                    <div class="box-body">
                        @if($meta['icon_data_uri'])
                            <div class="admin-icon-preview-wrap">
                                <img src="{{ $meta['icon_data_uri'] }}" alt="Minecraft MOTD icon" width="64" height="64" class="admin-icon-preview-image">
                            </div>
                        @endif
                        <p class="text-muted small admin-text-gap-sm admin-text-no-top">
                            Use the upload field below to replace <code>server-icon.png</code> with a square 64x64 PNG generated from your image.
                        </p>
                        <p class="text-muted small admin-text-gap-sm">
                            Current runtime icon path: <code>{{ $meta['runtime_icon_path'] }}</code>
                        </p>
                        <p class="text-muted small admin-text-last">
                            Saving this page also pushes the icon to matching live servers when icon sync is enabled.
                        </p>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Runtime Files</h3>
                    </div>
                    <div class="box-body">
                        <p><strong>Active source:</strong><br><code>{{ $meta['source_config_path'] }}</code></p>
                        <p><strong>Admin-managed runtime config:</strong><br><code>{{ $meta['runtime_config_path'] }}</code></p>
                        <p class="text-muted small admin-text-gap-md admin-text-last">
                            The live Minecraft MOTD feature stores its admin-managed config under <code>storage/app/always-motd</code>. This keeps edits inside a writable Laravel storage path.
                        </p>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Icon Controls</h3>
                    </div>
                    <div class="box-body">
                        <button type="submit" class="btn btn-default btn-sm" name="motd[sync_panel_logo]" value="1">
                            Use Current Panel Logo And Save
                        </button>
                        <p class="text-muted small admin-text-gap-sm admin-text-gap-bottom">
                            This is an action button, not a persistent setting. It copies the current panel logo into the Minecraft MOTD icon and saves the whole form immediately.
                        </p>
                        <div class="form-group admin-form-group-tight">
                            <label>Upload Custom Icon</label>
                            <input type="file" class="form-control" name="motd_icon" accept="image/png,image/jpeg,image/gif,image/webp">
                            <p class="text-muted small admin-help-tight">
                                Upload takes priority over the panel-logo button in the same save request.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
