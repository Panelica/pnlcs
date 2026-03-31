@extends('admin.layouts.app')
@section('title', 'Quotes')
@section('content')
<div class="page-header">
    <h1>Quotes</h1>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">+ New Quote</a>
</div>

@if(session('success'))
<div style="padding:10px 15px;background:#dff0d8;border:1px solid #d6e9c6;border-radius:4px;color:#3c763d;margin-bottom:15px;font-size:13px;">{{ session('success') }}</div>
@endif

<div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
    @foreach([''=>'All','Draft'=>'Draft','Sent'=>'Sent','Accepted'=>'Accepted','Declined'=>'Declined'] as $val=>$label)
    <a href="{{ route('admin.quotes.index', ['status'=>$val,'search'=>request('search')]) }}"
       class="btn btn-sm {{ request('status')==$val ? 'btn-primary' : 'btn-default' }}">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" style="margin-bottom:10px;">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by subject or client..." class="form-control" style="max-width:300px;display:inline-block;">
</form>

<div class="card">
    <table class="data-table">
        <thead><tr>
            <th>#</th><th>Subject</th><th>Client</th><th>Date</th><th>Valid Until</th><th style="text-align:right;">Total</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
            @forelse($quotes as $quote)
            @php
                $badgeClass = match($quote->status) { 'Accepted'=>'badge-active', 'Sent'=>'badge-open', 'Declined'=>'badge-cancelled', default=>'badge-draft' };
            @endphp
            <tr>
                <td style="font-family:monospace;font-size:12px;color:#777;">{{ $quote->id }}</td>
                <td><a href="{{ route('admin.quotes.show', $quote) }}" style="color:#337ab7;font-weight:600;">{{ $quote->subject }}</a></td>
                <td>{{ $quote->client->full_name ?? 'N/A' }}</td>
                <td style="font-size:12px;color:#777;">{{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</td>
                <td style="font-size:12px;color:#777;">{{ \Carbon\Carbon::parse($quote->valid_until)->format('d M Y') }}</td>
                <td style="text-align:right;font-weight:600;">${{ number_format($quote->total,2) }}</td>
                <td><span class="{{ $badgeClass }}">{{ $quote->status }}</span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-default btn-xs">View</a>
                        <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-default btn-xs">Edit</a>
                        <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" onsubmit="return confirm('Delete this quote?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">Del</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">No quotes found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $quotes->withQueryString()->links() }}</div>
</div>
@endsection
