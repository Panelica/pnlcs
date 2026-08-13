@extends("client.layouts.app")
@section("title", basename($path))
@section("content")

<style>
    .fe-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .fe-back:hover{color:var(--primary)}
    .fe-head{display:flex;align-items:center;gap:14px;margin-bottom:16px}
    .fe-ic{width:44px;height:44px;border-radius:12px;background:rgba(59,130,246,.13);color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:22px}
    .fe-head h1{font-size:19px;font-weight:800;margin:0;color:var(--text)}
    .fe-path{font-family:ui-monospace,Menlo,monospace;font-size:12px;background:var(--primary-light);color:var(--primary);padding:2px 9px;border-radius:6px}
    .fe-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden}
    .fe-ta{width:100%;min-height:62vh;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.6;padding:16px;border:none;background:var(--card);color:var(--text);resize:vertical;outline:none;display:block}
    .fe-foot{display:flex;gap:10px;padding:14px 16px;border-top:1px solid var(--border);background:var(--bg)}
    .fe-save{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer}
    .fe-save:hover{background:var(--primary-dark)}
    .fe-cancel{display:inline-flex;align-items:center;padding:10px 20px;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--text);font-weight:700;font-size:13.5px;text-decoration:none}
    .fe-cancel:hover{border-color:var(--primary);color:var(--primary)}
</style>

<a href="{{ route('client.services.files', ['service' => $service, 'path' => dirname($path)]) }}" class="fe-back"><i class="ri-arrow-left-line"></i>{{ __('client.hosting.files.title') }}</a>

<div class="fe-head">
    <div class="fe-ic"><i class="ri-edit-line"></i></div>
    <div><h1>{{ basename($path) }}</h1><div style="margin-top:3px"><span class="fe-path">{{ $path }}</span></div></div>
</div>

<div class="fe-card">
    <form method="POST" action="{{ route('client.services.files.save', $service) }}">
        @csrf
        <input type="hidden" name="path" value="{{ $path }}">
        <textarea name="content" class="fe-ta" spellcheck="false">{{ $content }}</textarea>
        <div class="fe-foot">
            <button type="submit" class="fe-save"><i class="ri-save-line"></i>{{ __('client.hosting.files.save') }}</button>
            <a href="{{ route('client.services.files', ['service' => $service, 'path' => dirname($path)]) }}" class="fe-cancel">{{ __('client.hosting.files.cancel') }}</a>
        </div>
    </form>
</div>

@endsection
