@extends("client.layouts.app")
@section("title", __("client.downloads.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">Downloads</h1>
        <p class="pn-page-subtitle">Software, tools, and resources available to you.</p>
    </div>
</div>

@if($categories->isEmpty())
<div class="pn-card">
    <div class="pn-empty">
        <div class="pn-empty-icon">&#128229;</div>
        <p>No downloads available at this time.</p>
    </div>
</div>
@else
<div style="display:flex;flex-direction:column;gap:20px">
    @foreach($categories as $category)
    @if($category->downloads->isNotEmpty())
    <div class="pn-card">
        <div class="pn-card-header">
            <span class="pn-card-title">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:6px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                {{ $category->name }}
            </span>
        </div>
        <div class="pn-card-body-flush">
            <table class="pn-table">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>{{ __('common.table.description') }}</th>
                        <th>Downloads</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($category->downloads as $download)
                    <tr>
                        <td style="font-weight:600">{{ $download->title }}</td>
                        <td class="text-muted text-sm">{{ $download->description ?? "-" }}</td>
                        <td class="text-muted text-sm">{{ number_format($download->download_count ?? 0) }}</td>
                        <td>
                            @if($download->location)
                            <a href="{{ route("client.downloads.download", $download) }}" class="btn btn-primary btn-xs">
                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>{{ __('common.actions.download') }}</a>
                            @else
                            <span class="text-muted text-sm">N/A</span>
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
