@extends("admin.layouts.app")
@section("title", __("admin.dashboard.title"))
@section("content")

<div style="display:flex;flex-direction:column;gap:16px;">
@foreach($widgetOutput as $key => $item)
    @php $widget = $item['widget']; $cols = $widget->getColumns(); @endphp
    @if($cols === 4)
        {{-- Full-width widget --}}
        <div class="card">
            <div class="card-body" style="padding:0;">
                {!! $item['html'] !!}
            </div>
        </div>
    @else
        @if($loop->first || ($widgetOutput[array_keys($widgetOutput)[$loop->index - 1] ?? '']['widget'] ?? null)?->getColumns() === 4 || $cols === 2 && !isset($gridOpen))
            @php $gridOpen = true; @endphp
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        @endif
        <div class="card" style="grid-column:span {{ $cols }};">
            <div class="card-header" style="padding:12px 16px;font-size:14px;font-weight:600;display:flex;align-items:center;justify-content:space-between;">
                {{ $widget->getTitle() }}
                <span style="font-size:11px;color:var(--pn-muted);font-weight:400;">{{ $widget->getDescription() }}</span>
            </div>
            <div style="min-height:100px;">
                {!! $item['html'] !!}
            </div>
        </div>
        @if($loop->last)
            </div>
            @php unset($gridOpen); @endphp
        @endif
    @endif
@endforeach
</div>

@endsection
