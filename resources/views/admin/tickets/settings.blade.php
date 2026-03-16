@extends('layouts.admin')

@section('title')
    Support Ticket Settings
@endsection

@section('content-header')
    <h1>Support Ticket Settings<small>Configure the Discord support-ticket bridge, channels, and launcher embed.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Support Tickets</a></li>
        <li class="active">Settings</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="callout callout-info">
                <h4 style="margin-top: 0;">Setup Order</h4>
                <p style="margin-bottom: 8px;">Fill these settings in this order to make setup easier:</p>
                    <ol style="margin-bottom: 0; padding-left: 18px;">
                        <li>Enter your Discord bot details: <strong>Guild ID</strong> and <strong>Bot Token</strong>. <strong>Application ID</strong> and <strong>Public Key</strong> are optional in gateway mode.</li>
                        <li>Select the Discord channels used for the launcher, the thread parent, and the log channel.</li>
                        <li>Leave <strong>Relay Webhook ID</strong>, <strong>Relay Webhook Token</strong>, and <strong>Bridge Shared Secret</strong> blank if you want the panel to generate them automatically on save.</li>
                        <li>Save the form, start the gateway sidecar, click <strong>Sync Launcher Embed</strong>, and only then enable <strong>Enable Tickets</strong>.</li>
                    </ol>
                </div>

            <form method="POST" action="{{ route('admin.tickets.settings.update') }}">
                @csrf
                @method('PATCH')

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Core</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Enable Tickets</label>
                            <select class="form-control" name="tickets:enabled">
                                <option value="true" @if(config('tickets.enabled')) selected @endif>Enabled</option>
                                <option value="false" @if(!config('tickets.enabled')) selected @endif>Disabled</option>
                            </select>
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Enables the entire ticketing module. Do not turn this on until the Discord bot, sidecar, and channels are fully configured.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Auto Create on Manual Checkout</label>
                            <select class="form-control" name="tickets:auto_create_on_manual_checkout">
                                <option value="true" @if(config('tickets.auto_create_on_manual_checkout', true)) selected @endif>Enabled</option>
                                <option value="false" @if(!config('tickets.auto_create_on_manual_checkout', true)) selected @endif>Disabled</option>
                            </select>
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                When a user checks out a manual order, renewal, or upgrade, the system automatically opens a payment ticket for that invoice.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Resolve Payment Ticket on Paid</label>
                            <select class="form-control" name="tickets:resolve_on_paid">
                                <option value="true" @if(config('tickets.resolve_on_paid', true)) selected @endif>Enabled</option>
                                <option value="false" @if(!config('tickets.resolve_on_paid', true)) selected @endif>Disabled</option>
                            </select>
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                If enabled, payment tickets automatically move to <code>resolved</code> once the invoice is paid or manually confirmed.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Discord</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Guild ID</label>
                            <input type="text" class="form-control" name="services:discord:guild_id" value="{{ old('services:discord:guild_id', config('services.discord.guild_id')) }}" placeholder="123456789012345678">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Your Discord server ID. Enable Developer Mode in Discord, then right-click the server and choose <strong>Copy Server ID</strong>.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Application ID</label>
                            <input type="text" class="form-control" name="services:discord:application_id" value="{{ old('services:discord:application_id', config('services.discord.application_id')) }}" placeholder="123456789012345678">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Optional in the current gateway-sidecar setup. Keep this only if you also use other Discord application features that need the application ID.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Application Public Key</label>
                            <input type="text" class="form-control" name="services:discord:application_public_key" value="{{ old('services:discord:application_public_key', config('services.discord.application_public_key')) }}" placeholder="hex_public_key_from_discord">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Optional in the current gateway-sidecar setup. Ticket launcher buttons and modals are handled by the running Discord bot through the gateway, not by Discord outgoing-webhook verification.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Bot Token</label>
                            <input type="text" class="form-control" name="services:discord:bot_token" value="" placeholder="{{ filled(config('services.discord.bot_token')) ? 'Stored securely. Leave blank to keep.' : 'paste bot token here' }}" autocomplete="new-password">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Your Discord bot token. Get it from the <strong>Bot</strong> tab in the Discord Developer Portal. This value is stored securely.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Launcher Channel ID</label>
                            <input type="text" class="form-control" name="tickets:discord:launcher_channel_id" value="{{ old('tickets:discord:launcher_channel_id', config('tickets.discord.launcher_channel_id')) }}" placeholder="channel id for the ticket embed">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                The channel where the bot posts the launcher embed with the <code>Payment</code>, <code>Refund</code>, and <code>Support</code> buttons.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Active Parent Channel ID</label>
                            <input type="text" class="form-control" name="tickets:discord:active_parent_channel_id" value="{{ old('tickets:discord:active_parent_channel_id', config('tickets.discord.active_parent_channel_id')) }}" placeholder="text channel id used as thread parent">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                The main text channel used as the parent for private ticket threads. Use a normal text channel, not a forum channel.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Log Channel ID</label>
                            <input type="text" class="form-control" name="tickets:discord:log_channel_id" value="{{ old('tickets:discord:log_channel_id', config('tickets.discord.log_channel_id')) }}" placeholder="channel id for bridge/system logs">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Audit/log channel for sync errors, ignored events, and bridge status messages. A staff-only channel is recommended.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Staff Role IDs</label>
                            <input type="text" class="form-control" name="tickets:discord:staff_role_ids" value="{{ old('tickets:discord:staff_role_ids', implode(',', array_filter((array) config('tickets.discord.staff_role_ids', [])))) }}" placeholder="123456789012345678,987654321098765432">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Enter one or more Discord role IDs for staff/admin users, separated by commas. Only these roles are allowed to reply as staff inside Discord tickets.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Launcher Message ID</label>
                            <input type="text" class="form-control" name="tickets:discord:launcher_message_id" value="{{ old('tickets:discord:launcher_message_id', config('tickets.discord.launcher_message_id')) }}" placeholder="optional, usually auto-filled after sync">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                You usually do not need to fill this manually. After clicking <strong>Sync Launcher Embed</strong>, the system stores the embed message ID here automatically.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Relay Webhook ID</label>
                            <input type="text" class="form-control" name="tickets:discord:relay_webhook_id" value="{{ old('tickets:discord:relay_webhook_id', config('tickets.discord.relay_webhook_id')) }}" placeholder="webhook id from the active parent channel">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                The webhook created on the <strong>Active Parent Channel</strong>. It is used to mirror user or admin names and avatars when relaying messages to Discord.
                                Leave this blank and the panel will try to create one automatically on save.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Relay Webhook Token</label>
                            <input type="text" class="form-control" name="tickets:discord:relay_webhook_token" value="" placeholder="{{ filled(config('tickets.discord.relay_webhook_token')) ? 'Stored securely. Leave blank to keep.' : 'paste webhook token here' }}" autocomplete="new-password">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                The webhook token paired with the Relay Webhook ID. This value is stored securely. Leave it blank to keep the current token, or leave both relay webhook fields blank to let the panel create a new webhook automatically.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Bridge Shared Secret</label>
                            <input type="text" class="form-control" name="tickets:bridge:shared_secret" value="" placeholder="{{ filled(config('tickets.bridge.shared_secret')) ? 'Stored securely. Leave blank to keep.' : 'random-long-secret-string' }}" autocomplete="new-password">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                A random secret used to validate callbacks from the Node sidecar to the panel. This must exactly match <code>TICKET_BRIDGE_SHARED_SECRET</code> in the sidecar environment. Leave it blank and the panel will generate one automatically on save.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Attachments</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Max Files Per Message</label>
                            <input type="number" class="form-control" name="tickets:attachments:max_files_per_message" value="{{ old('tickets:attachments:max_files_per_message', config('tickets.attachments.max_files_per_message', 5)) }}" min="1" max="10">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Maximum number of attachments allowed in a single ticket message. Recommended safe default: <strong>5</strong>.
                            </p>
                        </div>
                        <div class="form-group">
                            <label>Max File Size MB</label>
                            <input type="number" class="form-control" name="tickets:attachments:max_file_size_mb" value="{{ old('tickets:attachments:max_file_size_mb', config('tickets.attachments.max_file_size_mb', 20)) }}" min="1" max="100">
                            <p class="text-muted small" style="margin-top: 6px; margin-bottom: 0;">
                                Maximum file size allowed for each attachment. Common recommendation: <strong>20 MB</strong>.
                            </p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Bridge Status</h3>
                </div>
                <div class="box-body">
                    <p><strong>Last heartbeat:</strong> {{ config('tickets.bridge.last_heartbeat_at') ?: 'Never' }}</p>
                    <p class="text-muted small" style="margin-top: 6px;">
                        If this always stays at <strong>Never</strong>, the Node sidecar is not running or the shared secret does not match.
                    </p>
                    <p><strong>Meta:</strong></p>
                    <pre style="white-space: pre-wrap;">{{ json_encode(config('tickets.bridge.last_heartbeat_meta', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
                <div class="box-footer">
                    <form method="POST" action="{{ route('admin.tickets.settings.sync-launcher') }}">
                        @csrf
                        <button type="submit" class="btn btn-info btn-block">Sync Launcher Embed</button>
                    </form>
                </div>
            </div>

            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Sidecar</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        Run the Discord gateway sidecar from <code>services/discord-ticket-bridge</code>.
                        The bridge shared secret above must match the sidecar environment. This sidecar now handles launcher buttons, select menus, and modal submissions directly from the Discord gateway.
                    </p>
                    <p class="text-muted" style="margin-top: 10px;">
                        Quick steps:
                    </p>
                    <ol class="text-muted" style="padding-left: 18px; margin-bottom: 0;">
                        <li><code>cd services/discord-ticket-bridge</code></li>
                        <li><code>npm install</code></li>
                        <li>Set the bot token, panel URL, relay webhook ID, and bridge shared secret in the environment</li>
                        <li>Enable <strong>Server Members Intent</strong> and <strong>Message Content Intent</strong> in the Discord Developer Portal only if you need richer inbound Discord sync, then set <code>DISCORD_ENABLE_GUILD_MEMBERS=true</code> and <code>DISCORD_ENABLE_MESSAGE_CONTENT=true</code></li>
                        <li><code>npm start</code> or run it through systemd</li>
                    </ol>
                    <p class="text-muted" style="margin-top: 10px;">
                        Example systemd unit: <code>deploy/discord-ticket-bridge.service.example</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
