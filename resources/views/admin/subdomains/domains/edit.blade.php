@extends('layouts.admin')

@section('title')
    Edit Subdomain Domain
@endsection

@section('content-header')
    <h1>Edit Subdomain Domain<small>{{ $domain->name }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.subdomains.index') }}">Subdomains</a></li>
        <li class="active">Edit Domain</li>
    </ol>
@endsection

@section('content')
    @include('admin.subdomains.partials.nav', ['activeTab' => 'domain-edit'])

    <form action="{{ route('admin.subdomains.domains.update', $domain) }}" method="POST">
        @csrf
        @method('PATCH')
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
                                <input type="text" name="name" class="form-control" value="{{ old('name', $domain->name) }}" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">API Token</label>
                            <div>
                                <input type="text" name="api_token" class="form-control" value="{{ old('api_token') }}" />
                                <p class="text-muted small">Leave blank to keep the current token unchanged.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Zone Identifier</label>
                            <div>
                                <input type="text" name="zone_identifier" class="form-control" value="{{ old('zone_identifier', $domain->zone_identifier) }}" required />
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">Update Domain</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
