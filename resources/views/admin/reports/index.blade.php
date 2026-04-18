@extends("admin.layouts.app")
@section("title", __("admin.reports.title"))
@section("content")

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
    <h4 style="margin:0;">{{ __("admin.reports.title") }}</h4>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm {{ !$selectedCategory ? 'btn-primary' : 'btn-outline' }}">{{ __('admin.reports.all') }}</a>
        @foreach($categories as $cat)
        <a href="{{ route('admin.reports.index', ['category' => $cat]) }}" class="btn btn-sm {{ $selectedCategory === $cat ? 'btn-primary' : 'btn-outline' }}">{{ $cat }}</a>
        @endforeach
    </div>
</div>

@foreach($reports as $category => $categoryReports)
<div style="margin-bottom:32px;">
    <h5 style="font-size:16px;font-weight:600;color:var(--pn-heading);margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid var(--pn-primary);">{{ $category }}</h5>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px;">
        @foreach($categoryReports as $report)
        <a href="{{ route('admin.reports.show', $report->getSlug()) }}" class="card" style="text-decoration:none;color:inherit;transition:box-shadow 0.2s;">
            <div class="card-body" style="padding:16px;">
                <div style="font-weight:600;font-size:14px;margin-bottom:4px;">{{ $report->getTitle() }}</div>
                <div style="font-size:12px;color:var(--pn-muted);">{{ $report->getDescription() }}</div>
                <div style="margin-top:8px;display:flex;gap:6px;">
                    @if($report->hasDateFilter())<span style="font-size:10px;background:var(--pn-badge-bg);padding:2px 6px;border-radius:4px;">{{ __('admin.reports.date_filter') }}</span>@endif
                    @if($report->canExport())<span style="font-size:10px;background:var(--pn-badge-bg);padding:2px 6px;border-radius:4px;">{{ __('admin.reports.csv_export') }}</span>@endif
                </div>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endforeach

@if($reports->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;padding:48px;">
    <p style="color:var(--pn-muted);">{{ __("admin.no_results") }}</p>
</div></div>
@endif

@endsection
