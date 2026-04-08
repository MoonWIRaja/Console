@extends('layouts.admin')

@section('title')
    New Subdomain Domain
@endsection

@section('content-header')
    <h1>New Subdomain Domain<small>Add a DNS zone for server subdomains.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.subdomains.index') }}">Subdomains</a></li>
        <li class="active">New Domain</li>
    </ol>
@endsection

@section('content')
    @include('admin.subdomains.partials.nav', ['activeTab' => 'domain-create'])

    <form action="{{ route('admin.subdomains.domains.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-xs-12 col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Cloudflare Zone</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Domain Name</label>
                            <div>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="mc.example.com" required />
                                <p class="text-muted small">Players will connect to subdomains under this base domain.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Provider</label>
                            <div>
                                <input type="text" class="form-control" value="Cloudflare" disabled />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">API Token</label>
                            <div>
                                <input type="text" name="api_token" class="form-control" value="{{ old('api_token') }}" required />
                                <p class="text-muted small">Use a token with DNS edit permission for the selected zone.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Zone Identifier</label>
                            <div>
                                <input type="text" name="zone_identifier" class="form-control" value="{{ old('zone_identifier') }}" required />
                                <p class="text-muted small">This is the Cloudflare Zone ID shown on the domain overview page.</p>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">Save Domain</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
