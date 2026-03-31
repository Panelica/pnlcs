@extends('client.layouts.app')
@section('title', 'Invoice #'. ($invoice->invoice_num ?? $invoice->id))
@section('styles')
<style>
    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .invoice-meta { font-size: 13px; color: #777; line-height: 1.8; }
    .totals-table { width: 300px; margin-left: auto; font-size: 13px; }
    .totals-table td { padding: 5px 10px; }
    .totals-table .grand-total { font-weight: 600; font-size: 15px; border-top: 2px solid #333; }
</style>
@endsection
@section('content')

<div class="page-header">
    <h1>Invoice #{{ $invoice->invoice_num ?? $invoice->id }}</h1>
    <span class="badge badge-{{ strtolower($invoice->status) }}" style="font-size:12px; padding:4px 12px;">{{ ucfirst($invoice->status) }}</span>
</div>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <div class="invoice-header">
            <div class="invoice-meta">
                <div><strong>Date:</strong> {{ $invoice->date?->format('d M Y') ?? 'N/A' }}</div>
                <div><strong>Due Date:</strong> {{ $invoice->due_date?->format('d M Y') ?? 'N/A' }}</div>
                @if($invoice->payment_method)
                <div><strong>Payment Method:</strong> {{ ucwords(str_replace('_', ' ', $invoice->payment_method)) }}</div>
                @endif
            </div>
        </div>

        <table class="data-table" style="margin-bottom:16px;">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align:right; width:120px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td style="text-align:right;">${{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals-table">
            @if($invoice->subtotal && $invoice->subtotal != $invoice->total)
            <tr>
                <td style="color:#777;">Subtotal:</td>
                <td style="text-align:right;">${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @endif
            @if($invoice->tax ?? false)
            <tr>
                <td style="color:#777;">Tax:</td>
                <td style="text-align:right;">${{ number_format($invoice->tax, 2) }}</td>
            </tr>
            @endif
            <tr class="grand-total">
                <td>Total:</td>
                <td style="text-align:right;">${{ number_format($invoice->total, 2) }}</td>
            </tr>
        </table>

        @if(in_array(strtolower($invoice->status), ['unpaid', 'overdue']))
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #eee;">
            <a href="#" class="btn btn-primary">Pay Now &rarr;</a>
        </div>
        @endif
    </div>
</div>

<a href="{{ route('client.invoices.index') }}" style="color:#337ab7; font-size:13px;">&larr; Back to Invoices</a>

@endsection
