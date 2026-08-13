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
    $count = $listing['ok'] ? count($listing['entries']) : 0;
    $iconFor = function($ext){
        $map = ['php'=>['ri-code-s-slash-line','#8b5cf6'],'html'=>['ri-html5-line','#e34f26'],'htm'=>['ri-html5-line','#e34f26'],
            'css'=>['ri-css3-line','#2965f1'],'scss'=>['ri-css3-line','#cf649a'],'js'=>['ri-javascript-line','#f0db4f'],'mjs'=>['ri-javascript-line','#f0db4f'],
            'json'=>['ri-braces-line','#f59e0b'],'md'=>['ri-markdown-line','#64748b'],'sql'=>['ri-database-2-line','#0ea5e9'],
            'zip'=>['ri-file-zip-line','#f59e0b'],'gz'=>['ri-file-zip-line','#f59e0b'],'tar'=>['ri-file-zip-line','#f59e0b'],'rar'=>['ri-file-zip-line','#f59e0b'],
            'png'=>['ri-image-line','#10b981'],'jpg'=>['ri-image-line','#10b981'],'jpeg'=>['ri-image-line','#10b981'],'gif'=>['ri-image-line','#10b981'],'svg'=>['ri-image-line','#10b981'],'webp'=>['ri-image-line','#10b981'],'ico'=>['ri-image-line','#10b981'],
            'pdf'=>['ri-file-pdf-2-line','#ef4444'],'log'=>['ri-file-list-2-line','#64748b'],'txt'=>['ri-file-text-line','#64748b'],
            'env'=>['ri-key-2-line','#f59e0b'],'sh'=>['ri-terminal-box-line','#10b981'],'py'=>['ri-code-line','#3776ab']];
        return $map[$ext] ?? ['ri-file-line','#94a3b8'];
    };
@endphp

<style>
    .fm-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .fm-back:hover{color:var(--primary)}
    .fm-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .fm-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;display:flex;align-items:center;justify-content:center;font-size:25px;box-shadow:0 8px 18px -6px rgba(59,130,246,.6)}
    .fm-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .fm-head .sub{font-size:13px;color:var(--muted)}
    .fm-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .fm-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:13px;padding:11px 15px;margin-bottom:16px;box-shadow:var(--shadow)}
    .fm-crumbs{display:flex;align-items:center;gap:3px;flex-wrap:wrap;font-size:13.5px}
    .fm-crumbs a{display:inline-flex;align-items:center;gap:5px;color:var(--muted);text-decoration:none;padding:5px 10px;border-radius:8px;font-weight:600;transition:all .12s}
    .fm-crumbs a:hover{background:var(--primary-light);color:var(--primary)}
    .fm-crumbs .sep{color:var(--border);font-weight:700}
    .fm-actions{display:flex;gap:8px;flex-wrap:wrap}
    .fm-up{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:var(--primary);color:#fff;transition:all .13s}
    .fm-up:hover{background:var(--primary-dark);transform:translateY(-1px)}
    .fm-mini{display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--text);transition:all .13s;list-style:none}
    .fm-mini:hover{border-color:var(--primary);color:var(--primary)}
    .fm-pop{position:absolute;right:0;z-index:30;margin-top:8px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:14px;box-shadow:var(--shadow-md);min-width:250px}
    .fm-shell{position:relative}
    .fm-table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:var(--shadow)}
    .fm-table thead th{text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 16px;border-bottom:1px solid var(--border);background:var(--bg)}
    .fm-table tbody td{padding:11px 16px;border-bottom:1px solid var(--border);font-size:13.5px;color:var(--text);vertical-align:middle}
    .fm-table tbody tr:last-child td{border-bottom:none}
    .fm-table tbody tr:hover{background:var(--primary-light)}
    .fm-name{display:inline-flex;align-items:center;gap:11px;text-decoration:none;color:var(--text);font-weight:600}
    .fm-name:hover{color:var(--primary)}
    .fm-fic{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
    .fm-meta{font-size:12.5px;color:var(--muted)}
    .fm-oct{font-family:ui-monospace,Menlo,monospace;font-size:11px;background:var(--bg);border:1px solid var(--border);padding:2px 7px;border-radius:6px;color:var(--muted)}
    .fm-act{display:inline-flex;align-items:center;justify-content:center;width:31px;height:31px;border-radius:8px;border:1px solid transparent;color:var(--muted);cursor:pointer;transition:all .12s;text-decoration:none;background:transparent}
    .fm-act:hover{background:var(--primary-light);color:var(--primary);border-color:var(--border)}
    .fm-act.danger:hover{background:rgba(239,68,68,.1);color:#dc2626}
    .fm-inp{width:100%;padding:8px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:13px;margin-bottom:9px}
    .fm-go{width:100%;padding:8px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer}
    .fm-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .fm-drop{position:absolute;inset:0;z-index:20;border:2.5px dashed var(--primary);border-radius:14px;background:color-mix(in srgb,var(--primary) 8%,var(--card));display:none;align-items:center;justify-content:center;flex-direction:column;gap:10px;color:var(--primary);font-weight:700;font-size:16px;backdrop-filter:blur(2px)}
    .fm-drop.on{display:flex}
    .fm-drop i{font-size:44px}
    #fm-progress{display:none;align-items:center;gap:10px;background:var(--card);border:1px solid var(--border);border-radius:11px;padding:12px 16px;margin-bottom:16px;box-shadow:var(--shadow);font-size:13px;font-weight:600;color:var(--text)}
    #fm-progress.on{display:flex}
    .fm-spin{width:16px;height:16px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:fmspin .7s linear infinite}
    @keyframes fmspin{to{transform:rotate(360deg)}}
</style>

<a href="{{ route('client.services.show', $service) }}" class="fm-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="fm-head">
    <div class="fm-head-ic"><i class="ri-folder-open-line"></i></div>
    <div><h1>{{ __('client.hosting.files.title') }}</h1><div class="sub">{{ __('client.hosting.files.subtitle') }}</div></div>
    <span class="fm-cnt">{{ $count }} {{ __('client.hosting.files.items') }}</span>
</div>

@if(! $listing['ok'])
<div class="fm-table" style="padding:24px;text-align:center"><span class="fm-meta">{{ $listing['message'] ?: __('client.hosting.files.load_failed') }}</span></div>
@else

<div class="fm-bar">
    <div class="fm-crumbs">
        <a href="{{ $fileRoute($home) }}"><i class="ri-home-4-fill"></i></a>
        @php($acc = $home)
        @foreach($segments as $seg)@php($acc = $acc.'/'.$seg)<span class="sep">/</span><a href="{{ $fileRoute($acc) }}">{{ $seg }}</a>@endforeach
    </div>
    <div class="fm-actions">
        <button type="button" class="fm-up" onclick="document.getElementById('fm-file').click()"><i class="ri-upload-2-line"></i>{{ __('client.hosting.files.upload') }}</button>
        <input type="file" id="fm-file" multiple style="display:none">
        <details style="position:relative">
            <summary class="fm-mini"><i class="ri-folder-add-line"></i>{{ __('client.hosting.files.new_folder') }}</summary>
            <div class="fm-pop"><form method="POST" action="{{ route('client.services.files.create', $service) }}">@csrf
                <input type="hidden" name="path" value="{{ $cur }}"><input type="hidden" name="type" value="folder">
                <label class="fm-lbl">{{ __('client.hosting.files.folder_name') }}</label>
                <input type="text" name="name" required maxlength="255" class="fm-inp"><button type="submit" class="fm-go">{{ __('client.hosting.files.create') }}</button>
            </form></div>
        </details>
        <details style="position:relative">
            <summary class="fm-mini"><i class="ri-file-add-line"></i>{{ __('client.hosting.files.new_file') }}</summary>
            <div class="fm-pop"><form method="POST" action="{{ route('client.services.files.create', $service) }}">@csrf
                <input type="hidden" name="path" value="{{ $cur }}"><input type="hidden" name="type" value="file">
                <label class="fm-lbl">{{ __('client.hosting.files.file_name') }}</label>
                <input type="text" name="name" required maxlength="255" class="fm-inp" placeholder="index.html"><button type="submit" class="fm-go">{{ __('client.hosting.files.create') }}</button>
            </form></div>
        </details>
    </div>
</div>

<div id="fm-progress"><span class="fm-spin"></span><span id="fm-progress-txt">{{ __('client.hosting.files.uploading') }}</span></div>

<div class="fm-shell" id="fm-shell">
    <div class="fm-drop" id="fm-drop"><i class="ri-upload-cloud-2-line"></i>{{ __('client.hosting.files.drop_here') }}</div>
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
                <td>@if($isFolder)<a href="{{ $fileRoute($e['path']) }}" class="fm-name"><span class="fm-fic" style="background:rgba(246,195,67,.15);color:{{ $ic[1] }}"><i class="{{ $ic[0] }}"></i></span>{{ $e['name'] }}</a>@else<span class="fm-name" style="cursor:default"><span class="fm-fic" style="background:{{ $ic[1] }}22;color:{{ $ic[1] }}"><i class="{{ $ic[0] }}"></i></span>{{ $e['name'] }}</span>@endif</td>
                <td class="fm-meta">{{ $isFolder ? '—' : ($e['size_formatted'] ?? $e['size'] ?? '') }}</td>
                <td class="fm-meta">{{ $e['modified_str'] ?? '' }}</td>
                <td><span class="fm-oct">{{ $e['permissions_octal'] ?? '' }}</span></td>
                <td style="text-align:right;white-space:nowrap">
                    @if(! $isFolder)<a href="{{ route('client.services.files.download', ['service' => $service, 'path' => $e['path']]) }}" class="fm-act" title="{{ __('client.hosting.files.download') }}"><i class="ri-download-2-line"></i></a>@if($canEdit)<a href="{{ route('client.services.files.edit', ['service' => $service, 'path' => $e['path']]) }}" class="fm-act" title="{{ __('client.hosting.files.edit') }}"><i class="ri-edit-line"></i></a>@endif @endif
                    <details style="display:inline-block;position:relative;text-align:left">
                        <summary class="fm-act" style="list-style:none" title="{{ __('client.hosting.files.rename') }}"><i class="ri-edit-box-line"></i></summary>
                        <div class="fm-pop"><form method="POST" action="{{ route('client.services.files.rename', $service) }}">@csrf<input type="hidden" name="path" value="{{ $e['path'] }}">
                            <label class="fm-lbl">{{ __('client.hosting.files.new_name') }}</label>
                            <input type="text" name="new_name" required maxlength="255" class="fm-inp" value="{{ $e['name'] }}"><button type="submit" class="fm-go">{{ __('client.hosting.files.rename') }}</button>
                        </form></div>
                    </details>
                    <form method="POST" action="{{ route('client.services.files.delete', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.files.delete_confirm') }}')">@csrf<input type="hidden" name="paths[]" value="{{ $e['path'] }}">
                        <button type="submit" class="fm-act danger" title="{{ __('client.hosting.files.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;padding:34px"><i class="ri-inbox-line" style="font-size:32px;color:var(--border);display:block;margin-bottom:8px"></i><span class="fm-meta">{{ __('client.hosting.files.empty') }}</span></td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endif

<script>
(function(){
    var input=document.getElementById('fm-file'), shell=document.getElementById('fm-shell'), drop=document.getElementById('fm-drop');
    if(!input)return;
    var url="{{ route('client.services.files.upload', $service) }}", cur=@json($cur);
    var csrf=(document.querySelector('meta[name=csrf-token]')||{}).content||'';
    var prog=document.getElementById('fm-progress'), ptxt=document.getElementById('fm-progress-txt');
    async function upload(files){
        if(!files||!files.length)return;
        prog.classList.add('on');
        var done=0;
        for(var i=0;i<files.length;i++){
            ptxt.textContent="{{ __('client.hosting.files.uploading') }} ("+(i+1)+"/"+files.length+") "+files[i].name;
            var fd=new FormData(); fd.append('_token',csrf); fd.append('path',cur); fd.append('file',files[i]);
            try{ await fetch(url,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'}); done++; }catch(e){}
        }
        ptxt.textContent="{{ __('client.hosting.files.upload_done') }}"; location.reload();
    }
    input.addEventListener('change',function(){upload(input.files);});
    if(shell){
        ['dragenter','dragover'].forEach(function(ev){shell.addEventListener(ev,function(e){e.preventDefault();drop.classList.add('on');});});
        ['dragleave','drop'].forEach(function(ev){shell.addEventListener(ev,function(e){e.preventDefault();if(ev==='drop'||e.target===drop)drop.classList.remove('on');});});
        shell.addEventListener('drop',function(e){e.preventDefault();drop.classList.remove('on');if(e.dataTransfer&&e.dataTransfer.files)upload(e.dataTransfer.files);});
    }
})();
</script>

@endsection
