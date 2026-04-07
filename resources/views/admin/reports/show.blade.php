@extends("admin.layouts.app")
@section("title", $title)
@section("content")

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h4 style="margin:0;">{{ $title }}</h4>
        <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ $description ?? '' }}</p>
    </div>
    <div style="display:flex;gap:8px;">
        @if($canExport && count($rows) > 0)
        <a href="{{ route('admin.reports.export', $report->getSlug()) }}?from={{ $from }}&to={{ $to }}&year={{ $year }}&month={{ $month }}" class="btn btn-sm btn-outline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            {{ __('admin.reports.export_csv') }}
        </a>
        @endif
        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline">{{ __("admin.nav.back") }}</a>
    </div>
</div>

@if($hasDateFilter)
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:12px 16px;">
        <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:13px;font-weight:500;">{{ __('admin.reports.from') }}</label>
            <input type="date" name="from" value="{{ $from }}" class="form-input" style="width:auto;">
            <label style="font-size:13px;font-weight:500;">{{ __('admin.reports.to') }}</label>
            <input type="date" name="to" value="{{ $to }}" class="form-input" style="width:auto;">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('admin.reports.generate') }}</button>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body" style="padding:0;overflow-x:auto;">
        @if(count($rows) > 0)
        <table class="table" style="width:100%;font-size:13px;">
            <thead>
                <tr>
                    @foreach($columns as $col)
                    <th style="padding:10px 14px;background:var(--pn-primary);color:#fff;font-size:12px;font-weight:600;white-space:nowrap;">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    @php $values = is_object($row) ? array_values((array)$row) : array_values($row); @endphp
                    @foreach($values as $val)
                    <td style="padding:8px 14px;border-bottom:1px solid var(--pn-border);white-space:nowrap;">
                        @if(is_numeric($val) && $val > 100 && !str_contains((string)$val, '-'))
                            {{ number_format((float)$val, 2) }}
                        @else
                            {{ $val }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            @if($totals)
            <tfoot>
                <tr style="font-weight:700;background:var(--pn-body-bg);">
                    @foreach($totals as $t)
                    <td style="padding:10px 14px;border-top:2px solid var(--pn-border);">
                        @if(is_numeric($t) && $t > 100){{ number_format((float)$t, 2) }}@else{{ $t }}@endif
                    </td>
                    @endforeach
                </tr>
            </tfoot>
            @endif
        </table>
        @else
        <div style="text-align:center;padding:48px;">
            <p style="color:var(--pn-muted);">{{ __("admin.no_results") }}</p>
        </div>
        @endif
    </div>
</div>

<div style="margin-top:12px;font-size:12px;color:var(--pn-muted);">
    Showing {{ count($rows) }} row(s) &middot; Generated {{ now()->format('Y-m-d H:i') }}
</div>

@endsection
