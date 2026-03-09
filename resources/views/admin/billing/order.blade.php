@extends('layouts.admin')

@section('title')
    Billing Order
@endsection

@section('content-header')
    <h1>Billing Order<small>#{{ $order->id }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.billing') }}">Billing</a></li>
        <li class="active">Order #{{ $order->id }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Order Summary</h3>
                </div>
                <div class="box-body">
                    <dl class="dl-horizontal">
                        <dt>Status</dt>
                        <dd><span class="label label-default">{{ strtoupper($order->status) }}</span></dd>
                        <dt>User</dt>
                        <dd>{{ $order->user->email }}</dd>
                        <dt>Requested Server Name</dt>
                        <dd>{{ $order->server_name }}</dd>
                        <dt>Node</dt>
                        <dd>{{ $order->node_name }}</dd>
                        <dt>Game</dt>
                        <dd>{{ $order->game_name }}</dd>
                        <dt>Resources</dt>
                        <dd>{{ $order->cpu_cores }} vCore / {{ $order->memory_gb }} GB RAM / {{ $order->disk_gb }} GB Storage</dd>
                        <dt>Total</dt>
                        <dd>RM {{ number_format((float) $order->total, 2) }}</dd>
                        <dt>Placed At</dt>
                        <dd>{{ $order->created_at }}</dd>
                        @if($order->approver)
                            <dt>Reviewed By</dt>
                            <dd>{{ $order->approver->email }}</dd>
                        @endif
                        @if($order->server)
                            <dt>Provisioned Server</dt>
                            <dd><a href="{{ route('admin.servers.view', $order->server->id) }}">{{ $order->server->name }}</a></dd>
                        @endif
                    </dl>

                    @if(!empty($order->order_notes))
                        <hr>
                        <strong>Client Notes</strong>
                        <p class="text-muted" style="margin-top: 8px;">{{ $order->order_notes }}</p>
                    @endif

                    @if(!empty($order->admin_notes))
                        <hr>
                        <strong>Admin Notes</strong>
                        <p class="text-muted" style="margin-top: 8px; white-space: pre-wrap;">{{ $order->admin_notes }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            @if($order->status === \Pterodactyl\Models\BillingOrder::STATUS_PENDING)
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">Approve Order</h3>
                    </div>
                    <form action="{{ route('admin.billing.orders.approve', $order->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <div class="box-body">
                            <p class="text-muted">Approving this order will immediately try to provision a new server for the client using the saved billing profile snapshot.</p>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-success btn-sm pull-right">Approve & Provision</button>
                        </div>
                    </form>
                </div>

                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">Reject Order</h3>
                    </div>
                    <form action="{{ route('admin.billing.orders.reject', $order->id) }}" method="POST">
                        {!! csrf_field() !!}
                        <div class="box-body">
                            <div class="form-group">
                                <label class="control-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="5" placeholder="Explain why this order is being rejected."></textarea>
                            </div>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="btn btn-danger btn-sm pull-right">Reject Order</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Order State</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">This order is no longer pending, so no further billing action is available from this page.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
