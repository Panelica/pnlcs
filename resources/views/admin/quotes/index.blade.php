@extends('admin.layouts.app')
@section('title', __('admin.quotes.title'))
@section('content')
<div class="page-header">
    <h1>{{ __('admin.quotes.title') }}</h1>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">+ {{ __('admin.quotes.new_quote') }}</a>
</div>
<div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
    @foreach([''=>'All','Draft'=>'Draft','Sent'=>'Sent','Accepted'=>'Accepted','Declined'=>'Declined'] as $val=>$label)
    <a href="{{ route('admin.quotes.index', ['status'=>$val,'search'=>request('search')]) }}"
       class="btn btn-sm {{ request('status')==$val ? 'btn-primary' : 'btn-default' }}">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" style="margin-bottom:10px;">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.quotes.search_placeholder') }}" class="form-control" style="max-width:300px;display:inline-block;">
</form>

<div class="card">
    <table class="data-table">
        <thead><tr>
            <th>#</th><th>{{ __('common.table.subject') }}</th><th>{{ __('common.table.client') }}</th><th>{{ __('common.table.date') }}</th><th>{{ __('admin.quotes.valid_until') }}</th><th style="text-align:right;">{{ __('common.table.total') }}</th><th>{{ __('common.table.status') }}</th><th>{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
            @forelse($quotes as $quote)
            @php
                $badgeClass = match($quote->status) { 'Accepted'=>'badge-active', 'Sent'=>'badge-open', 'Declined'=>'badge-cancelled', default=>'badge-draft' };
            @endphp
            <tr>
                <td style="font-family:monospace;font-size:12px;color:#777;">{{ $quote->id }}</td>
                <td><a href="{{ route('admin.quotes.show', $quote) }}" style="color:#337ab7;font-weight:600;">{{ $quote->subject }}</a></td>
                <td>{{ $quote->client?->full_name ?? 'N/A' }}</td>
                <td style="font-size:12px;color:#777;">{{ \Carbon\Carbon::parse($quote->date)->format(date_fmt()) }}</td>
                <td style="font-size:12px;color:#777;">{{ \Carbon\Carbon::parse($quote->valid_until)->format(date_fmt()) }}</td>
                <td style="text-align:right;font-weight:600;">{{ money_fmt($quote->total) }}</td>
                <td><span class="{{ $badgeClass }}">{{ $quote->status }}</span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-default btn-xs">{{ __('common.actions.view') }}</a>
                        <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</a>
                        <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" onsubmit="return confirm('{{ __('admin.quotes.confirm_delete') }}')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-xs">{{ __('admin.quotes.del') }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">{{ __('admin.quotes.no_quotes') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="padding:10px 15px;">{{ $quotes->withQueryString()->links() }}</div>
</div>
@endsection
