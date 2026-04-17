@extends('admin.layouts.app')
@section('title', __('admin.quotes.title'))
@section('content')

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.quotes.title') }}</h1>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">{{ __('admin.quotes.new_quote') }}</a>
</div>
<div class="card">
    @if(($quotes ?? collect())->isEmpty())
    <div class="card-body" style="text-align:center;padding:40px;color:#999;">{{ __('admin.quotes.no_quotes') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('admin.quotes.quote_number') }}</th><th>{{ __('common.table.client') }}</th><th>{{ __('common.table.subject') }}</th><th>{{ __('common.table.total') }}</th><th>{{ __('admin.quotes.valid_until_col') }}</th><th>{{ __('admin.quotes.stage') }}</th><th style="text-align:right;">{{ __('common.table.actions') }}</th></tr></thead>
        <tbody>
        @foreach($quotes as $quote)
        <tr>
            <td style="font-family:monospace;font-weight:600;">{{ $quote->id }}</td>
            <td>{{ $quote->client?->full_name ?? __("admin.quotes.deleted_client") ?? ($quote->firstname . ' ' . $quote->lastname) }}</td>
            <td>{{ $quote->subject }}</td>
            <td style="font-weight:600;">${{ number_format($quote->total ?? 0, 2) }}</td>
            <td style="font-size:12px;">{{ $quote->validuntil?->format('d M Y') ?? '&mdash;' }}</td>
            <td><span class="badge-{{ strtolower($quote->stage ?? 'draft') }}">{{ $quote->stage ?? 'Draft' }}</span></td>
            <td style="text-align:right;">
                <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-default btn-xs">{{ __('common.actions.edit') }}</a>
                <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.quotes.confirm_delete') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
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
