@extends('admin.layouts.app')
@section('title', $title)
@section('content')
<div class="page-header">
    <h1>{{ $title }}</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-default btn-sm">&larr; Reports</a>
</div>
<div class="card">
    <table class="data-table">
        <thead><tr>@foreach($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($data as $row)
            <tr>
                @if(is_object($row) && isset($row->month))
                    <td>{{ $row->month }}</td>
                    @foreach(array_slice($row->toArray(), 1) as $val)<td>{{ is_numeric($val) ? '$' . number_format($val, 2) : $val }}</td>@endforeach
                @elseif(is_object($row) && isset($row->id))
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->client->full_name ?? 'N/A' }}</td>
                    <td>{{ $row->product->name ?? 'N/A' }}</td>
                    <td>{{ $row->domain ?? '-' }}</td>
                    <td>${{ number_format($row->amount ?? $row->revenue ?? 0, 2) }}</td>
                @else
                    @foreach((array) $row as $val)<td>{{ $val }}</td>@endforeach
                @endif
            </tr>
            @empty
            <tr><td colspan="{{ count($columns) }}" style="text-align:center;color:#999;padding:30px;">No data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
