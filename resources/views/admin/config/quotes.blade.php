@extends('admin.layouts.app')
@section('title', 'Quotes')
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>Quotes</h1>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">+ New Quote</a>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="padding:10px 15px;background:#f2dede;border:1px solid #ebccd1;border-radius:4px;color:#a94442;margin-bottom:15px;font-size:13px;">{{ session('error') }}</div>
@endif

<div class="card">
    @if(($quotes ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">No quotes created.</div>
    @else
    <table class="data-table">
        <thead><tr><th>Quote #</th><th>Client</th><th>Subject</th><th>Total</th><th>Valid Until</th><th>Stage</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        @foreach($quotes as $quote)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $quote->id }}</td>
            <td>{{ $quote->client->full_name ?? ($quote->firstname . ' ' . $quote->lastname) }}</td>
            <td>{{ $quote->subject }}</td>
            <td style="font-weight:600;">${{ number_format($quote->total ?? 0, 2) }}</td>
            <td style="font-size:12px;">{{ $quote->validuntil?->format('d M Y') ?? '&mdash;' }}</td>
            <td><span class="badge-{{ strtolower($quote->stage ?? 'draft') }}">{{ $quote->stage ?? 'Draft' }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-default btn-xs">Edit</a>
                <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" style="display:inline;" onsubmit="return confirm('Delete this quote?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @if(method_exists($quotes, 'links'))
    <div style="padding:10px 15px;">{{ $quotes->links() }}</div>
    @endif
    @endif
</div>
@endsection
