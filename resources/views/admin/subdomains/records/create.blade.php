@extends('layouts.admin')

@section('title')
    New Subdomain Template
@endsection

@section('content-header')
    <h1>New Subdomain Template<small>Create a reusable DNS record template for nests.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.subdomains.index') }}">Subdomains</a></li>
        <li class="active">New Template</li>
    </ol>
@endsection

@section('content')
    @include('admin.subdomains.partials.nav', ['activeTab' => 'record-create'])

    <form action="{{ route('admin.subdomains.records.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-xs-12 col-md-9">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Template Settings</h3>
                    </div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Template Name</label>
                            <div>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Minecraft Java Default" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Domain</label>
                            <div>
                                <select name="domain_id" class="form-control" required>
                                    @foreach ($domains as $domain)
                                        <option value="{{ $domain->id }}" @selected((int) old('domain_id') === $domain->id)>{{ $domain->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Nests</label>
                            <div>
                                <select name="nest_ids[]" id="nest_ids" class="form-control" multiple required>
                                    @foreach ($nests as $nest)
                                        <option value="{{ $nest->id }}">
                                            {{ $nest->name }} ({{ $nest->eggs_count }} egg{{ $nest->eggs_count === 1 ? '' : 's' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-muted small">Pick one or more nests. Every egg under the selected nest can use this template.</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Record Type</label>
                            <div>
                                <select name="record_type" id="record_type" class="form-control" required>
                                    <option value="CNAME">CNAME / A</option>
                                    <option value="SRV">SRV + Address Record</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="control-label">TTL</label>
                            <div>
                                <input type="number" name="ttl" class="form-control" value="{{ old('ttl', 300) }}" min="60" max="86400" />
                            </div>
                        </div>
                        <div class="form-group" id="proxied_group">
                            <label class="control-label">Cloudflare Proxy</label>
                            <div>
                                <label><input type="checkbox" name="proxied" value="1" @checked(old('proxied')) /> Enable proxy on address record</label>
                                <p class="text-muted small">Leave this off for Minecraft traffic unless you use a compatible Cloudflare product.</p>
                            </div>
                        </div>
                        <div id="srv_fields">
                            <div class="form-group">
                                <label class="control-label">Service</label>
                                <div>
                                    <input type="text" name="service" class="form-control" value="{{ old('service', '_minecraft') }}" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Protocol</label>
                                <div>
                                    <select name="protocol" class="form-control">
                                        <option value="_tcp" @selected(old('protocol', '_tcp') === '_tcp')>TCP</option>
                                        <option value="_udp" @selected(old('protocol') === '_udp')>UDP</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Priority</label>
                                <div>
                                    <input type="number" name="priority" class="form-control" value="{{ old('priority', 0) }}" min="0" max="65535" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Weight</label>
                                <div>
                                    <input type="number" name="weight" class="form-control" value="{{ old('weight', 0) }}" min="0" max="65535" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary pull-right">Save Template</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function () {
            $('#nest_ids').select2();

            function toggleSrvFields() {
                const isSrv = $('#record_type').val() === 'SRV';
                $('#srv_fields').toggle(isSrv);
            }

            $('#record_type').on('change', toggleSrvFields);
            toggleSrvFields();
        });
    </script>
@endsection
