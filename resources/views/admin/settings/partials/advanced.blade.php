<form action="{{ route('admin.settings.advanced') }}" method="POST">
    @php($googleEnabled = filter_var(old('services:google:enabled', config('services.google.enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($discordEnabled = filter_var(old('services:discord:enabled', config('services.discord.enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($discordCommunityEnabled = filter_var(old('services:discord:community_enabled', config('services.discord.community_enabled')), FILTER_VALIDATE_BOOLEAN))
    @php($googleConfigured = filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
    @php($discordConfigured = filled(config('services.discord.client_id')) && filled(config('services.discord.client_secret')))
    @php($discordCommunityConfigured = filled(config('services.discord.invite_url')) && filled(config('services.discord.guild_id')) && filled(config('services.discord.role_id')) && filled(config('services.discord.bot_token')))
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Cloudflare Turnstile</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Status</label>
                    <div>
                        @php($turnstileEnabled = filter_var(old('turnstile:enabled', config('turnstile.enabled')), FILTER_VALIDATE_BOOLEAN))
                        <select class="form-control" name="turnstile:enabled">
                            <option value="true" @if($turnstileEnabled) selected @endif>Enabled</option>
                            <option value="false" @if(!$turnstileEnabled) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">If enabled, login requires Cloudflare Turnstile verification before credentials can be entered. Other authentication flows can request challenges when needed.</p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Site Key</label>
                    <div>
                        <input type="text" class="form-control" name="turnstile:site_key" value="{{ old('turnstile:site_key', config('turnstile.site_key')) }}">
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Secret Key</label>
                    <div>
                        <input type="text" class="form-control" name="turnstile:secret_key" value="{{ old('turnstile:secret_key', config('turnstile.secret_key')) }}">
                        <p class="text-muted small">Used for communication between your site and Cloudflare. Keep this value secret.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Verify Domain</label>
                    <div>
                        @php($turnstileVerifyDomain = filter_var(old('turnstile:verify_domain', config('turnstile.verify_domain')), FILTER_VALIDATE_BOOLEAN))
                        <select class="form-control" name="turnstile:verify_domain">
                            <option value="true" @if($turnstileVerifyDomain) selected @endif>Enabled</option>
                            <option value="false" @if(!$turnstileVerifyDomain) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">If enabled, the solved Turnstile hostname must match your panel domain.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">HTTP Connections</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="form-group col-md-6">
                    <label class="control-label">Connection Timeout</label>
                    <div>
                        <input type="number" required class="form-control" name="pterodactyl:guzzle:connect_timeout" value="{{ old('pterodactyl:guzzle:connect_timeout', config('pterodactyl.guzzle.connect_timeout')) }}">
                        <p class="text-muted small">The amount of time in seconds to wait for a connection to be opened before throwing an error.</p>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label class="control-label">Request Timeout</label>
                    <div>
                        <input type="number" required class="form-control" name="pterodactyl:guzzle:timeout" value="{{ old('pterodactyl:guzzle:timeout', config('pterodactyl.guzzle.timeout')) }}">
                        <p class="text-muted small">The amount of time in seconds to wait for a request to be completed before throwing an error.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">Automatic Allocation Creation</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Status</label>
                    <div>
                        <select class="form-control" name="pterodactyl:client_features:allocations:enabled">
                            <option value="false">Disabled</option>
                            <option value="true" @if(old('pterodactyl:client_features:allocations:enabled', config('pterodactyl.client_features.allocations.enabled'))) selected @endif>Enabled</option>
                        </select>
                        <p class="text-muted small">If enabled users will have the option to automatically create new allocations for their server via the frontend.</p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Starting Port</label>
                    <div>
                        <input type="number" class="form-control" name="pterodactyl:client_features:allocations:range_start" value="{{ old('pterodactyl:client_features:allocations:range_start', config('pterodactyl.client_features.allocations.range_start')) }}">
                        <p class="text-muted small">The starting port in the range that can be automatically allocated.</p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Ending Port</label>
                    <div>
                        <input type="number" class="form-control" name="pterodactyl:client_features:allocations:range_end" value="{{ old('pterodactyl:client_features:allocations:range_end', config('pterodactyl.client_features.allocations.range_end')) }}">
                        <p class="text-muted small">The ending port in the range that can be automatically allocated.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title">OAuth Login Providers</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Google Status</label>
                    <div>
                        <select class="form-control" name="services:google:enabled">
                            <option value="true" @if($googleEnabled) selected @endif>Enabled</option>
                            <option value="false" @if(!$googleEnabled) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">
                            Allow users to link Google and sign in with it after linking.
                            <br>
                            Current configuration:
                            <strong>{{ $googleConfigured ? 'Ready' : 'Missing credentials' }}</strong>
                        </p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Google Client ID</label>
                    <div>
                        <input type="text" class="form-control" name="services:google:client_id" value="{{ old('services:google:client_id', config('services.google.client_id')) }}">
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Google Client Secret</label>
                    <div>
                        <input type="text" class="form-control" name="services:google:client_secret" value="{{ old('services:google:client_secret') }}" autocomplete="new-password">
                        <p class="text-muted small">Leave blank to keep the current secret. Enter <code>!e</code> to clear the stored secret.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-12">
                    <label class="control-label">Google Callback URL</label>
                    <div>
                        <input type="text" readonly class="form-control" value="{{ route('auth.oauth.callback', ['provider' => 'google']) }}">
                        <p class="text-muted small">Set this exact callback URL in your Google OAuth application configuration.</p>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Status</label>
                    <div>
                        <select class="form-control" name="services:discord:enabled">
                            <option value="true" @if($discordEnabled) selected @endif>Enabled</option>
                            <option value="false" @if(!$discordEnabled) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">
                            Allow users to link Discord and sign in with it after linking.
                            <br>
                            Current configuration:
                            <strong>{{ $discordConfigured ? 'Ready' : 'Missing credentials' }}</strong>
                        </p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Client ID</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:client_id" value="{{ old('services:discord:client_id', config('services.discord.client_id')) }}">
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Client Secret</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:client_secret" value="{{ old('services:discord:client_secret') }}" autocomplete="new-password">
                        <p class="text-muted small">Leave blank to keep the current secret. Enter <code>!e</code> to clear the stored secret.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-12">
                    <label class="control-label">Discord Callback URL</label>
                    <div>
                        <input type="text" readonly class="form-control" value="{{ route('auth.oauth.callback', ['provider' => 'discord']) }}">
                        <p class="text-muted small">Set this exact callback URL in your Discord application OAuth2 redirect settings.</p>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Community Status</label>
                    <div>
                        <select class="form-control" name="services:discord:community_enabled">
                            <option value="true" @if($discordCommunityEnabled) selected @endif>Enabled</option>
                            <option value="false" @if(!$discordCommunityEnabled) selected @endif>Disabled</option>
                        </select>
                        <p class="text-muted small">
                            Allow linked Discord users to join your Discord server and receive a role automatically from the account page.
                            <br>
                            Current configuration:
                            <strong>{{ $discordCommunityConfigured ? 'Ready' : 'Missing setup values' }}</strong>
                        </p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Guild ID</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:guild_id" value="{{ old('services:discord:guild_id', config('services.discord.guild_id')) }}">
                        <p class="text-muted small">The Discord server ID that users will be added into.</p>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label class="control-label">Discord Role ID</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:role_id" value="{{ old('services:discord:role_id', config('services.discord.role_id')) }}">
                        <p class="text-muted small">The role ID to assign automatically after the user is added to the server.</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label class="control-label">Discord Invite URL</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:invite_url" value="{{ old('services:discord:invite_url', config('services.discord.invite_url')) }}">
                        <p class="text-muted small">Users are redirected here after the panel finishes the auto-join and role assignment.</p>
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label class="control-label">Discord Bot Token</label>
                    <div>
                        <input type="text" class="form-control" name="services:discord:bot_token" value="{{ old('services:discord:bot_token') }}" autocomplete="new-password">
                        <p class="text-muted small">Leave blank to keep the current bot token. Enter <code>!e</code> to clear the stored token. The bot must already be inside the server and able to manage the configured role.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box box-primary">
        <div class="box-footer">
            {{ csrf_field() }}
            <button type="submit" name="_method" value="PATCH" class="btn btn-sm btn-primary pull-right">Save</button>
        </div>
    </div>
</form>
