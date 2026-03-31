@extends('admin.layouts.app')
@section('title', 'Reports')
@section('content')
<div class="page-header"><h1>Reports</h1></div>

@php $categories = collect($reports)->groupBy('category'); @endphp
@foreach($categories as $cat => $items)
<div style="margin-bottom:20px;">
    <h3 style="font-size:12px;font-weight:700;color:#777;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:6px;">{{ $cat }}</h3>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
        @foreach($items as $report)
        <a href="{{ route('admin.reports.show', $report['slug']) }}" class="card" style="display:block;text-decoration:none;padding:12px 15px;transition:border-color 0.15s;">
            <div style="font-weight:600;font-size:13px;color:#333;margin-bottom:4px;">{{ $report['name'] }}</div>
            <div style="font-size:12px;color:#777;">{{ $report['description'] }}</div>
        </a>
        @endforeach
    </div>
</div>
@endforeach
@endsection
