@extends("admin.layouts.app")

@section("title", "SSL Module Configuration")

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SSL Module Configuration</h1>
    <a href="{{ route('admin.config.servers') }}" class="btn btn-secondary">Back to Config</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@foreach($modules as $name => $module)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ ucfirst($name) }}</h5>
        <form method="POST" action="{{ route('admin.config.testSslConnection', $name) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">Test Connection</button>
        </form>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.config.updateSslModuleSettings', $name) }}">
            @csrf
            @foreach($module->getConfigFields() as $key => $field)
                <div class="mb-3">
                    <label class="form-label">{{ $field['label'] }}</label>
                    @if(($field['type'] ?? 'text') === 'checkbox')
                        <div class="form-check">
                            <input type="hidden" name="settings[{{ $key }}]" value="0">
                            <input type="checkbox" name="settings[{{ $key }}]" class="form-check-input" value="1"
                                {{ ($settings[$name][$key] ?? $field['default'] ?? '0') === '1' ? 'checked' : '' }}>
                            <label class="form-check-label">Enabled</label>
                        </div>
                    @elseif(($field['type'] ?? 'text') === 'password')
                        <input type="password" name="settings[{{ $key }}]" class="form-control"
                            value="{{ $settings[$name][$key] ?? '' }}"
                            {{ !empty($field['required']) ? 'required' : '' }}>
                    @else
                        <input type="text" name="settings[{{ $key }}]" class="form-control"
                            value="{{ $settings[$name][$key] ?? '' }}"
                            {{ !empty($field['required']) ? 'required' : '' }}>
                    @endif
                </div>
            @endforeach
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
@endforeach
@endsection
