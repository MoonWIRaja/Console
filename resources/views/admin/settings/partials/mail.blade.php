<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Email Settings</h3>
    </div>
    @if(config('mail.default') !== 'smtp')
        <div class="box-body">
            <div class="row">
                <div class="col-xs-12">
                    <div class="alert alert-info no-margin-bottom">
                        This interface is limited to instances using SMTP as the mail driver. Please either use <code>php artisan p:environment:mail</code> command to update your email settings, or set <code>MAIL_DRIVER=smtp</code> in your environment file.
                    </div>
                </div>
            </div>
        </div>
    @else
        <form>
            <div class="box-body">
                <div class="row">
                    <div class="form-group col-md-6">
                        <label class="control-label">SMTP Host</label>
                        <div>
                            <input required type="text" class="form-control" name="mail:mailers:smtp:host" value="{{ old('mail:mailers:smtp:host', config('mail.mailers.smtp.host')) }}" />
                            <p class="text-muted small">Enter the SMTP server address that mail should be sent through.</p>
                        </div>
                    </div>
                    <div class="form-group col-md-2">
                        <label class="control-label">SMTP Port</label>
                        <div>
                            <input required type="number" class="form-control" name="mail:mailers:smtp:port" value="{{ old('mail:mailers:smtp:port', config('mail.mailers.smtp.port')) }}" />
                            <p class="text-muted small">Enter the SMTP server port that mail should be sent through.</p>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="control-label">Encryption</label>
                        <div>
                            @php
                                $encryption = old('mail:mailers:smtp:encryption', config('mail.mailers.smtp.encryption'));
                            @endphp
                            <select name="mail:mailers:smtp:encryption" class="form-control">
                                <option value="" @if($encryption === '') selected @endif>None</option>
                                <option value="tls" @if($encryption === 'tls') selected @endif>Transport Layer Security (TLS)</option>
                                <option value="ssl" @if($encryption === 'ssl') selected @endif>Secure Sockets Layer (SSL)</option>
                            </select>
                            <p class="text-muted small">Select the type of encryption to use when sending mail.</p>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="control-label">Username <span class="field-optional"></span></label>
                        <div>
                            <input type="text" class="form-control" name="mail:mailers:smtp:username" value="{{ old('mail:mailers:smtp:username', config('mail.mailers.smtp.username')) }}" />
                            <p class="text-muted small">The username to use when connecting to the SMTP server.</p>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="control-label">Password <span class="field-optional"></span></label>
                        <div>
                            <input type="password" class="form-control" name="mail:mailers:smtp:password"/>
                            <p class="text-muted small">The password to use in conjunction with the SMTP username. Leave blank to continue using the existing password. To set the password to an empty value enter <code>!e</code> into the field.</p>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <hr />
                    <div class="form-group col-md-6">
                        <label class="control-label">Mail From</label>
                        <div>
                            <input required type="email" class="form-control" name="mail:from:address" value="{{ old('mail:from:address', config('mail.from.address')) }}" />
                            <p class="text-muted small">Enter an email address that all outgoing emails will originate from.</p>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="control-label">Mail From Name <span class="field-optional"></span></label>
                        <div>
                            <input type="text" class="form-control" name="mail:from:name" value="{{ old('mail:from:name', config('mail.from.name')) }}" />
                            <p class="text-muted small">The name that emails should appear to come from.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                {{ csrf_field() }}
                <div class="pull-right">
                    <button type="button" id="testButton" class="btn btn-sm btn-success">Test</button>
                    <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save</button>
                </div>
            </div>
        </form>
    @endif
</div>
