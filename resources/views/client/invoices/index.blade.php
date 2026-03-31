@extends('client.layouts.app')
@section('title', 'My Invoices')
@section('content')

<div class="page-header">
    <h1>My Invoices</h1>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td><a href="{{ route('client.invoices.show', $inv) }}" style="color:#337ab7; font-weight:500;">#{{ $inv->invoice_num ?? $inv->id }}</a></td>
                    <td style="color:#777;">{{ $inv->date?->format('d M Y') ?? '-' }}</td>
                    <td style="color:#777;">{{ $inv->due_date?->format('d M Y') ?? '-' }}</td>
                    <td style="font-weight:500;">${{ number_format($inv->total, 2) }}</td>
                    <td><span class="badge badge-{{ strtolower($inv->status) }}">{{ ucfirst($inv->status) }}</span></td>
                    <td>
                        @if(in_array(strtolower($inv->status), ['unpaid', 'overdue']))
                            <a href="{{ route('client.invoices.show', $inv) }}" class="btn btn-primary btn-xs">Pay Now</a>
                        @else
                            <a href="{{ route('client.invoices.show', $inv) }}" class="btn btn-default btn-xs">View</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:32px; color:#999;">No invoices found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->hasPages())
    <div style="margin-top:16px;">{{ $invoices->links() }}</div>
@endif

@endsection
