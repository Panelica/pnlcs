@php
    $menuItems = $adminMenuItems[$location] ?? [];
    $sidebar = $sidebar ?? false;
    $childIndent = $childIndent ?? 28;
@endphp
@foreach($menuItems as $item)
<li><a href="{{ $item['url'] }}"@if($sidebar && request()->url() === $item['url']) class="active" @endif>{{ $item['label'] }}</a></li>
@foreach($item['children'] as $child)
@continue($child['url'] === $item['url'])
<li><a href="{{ $child['url'] }}" style="padding-left:{{ $childIndent }}px;"@if($sidebar && request()->url() === $child['url']) class="active" @endif>{{ $child['label'] }}</a></li>
@endforeach
@endforeach
