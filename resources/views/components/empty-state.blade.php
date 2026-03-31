@props([
    'title' => 'No data found',
    'description' => '''',
    'actionUrl' => null,
    'actionLabel' => 'Get Started',
])

<div style="text-align:center; padding:48px 24px; color:#999;">
    <div style="font-size:40px; margin-bottom:14px; opacity:0.4;">&#128193;</div>
    <div style="font-size:14px; font-weight:600; color:#555; margin-bottom:6px;">{{ $title }}</div>
    @if($description)
    <div style="font-size:13px; color:#999; margin-bottom:16px; max-width:320px; margin-left:auto; margin-right:auto; line-height:1.6;">{{ $description }}</div>
    @endif
    @if($actionUrl)
    <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm">{{ $actionLabel }}</a>
    @endif
</div>
