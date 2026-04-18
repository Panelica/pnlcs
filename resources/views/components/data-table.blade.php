@props([
    'headers' => [],
    'empty' => __('common.table.no_records'),
    'title' => null,
    'createUrl' => null,
    'createLabel' => __('common.actions.add_new'),
    'paginator' => null,
])

<div class="card">
    @if($title || $createUrl)
    <div class="card-header">
        @if($title)<span>{{ $title }}</span>@endif
        @if($createUrl)<a href="{{ $createUrl }}" class="btn btn-primary btn-sm">+ {{ $createLabel }}</a>@endif
    </div>
    @endif
    <div class="card-body" style="padding:0; overflow-x:auto;">
        <table class="data-table">
            @if(count($headers))
            <thead>
                <tr>
                    @foreach($headers as $header)
                    <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @if($paginator && $paginator->hasPages())
    <div style="padding:12px 16px; border-top:1px solid #eee;">
        {{ $paginator->links() }}
    </div>
    @endif
</div>
