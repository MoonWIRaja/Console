@php($panelLogo = config('app.logo') ? asset(config('app.logo')) : asset('assets/svgs/pterodactyl.svg'))
@php($level = (int) old('pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required')))
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Panel Settings</h3>
    </div>
    <form action="{{ route('admin.settings') }}" method="POST" enctype="multipart/form-data">
        <div class="box-body">
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Company Name</label>
                    <div>
                        <input type="text" class="form-control" name="app:name" value="{{ old('app:name', config('app.name')) }}" />
                        <p class="text-muted"><small>This is the name that is used throughout the panel and in emails sent to clients.</small></p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Require 2-Factor Authentication</label>
                    <div>
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-primary @if ($level == 0) active @endif">
                                <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="0" @if ($level == 0) checked @endif> Not Required
                            </label>
                            <label class="btn btn-primary @if ($level == 1) active @endif">
                                <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="1" @if ($level == 1) checked @endif> Admin Only
                            </label>
                            <label class="btn btn-primary @if ($level == 2) active @endif">
                                <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="2" @if ($level == 2) checked @endif> All Users
                            </label>
                        </div>
                        <p class="text-muted"><small>If enabled, any account falling into the selected grouping will be required to have 2-Factor authentication enabled to use the Panel.</small></p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Default Language</label>
                    <div>
                        <select name="app:locale" class="form-control">
                            @foreach($languages as $key => $value)
                                <option value="{{ $key }}" @if(config('app.locale') === $key) selected @endif>{{ $value }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted"><small>The default language to use when rendering UI components.</small></p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Current Logo</label>
                    <div style="padding: 12px; min-height: 132px; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb; border-radius: 8px; background: rgba(0, 0, 0, 0.02);">
                        <img src="{{ $panelLogo }}" alt="Panel Logo" style="max-width: 100%; max-height: 96px; object-fit: contain;">
                    </div>
                    <p class="text-muted"><small>This image is used for the favicon, sidebar logo, and login branding.</small></p>
                </div>
                <div class="form-group col-md-8">
                    <label class="control-label">Upload Logo <span class="field-optional"></span></label>
                    <div>
                        <input type="file" class="form-control" name="app_logo" accept=".png,.jpg,.jpeg,.webp,.svg,.ico">
                        <p class="text-muted"><small>Upload a square image for best results. Supported types: PNG, JPG, JPEG, WEBP, SVG, ICO. Max size: 2 MB.</small></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
        </div>
    </form>
</div>
