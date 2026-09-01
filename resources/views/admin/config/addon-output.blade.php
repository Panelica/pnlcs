@extends("admin.layouts.app")
@section("title", $addon->getDisplayName())
@section("content")

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <h4 style="margin:0;">{{ $addon->getDisplayName() }}</h4>
        <p style="font-size:13px;color:var(--pn-muted);margin:4px 0 0;">{{ $addon->getDescription() }}</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:12px;color:var(--pn-muted);">v{{ $addon->getVersion() }} by {{ $addon->getAuthor() }}</span>
        <a href="{{ route('admin.config.addons.modules') }}" class="btn btn-sm btn-outline">{{ __('admin.nav.back') }}</a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:16px;">
        {!! $output !!}
    </div>
</div>

@if(!empty($config))
<div class="card" style="margin-top:16px;">
    <div class="card-header"><span style="font-weight:600;">{{ __('admin.addon_modules.settings') }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.addons.modules.settings', $name) }}">
            @csrf
            @foreach($config as $field)
                @php($key = $field['name'])
                @php($type = $field['type'] ?? 'text')
                <div class="mb-3">
                    <label class="form-label">{{ $field['label'] }}</label>
                    @if($type === 'checkbox')
                        <div class="form-check">
                            <input type="hidden" name="{{ $key }}" value="0">
                            <input type="checkbox" name="{{ $key }}" value="1" class="form-check-input"
                                {{ ($settings[$key] ?? $field['default'] ?? '0') == '1' ? 'checked' : '' }}>
                        </div>
                    @elseif($type === 'textarea')
                        <textarea name="{{ $key }}" class="form-control" rows="3">{{ $settings[$key] ?? $field['default'] ?? '' }}</textarea>
                    @elseif($type === 'password')
                        <input type="password" name="{{ $key }}" class="form-control" autocomplete="new-password"
                            placeholder="{{ ($settings[$key] ?? '') ? __('admin.addon_modules.key_is_set') : '' }}">
                    @elseif($type === 'select')
                        <select name="{{ $key }}" class="form-control">
                            @foreach($field['options'] ?? [] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" {{ ($settings[$key] ?? $field['default'] ?? '') == $optValue ? 'selected' : '' }}>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="{{ $key }}" class="form-control" value="{{ $settings[$key] ?? $field['default'] ?? '' }}">
                    @endif
                    @if(!empty($field['hint']))
                        <div style="font-size:12px;color:var(--pn-muted);margin-top:4px;">{{ $field['hint'] }}</div>
                    @endif
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary">{{ __('common.actions.save_changes') }}</button>
        </form>
    </div>
</div>
@endif

@endsection
