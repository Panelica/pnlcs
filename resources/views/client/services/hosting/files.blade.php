@extends("client.layouts.app")
@section("title", __('client.hosting.files.title'))
@section("content")

@php
    use Illuminate\Support\Str;
    $home = $listing['home'];
    $cur = $listing['path'];
    $rel = trim(Str::startsWith($cur, $home) ? substr($cur, strlen($home)) : '', '/');
    $segments = $rel === '' ? [] : explode('/', $rel);
    $parent = $cur !== $home ? dirname($cur) : null;
    if ($parent !== null && ! Str::startsWith($parent, $home)) { $parent = $home; }
    $editable = ['txt','text','md','markdown','html','htm','css','scss','js','mjs','ts','jsx','tsx','vue','json','xml','yml','yaml','ini','conf','cfg','env','log','sh','bash','sql','php','py','rb','go','java','c','cpp','h','htaccess','gitignore'];
    $fileRoute = fn($p) => route('client.services.files', ['service' => $service, 'path' => $p]);
    $iconFor = function($ext){
        $map = ['php'=>['ri-code-s-slash-line','#8b5cf6'],'html'=>['ri-html5-line','#ef4444'],'htm'=>['ri-html5-line','#ef4444'],
            'css'=>['ri-css3-line','#3b82f6'],'scss'=>['ri-css3-line','#ec4899'],'js'=>['ri-javascript-line','#f59e0b'],
            'json'=>['ri-braces-line','#f59e0b'],'md'=>['ri-markdown-line','#64748b'],'sql'=>['ri-database-2-line','#0ea5e9'],
            'zip'=>['ri-file-zip-line','#f59e0b'],'gz'=>['ri-file-zip-line','#f59e0b'],'tar'=>['ri-file-zip-line','#f59e0b'],
            'png'=>['ri-image-line','#10b981'],'jpg'=>['ri-image-line','#10b981'],'jpeg'=>['ri-image-line','#10b981'],'gif'=>['ri-image-line','#10b981'],'svg'=>['ri-image-line','#10b981'],'webp'=>['ri-image-line','#10b981'],
            'pdf'=>['ri-file-pdf-2-line','#ef4444'],'log'=>['ri-file-list-2-line','#64748b'],'txt'=>['ri-file-text-line','#64748b'],
            'env'=>['ri-key-2-line','#f59e0b'],'sh'=>['ri-terminal-box-line','#10b981']];
        return $map[$ext] ?? ['ri-file-line','#94a3b8'];
    };
@endphp

<style>
    .fm-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .fm-back:hover{color:var(--primary)}
    .fm-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .fm-head-ic{width:46px;height:46px;border-radius:13px;background:rgba(59,130,246,.13);color:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:24px}
    .fm-head h1{font-size:21px;font-weight:800;margin:0;letter-spacing:-.4px;color:var(--text)}
    .fm-head .sub{font-size:13px;color:var(--muted)}
    .fm-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:10px 14px;margin-bottom:16px;box-shadow:var(--shadow)}
    .fm-crumbs{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:13.5px}
    .fm-crumbs a{display:inline-flex;align-items:center;gap:5px;color:var(--muted);text-decoration:none;padding:4px 9px;border-radius:7px;font-weight:600;transition:all .12s}
    .fm-crumbs a:hover{background:var(--primary-light);color:var(--primary)}
    .fm-crumbs .sep{color:var(--border);font-weight:700}
    .fm-actions{display:flex;gap:8px}
    .fm-mini{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--text);transition:all .13s;list-style:none}
    .fm-mini:hover{border-color:var(--primary);color:var(--primary)}
    .fm-pop{position:absolute;right:0;z-index:20;margin-top:8px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;box-shadow:var(--shadow-md);min-width:250px}
    .fm-table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
    .fm-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg)}
    .fm-table tbody td{padding:11px 16px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);vertical-align:middle}
    .fm-table tbody tr:last-child td{border-bottom:none}
    .fm-table tbody tr:hover{background:var(--primary-light)}
    .fm-name{display:inline-flex;align-items:center;gap:11px;text-decoration:none;color:var(--text);font-weight:600}
    .fm-name:hover{color:var(--primary)}
    .fm-fic{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
    .fm-meta{font-size:12.5px;color:var(--muted)}
    .fm-oct{font-family:ui-monospace,Menlo,monospace;font-size:11px;background:var(--bg);border:1px solid var(--border);padding:2px 7px;border-radius:6px;color:var(--muted)}
    .fm-act{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;border:1px solid transparent;color:var(--muted);cursor:pointer;transition:all .12s;text-decoration:none;background:transparent}
    .fm-act:hover{background:var(--primary-light);color:var(--primary);border-color:var(--border)}
    .fm-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626}
    .fm-inp{width:100%;padding:8px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:13px;margin-bottom:9px}
    .fm-go{width:100%;padding:8px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer}
    .fm-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="fm-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="fm-head">
    <div class="fm-head-ic"><i class="ri-folder-open-line"></i></div>
    <div><h1>{{ __('client.hosting.files.title') }}</h1><div class="sub">{{ __('client.hosting.files.subtitle') }}</div></div>
</div>

@if(! $listing['ok'])
<div class="fm-table" style="padding:24px;text-align:center"><span class="fm-meta">{{ $listing['message'] ?: __('client.hosting.files.load_failed') }}</span></div>
@else

<div class="fm-bar">
    <div class="fm-crumbs">
        <a href="{{ $fileRoute($home) }}"><i class="ri-home-4-fill"></i></a>
        @php($acc = $home)
        @foreach($segments as $seg)
            @php($acc = $acc.'/'.$seg)
            <span class="sep">/</span><a href="{{ $fileRoute($acc) }}">{{ $seg }}</a>
        @endforeach
    </div>
    <div class="fm-actions">
        <details style="position:relative">
            <summary class="fm-mini"><i class="ri-folder-add-line"></i>{{ __('client.hosting.files.new_folder') }}</summary>
            <div class="fm-pop">
                <form method="POST" action="{{ route('client.services.files.create', $service) }}">
                    @csrf
                    <input type="hidden" name="path" value="{{ $cur }}"><input type="hidden" name="type" value="folder">
                    <label class="fm-lbl">{{ __('client.hosting.files.folder_name') }}</label>
                    <input type="text" name="name" required maxlength="255" class="fm-inp">
                    <button type="submit" class="fm-go">{{ __('client.hosting.files.create') }}</button>
                </form>
            </div>
        </details>
        <details style="position:relative">
            <summary class="fm-mini"><i class="ri-file-add-line"></i>{{ __('client.hosting.files.new_file') }}</summary>
            <div class="fm-pop">
                <form method="POST" action="{{ route('client.services.files.create', $service) }}">
                    @csrf
                    <input type="hidden" name="path" value="{{ $cur }}"><input type="hidden" name="type" value="file">
                    <label class="fm-lbl">{{ __('client.hosting.files.file_name') }}</label>
                    <input type="text" name="name" required maxlength="255" class="fm-inp" placeholder="index.html">
                    <button type="submit" class="fm-go">{{ __('client.hosting.files.create') }}</button>
                </form>
            </div>
        </details>
    </div>
</div>

<div style="overflow-x:auto">
<table class="fm-table">
    <thead><tr>
        <th>{{ __('client.hosting.files.name') }}</th><th style="width:120px">{{ __('client.hosting.files.size') }}</th>
        <th style="width:150px">{{ __('client.hosting.files.modified') }}</th><th style="width:80px">{{ __('client.hosting.files.permissions') }}</th>
        <th style="width:150px;text-align:right">{{ __('common.table.actions') }}</th>
    </tr></thead>
    <tbody>
        @if($parent !== null)
        <tr><td colspan="5"><a href="{{ $fileRoute($parent) }}" class="fm-name" style="color:var(--muted)"><span class="fm-fic" style="background:var(--bg)"><i class="ri-arrow-go-back-line"></i></span>..</a></td></tr>
        @endif
        @forelse($listing['entries'] as $e)
            @php($isFolder = ($e['type'] ?? '') === 'folder')
            @php($ext = strtolower($e['extension'] ?? pathinfo($e['name'] ?? '', PATHINFO_EXTENSION)))
            @php($canEdit = ! $isFolder && (in_array($ext, $editable, true) || (int)($e['size'] ?? 0) === 0))
            @php($ic = $isFolder ? ['ri-folder-fill','#f6c343'] : $iconFor($ext))
        <tr>
            <td>
                @if($isFolder)
                <a href="{{ $fileRoute($e['path']) }}" class="fm-name"><span class="fm-fic" style="background:rgba(246,195,67,.14);color:{{ $ic[1] }}"><i class="{{ $ic[0] }}"></i></span>{{ $e['name'] }}</a>
                @else
                <span class="fm-name" style="cursor:default"><span class="fm-fic" style="background:{{ $ic[1] }}1f;color:{{ $ic[1] }}"><i class="{{ $ic[0] }}"></i></span>{{ $e['name'] }}</span>
                @endif
            </td>
            <td class="fm-meta">{{ $isFolder ? '—' : ($e['size_formatted'] ?? $e['size'] ?? '') }}</td>
            <td class="fm-meta">{{ $e['modified_str'] ?? '' }}</td>
            <td><span class="fm-oct">{{ $e['permissions_octal'] ?? '' }}</span></td>
            <td style="text-align:right;white-space:nowrap">
                @if(! $isFolder)
                    <a href="{{ route('client.services.files.download', ['service' => $service, 'path' => $e['path']]) }}" class="fm-act" title="{{ __('client.hosting.files.download') }}"><i class="ri-download-2-line"></i></a>
                    @if($canEdit)<a href="{{ route('client.services.files.edit', ['service' => $service, 'path' => $e['path']]) }}" class="fm-act" title="{{ __('client.hosting.files.edit') }}"><i class="ri-edit-line"></i></a>@endif
                @endif
                <details style="display:inline-block;position:relative;text-align:left">
                    <summary class="fm-act" style="list-style:none" title="{{ __('client.hosting.files.rename') }}"><i class="ri-edit-box-line"></i></summary>
                    <div class="fm-pop">
                        <form method="POST" action="{{ route('client.services.files.rename', $service) }}">
                            @csrf<input type="hidden" name="path" value="{{ $e['path'] }}">
                            <label class="fm-lbl">{{ __('client.hosting.files.new_name') }}</label>
                            <input type="text" name="new_name" required maxlength="255" class="fm-inp" value="{{ $e['name'] }}">
                            <button type="submit" class="fm-go">{{ __('client.hosting.files.rename') }}</button>
                        </form>
                    </div>
                </details>
                <form method="POST" action="{{ route('client.services.files.delete', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.files.delete_confirm') }}')">
                    @csrf<input type="hidden" name="paths[]" value="{{ $e['path'] }}">
                    <button type="submit" class="fm-act danger" title="{{ __('client.hosting.files.delete') }}"><i class="ri-delete-bin-line"></i></button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;padding:28px"><span class="fm-meta">{{ __('client.hosting.files.empty') }}</span></td></tr>
        @endforelse
    </tbody>
</table>
</div>
@endif

@endsection
