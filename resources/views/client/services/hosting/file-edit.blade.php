@extends("client.layouts.app")
@section("title", basename($path))
@section("content")

<a href="{{ route('client.services.files', ['service' => $service, 'path' => dirname($path)]) }}" class="pn-back">
    <i class="ri-arrow-left-line"></i>
    {{ __('client.hosting.files.title') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title"><i class="ri-edit-line" style="margin-right:8px;color:var(--primary,#3b82f6)"></i>{{ basename($path) }}</h1>
        <p class="pn-page-subtitle"><span class="pn-code" style="font-size:12px">{{ $path }}</span></p>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.services.files.save', $service) }}">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <textarea name="content" spellcheck="false"
                style="width:100%;min-height:60vh;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;padding:12px;border:1px solid var(--border,#e5e7eb);border-radius:8px;background:var(--card-bg,#fff);color:inherit;resize:vertical">{{ $content }}</textarea>
            <div style="display:flex;gap:8px;margin-top:12px">
                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> {{ __('client.hosting.files.save') }}</button>
                <a href="{{ route('client.services.files', ['service' => $service, 'path' => dirname($path)]) }}" class="btn btn-secondary">{{ __('client.hosting.files.cancel') }}</a>
            </div>
        </form>
    </div>
</div>

@endsection
