@extends('client.layouts.app')
@section('title', 'Downloads')
@section('content')

<div class="page-header">
    <h1>Downloads</h1>
</div>

@if($categories->isEmpty())
<div class="card">
    <div class="card-body" style="text-align:center; padding:48px; color:#999;">No downloads available at this time.</div>
</div>
@else
<div style="display:flex; flex-direction:column; gap:16px;">
    @foreach($categories as $category)
    @if($category->downloads->isNotEmpty())
    <div class="card">
        <div class="card-header">{{ $category->name }}</div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Description</th>
                        <th>Downloads</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($category->downloads as $download)
                    <tr>
                        <td style="font-weight:500;">{{ $download->title }}</td>
                        <td style="color:#777;">{{ $download->description ?? '-' }}</td>
                        <td style="color:#999; font-size:12px;">{{ $download->download_count ?? 0 }}</td>
                        <td>
                            @if($download->location)
                            <a href="{{ route('client.downloads.download', $download) }}" class="btn btn-primary btn-xs">Download</a>
                            @else
                            <span style="color:#999; font-size:12px;">N/A</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endforeach
</div>
@endif

@endsection
