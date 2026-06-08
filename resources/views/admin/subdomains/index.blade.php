@extends('layouts.admin')

@section('title')
    Subdomains
@endsection

@section('content-header')
    <h1>Subdomains<small>Manage DNS domains and server templates.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Subdomains</li>
    </ol>
@endsection

@section('content')
    @include('admin.subdomains.partials.nav', ['activeTab' => 'index'])

    <div class="row admin-full-row">
        <div class="col-xs-12 col-lg-5 admin-full-col-stack">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Domains</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.subdomains.domains.create') }}" class="btn btn-sm btn-primary">New Domain</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Provider</th>
                                <th>Templates</th>
                                <th>Active</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($domains as $domain)
                                <tr>
                                    <td><code>{{ $domain->name }}</code></td>
                                    <td>{{ ucfirst($domain->provider) }}</td>
                                    <td>{{ $domain->records_count }}</td>
                                    <td>{{ $domain->subdomains_count }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.subdomains.domains.edit', $domain) }}" class="btn btn-xs btn-default">Edit</a>
                                        <form action="{{ route('admin.subdomains.domains.delete', $domain) }}" method="POST" class="admin-inline-form" onsubmit="return confirm('Delete this domain?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-xs btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No subdomain domains configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-lg-7 admin-full-col-stack">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Record Templates</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.subdomains.records.create') }}" class="btn btn-sm btn-primary">New Template</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Domain</th>
                                <th>Type</th>
                                <th>Nests</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr>
                                    <td>{{ $record->name }}</td>
                                    <td><code>{{ $record->domain->name }}</code></td>
                                    <td>{{ $record->record_type }}</td>
                                    <td>{{ $record->nests->pluck('name')->implode(', ') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.subdomains.records.edit', $record) }}" class="btn btn-xs btn-default">Edit</a>
                                        <form action="{{ route('admin.subdomains.records.delete', $record) }}" method="POST" class="admin-inline-form" onsubmit="return confirm('Delete this template?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-xs btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No subdomain templates configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
