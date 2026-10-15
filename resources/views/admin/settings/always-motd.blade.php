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

        @php($liveEnabled = old('live.enabled', data_get($config, 'live.enabled', true)) ? '1' : '0')
        @php($syncIcon = old('live.syncServerIcon', data_get($config, 'live.syncServerIcon', true)) ? '1' : '0')
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Server MOTD</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">Saving pushes this MOTD (and the icon, if icon sync is on) into every matching Minecraft server through Wings. Players only see it while a server is running; there is no offline fallback.</p>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Live MOTD</label>
                                <select class="form-control" name="live[enabled]">
                                    <option value="1" @if($liveEnabled === '1') selected @endif>Enabled</option>
                                    <option value="0" @if($liveEnabled === '0') selected @endif>Disabled</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Sync icon to servers</label>
                                <select class="form-control" name="live[syncServerIcon]">
                                    <option value="1" @if($syncIcon === '1') selected @endif>Yes</option>
                                    <option value="0" @if($syncIcon === '0') selected @endif>No</option>
                                </select>
                                <p class="text-muted small admin-help-tight">Also writes the icon as <code>server-icon.png</code>.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="live[runningDescription]" rows="4" placeholder="§6Your Network\n§7Powered by your panel">{{ old('live.runningDescription', data_get($config, 'live.runningDescription')) }}</textarea>
                            <p class="text-muted small admin-help-tight">Written into <code>server.properties</code>. Bedrock gets a plain one-line version automatically.</p>
                        </div>
                        <p class="text-muted small admin-text-last">Config file: <code>{{ $meta['runtime_config_path'] }}</code></p>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Server Icon</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Current icon</label>
                                <div class="admin-icon-preview-wrap">
                                    @if($meta['icon_data_uri'])
                                        <img src="{{ $meta['icon_data_uri'] }}" alt="Minecraft MOTD icon" width="64" height="64" class="admin-icon-preview-image">
                                    @else
                                        <span class="text-muted small">No icon yet</span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Upload custom icon</label>
                                <input type="file" class="form-control" name="motd_icon" accept="image/png,image/jpeg,image/gif,image/webp">
                                <p class="text-muted small admin-help-tight">Converted to a square 64x64 PNG. Saved with the form.</p>
                                <button type="submit" class="btn btn-default btn-sm" name="motd[sync_panel_logo]" value="1" data-as-action style="margin-top:8px">Use panel logo and save</button>
                                <p class="text-muted small admin-help-tight">Copies the current panel logo into the icon and saves right away. An uploaded file takes priority.</p>
                            </div>
                        </div>
                        <p class="text-muted small admin-text-last">Icon file: <code>{{ $meta['runtime_icon_path'] }}</code></p>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Server Matching</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted small">Which servers get the MOTD. Leave everything blank to detect Minecraft servers automatically.</p>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label>Nest names</label>
                                <input type="text" class="form-control" name="detection[nestNamesInput]" value="{{ old('detection.nestNamesInput', $meta['detection_nest_names_input']) }}" placeholder="Minecraft">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Nest IDs</label>
                                <input type="text" class="form-control" name="detection[nestIdsInput]" value="{{ old('detection.nestIdsInput', $meta['detection_nest_ids_input']) }}" placeholder="1, 2">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label>Egg names</label>
                                <input type="text" class="form-control" name="detection[eggNamesInput]" value="{{ old('detection.eggNamesInput', $meta['detection_egg_names_input']) }}" placeholder="Paper, Fabric">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Egg IDs</label>
                                <input type="text" class="form-control" name="detection[eggIdsInput]" value="{{ old('detection.eggIdsInput', $meta['detection_egg_ids_input']) }}" placeholder="5, 7">
                            </div>
                            <div class="form-group col-md-4">
                                <label>Excluded egg IDs</label>
                                <input type="text" class="form-control" name="excludeEggsInput" value="{{ old('excludeEggsInput', $meta['exclude_eggs_input']) }}" placeholder="28, 31">
                                <p class="text-muted small admin-help-tight">Always ignored, even if they match.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">Save Minecraft MOTD</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
