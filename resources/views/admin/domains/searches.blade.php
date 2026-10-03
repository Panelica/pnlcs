@extends("admin.layouts.app")
@section("title", __('admin.domain_searches.title'))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.domain_searches.title') }}</h1>
    <div style="display:flex;gap:6px;">
        @foreach([7, 30, 90] as $d)
        <a href="{{ route('admin.domains.searches', ['days' => $d]) }}" class="btn btn-{{ $d === $days ? 'primary' : 'default' }} btn-sm">{{ __('admin.domain_searches.last_days', ['days' => $d]) }}</a>
        @endforeach
    </div>
</div>

@unless($enabled)
<div class="alert alert-warning" style="margin-bottom:15px;">{{ __('admin.domain_searches.disabled') }}</div>
@endunless

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:15px;">
    @foreach([['searches', $total], ['distinct', $distinct], ['free', $free], ['taken', $taken], ['unanswered', $unanswered]] as [$key, $n])
    <div class="card"><div class="card-body" style="padding:12px 16px;"><div style="font-size:12px;color:#777;">{{ __('admin.domain_searches.'.$key) }}</div><div style="font-size:22px;font-weight:700;">{{ number_format($n) }}</div></div></div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:15px;margin-bottom:15px;">
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.domain_searches.top_extensions') }}</strong></div>
        <div class="card-body">
            @forelse($extensions as $ext => $n)
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;border-bottom:1px solid #f0f0f0;"><span style="font-family:monospace;">{{ $ext }}</span><span>{{ number_format($n) }}</span></div>
            @empty
            <p style="margin:0;color:#777;">{{ __('admin.domain_searches.none') }}</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-header"><strong>{{ __('admin.domain_searches.missed') }}</strong></div>
        <div class="card-body">
            <p style="font-size:12px;color:#777;margin-top:0;">{{ __('admin.domain_searches.missed_hint') }}</p>
            @forelse($missed as $name => $n)
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;border-bottom:1px solid #f0f0f0;"><span style="font-family:monospace;">{{ $name }}</span><span>{{ trans_choice('admin.domain_searches.times', $n, ['count' => $n]) }}</span></div>
            @empty
            <p style="margin:0;color:#777;">{{ __('admin.domain_searches.none') }}</p>
            @endforelse
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.domain_searches.recent') }}</strong></div>
    @if($recent->isEmpty())
    <div class="card-body" style="color:#777;">{{ __('admin.domain_searches.none') }}</div>
    @else
    <table class="data-table">
        <thead><tr><th>{{ __('common.table.date') }}</th><th>{{ __('admin.domain_searches.domain') }}</th><th>{{ __('admin.domain_searches.result') }}</th><th>{{ __('admin.domain_searches.client') }}</th></tr></thead>
        <tbody>
        @foreach($recent as $r)
        <tr>
            <td style="font-size:12px;white-space:nowrap;">{{ $r->created_at->timezone(display_tz())->format(datetime_fmt()) }}</td>
            <td style="font-family:monospace;">{{ $r->domain }}</td>
            <td>{{ $r->available === null ? __('admin.domain_searches.unanswered') : ($r->available ? __('admin.domain_searches.free') : __('admin.domain_searches.taken')) }}</td>
            <td>@if($r->client_id)<a href="{{ route('admin.clients.show', $r->client_id) }}">#{{ $r->client_id }}</a>@else — @endif</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
