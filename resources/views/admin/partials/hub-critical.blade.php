{{-- Critical notices from the PNLCS team (App\Services\Hub\NoticeBoard): on every admin page until this install is no longer affected. Signed; plain text. --}}
@foreach(app(\App\Services\Hub\NoticeBoard::class)->current('critical') as $hubNotice)
<div class="alert alert-danger" role="alert" style="border-left:4px solid #a94442;">
    <strong><i class="fas fa-shield-alt"></i> {{ $hubNotice['title'] }}</strong>
    @if($hubNotice['body'] !== '')<div style="margin-top:4px;white-space:pre-line;">{{ $hubNotice['body'] }}</div>@endif
    @if($hubNotice['url'] !== '')<div style="margin-top:6px;"><a href="{{ $hubNotice['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('admin.hub.read_more') }} &rarr;</a></div>@endif
</div>
@endforeach
