@extends('layouts.admin')

@section('title')
    Billing Node Setup
@endsection

@section('content-header')
    <h1>Billing Setup<small>{{ $node->name }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">{{ $node->name }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="callout callout-info">
                <p>
                    Billing availability right now:
                    <strong>max {{ $availability['cpu_remaining'] }} vCore per order</strong>,
                    <strong>{{ $availability['memory_remaining_gb'] }} GB RAM</strong>,
                    <strong>{{ $availability['disk_remaining_gb'] }} GB Storage</strong>,
                    <strong>{{ $availability['free_allocations'] }} free allocation(s)</strong>.
                </p>
                <p class="text-muted" style="margin: 8px 0 0;">
                    vCore is treated as a per-order limit only. RAM and Storage are the live sellable stock for billing.
                    Current breakdown: <strong>{{ $availability['billing_memory_remaining_gb'] }} GB billing RAM</strong> vs
                    <strong>{{ $availability['node_memory_remaining_gb'] }} GB physical node RAM</strong>,
                    and <strong>{{ $availability['billing_disk_remaining_gb'] }} GB billing disk</strong> vs
                    <strong>{{ $availability['node_disk_remaining_gb'] }} GB physical node disk</strong>.
                    The sellable value always follows the lower side.
                </p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.billing.nodes.update', $node->id) }}" method="POST">
        {!! csrf_field() !!}
        <input type="hidden" name="_method" value="PATCH">
        <div class="row">
            <div class="col-xs-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Node Billing Configuration</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-3">
                                <label class="control-label">Billing Enabled</label>
                                <select name="enabled" class="form-control">
                                    <option value="1" @if(old('enabled', $config->enabled) == 1) selected @endif>Enabled</option>
                                    <option value="0" @if(old('enabled', $config->enabled) == 0) selected @endif>Disabled</option>
                                </select>
                            </div>
                            <div class="form-group col-md-5">
                                <label class="control-label">Display Name</label>
                                <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $config->display_name) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Show Remaining Capacity</label>
                                <select name="show_remaining_capacity" class="form-control">
                                    <option value="1" @if(old('show_remaining_capacity', $config->show_remaining_capacity) == 1) selected @endif>Show to Clients</option>
                                    <option value="0" @if(old('show_remaining_capacity', $config->show_remaining_capacity) == 0) selected @endif>Hide from Clients</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label class="control-label">Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $config->description) }}</textarea>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Maximum vCore Per Order</label>
                                <input type="number" min="0" name="cpu_stock" class="form-control" value="{{ old('cpu_stock', $config->cpu_stock) }}">
                                <p class="text-muted small">This value does not decrease after orders. It only controls the maximum selectable vCore per order.</p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Sellable RAM Stock (GB)</label>
                                <input type="number" min="0" name="memory_stock_gb" class="form-control" value="{{ old('memory_stock_gb', $config->memory_stock_gb) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Sellable Storage Stock (GB)</label>
                                <input type="number" min="0" name="disk_stock_gb" class="form-control" value="{{ old('disk_stock_gb', $config->disk_stock_gb) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Price Per 1 vCore (RM)</label>
                                <input type="number" min="0" step="0.01" name="price_per_vcore" class="form-control" value="{{ old('price_per_vcore', $config->price_per_vcore) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Price Per 1 GB RAM (RM)</label>
                                <input type="number" min="0" step="0.01" name="price_per_gb_ram" class="form-control" value="{{ old('price_per_gb_ram', $config->price_per_gb_ram) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Price Per 10 GB Storage (RM)</label>
                                <input type="number" min="0" step="0.01" name="price_per_10gb_disk" class="form-control" value="{{ old('price_per_10gb_disk', $config->price_per_10gb_disk) }}">
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="form-group col-md-3">
                                <label class="control-label">Default Allocation Limit</label>
                                <input type="number" min="0" name="default_allocation_limit" class="form-control" value="{{ old('default_allocation_limit', $config->default_allocation_limit) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Default Database Limit</label>
                                <input type="number" min="0" name="default_database_limit" class="form-control" value="{{ old('default_database_limit', $config->default_database_limit) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Default Backup Limit</label>
                                <input type="number" min="0" name="default_backup_limit" class="form-control" value="{{ old('default_backup_limit', $config->default_backup_limit) }}">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Default IO Weight</label>
                                <input type="number" min="10" max="1000" name="default_io" class="form-control" value="{{ old('default_io', $config->default_io) }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Default Swap (MB)</label>
                                <input type="number" min="-1" name="default_swap" class="form-control" value="{{ old('default_swap', $config->default_swap) }}">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Disable OOM Killer</label>
                                <select name="default_oom_disabled" class="form-control">
                                    <option value="1" @if(old('default_oom_disabled', $config->default_oom_disabled) == 1) selected @endif>Yes</option>
                                    <option value="0" @if(old('default_oom_disabled', $config->default_oom_disabled) == 0) selected @endif>No</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Start On Completion</label>
                                <select name="start_on_completion" class="form-control">
                                    <option value="1" @if(old('start_on_completion', $config->start_on_completion) == 1) selected @endif>Yes</option>
                                    <option value="0" @if(old('start_on_completion', $config->start_on_completion) == 0) selected @endif>No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm pull-right">Save Billing Setup</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Add Nests</h3>
                </div>
                <form action="{{ route('admin.billing.nodes.games.store', $node->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label class="control-label">Select Nest</label>
                                <div class="row">
                                    @foreach($nests as $nest)
                                        <div class="col-md-4 col-sm-6">
                                            <label style="display: block; border: 1px solid #d2d6de; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; cursor: pointer;">
                                                <input
                                                    type="checkbox"
                                                    name="nest_ids[]"
                                                    value="{{ $nest->id }}"
                                                    style="margin-right: 8px;"
                                                    @php($checkedNestIds = collect(old('nest_ids', $selectedNestIds ?? []))->map(fn ($id) => (int) $id))
                                                    @if($checkedNestIds->contains($nest->id)) checked @endif
                                                >
                                                <strong>{{ $nest->name }}</strong><br>
                                                <small class="text-muted">{{ $nest->eggs_count }} egg{{ $nest->eggs_count === 1 ? '' : 's' }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="text-muted small" style="margin-top: 10px;">
                                    Tick one or more nests. Every egg under the selected nest list will be exposed automatically on this billing node.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm pull-right">Save Selected Nests</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Orders For This Node</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Game</th>
                                <th>Resources</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr>
                                    <td><code>#{{ $order->id }}</code></td>
                                    <td>{{ $order->user->email }}</td>
                                    <td>{{ $order->game_name }}</td>
                                    <td>{{ $order->cpu_cores }} / {{ $order->memory_gb }} GB / {{ $order->disk_gb }} GB</td>
                                    <td><span class="label label-default">{{ strtoupper($order->status) }}</span></td>
                                    <td>RM {{ number_format((float) $order->total, 2) }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.billing.orders.view', $order->id) }}" class="btn btn-xs btn-default">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No billing orders have targeted this node yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
