@extends('layouts.admin')

@section('title')
    Billing Invoices
@endsection

@section('content-header')
    <h1>Billing Invoices<small>Inspect invoice lifecycle and linked orders.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')
    <div class="box">
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('admin.billing.invoices.view', $invoice->id) }}">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->user->email }}</td>
                            <td>{{ strtoupper($invoice->type) }}</td>
                            <td><span class="label label-default">{{ strtoupper($invoice->status) }}</span></td>
                            <td>{{ $invoice->due_at ?? 'N/A' }}</td>
                            <td>RM {{ number_format((float) $invoice->grand_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No invoices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer clearfix">{{ $invoices->links() }}</div>
    </div>
@endsection
