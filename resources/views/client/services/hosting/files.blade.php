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
@endphp

<a href="{{ route('client.services.show', $service) }}" class="pn-back">
    <i class="ri-arrow-left-line"></i>
    {{ $service->product?->name ?? __('client.services.title') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title"><i class="ri-folder-open-line" style="margin-right:8px;color:var(--primary,#3b82f6)"></i>{{ __('client.hosting.files.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.hosting.files.subtitle') }}</p>
    </div>
</div>

@if(! $listing['ok'])
<div class="pn-card mb-24"><div class="pn-card-body"><p class="text-muted" style="margin:0">{{ $listing['message'] ?: __('client.hosting.files.load_failed') }}</p></div></div>
@else

{{-- Breadcrumb + toolbar --}}
<div class="pn-card mb-24">
    <div class="pn-card-body" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="font-size:14px;display:flex;align-items:center;gap:4px;flex-wrap:wrap">
            <a href="{{ $fileRoute($home) }}" style="text-decoration:none"><i class="ri-home-4-line"></i></a>
            @php($acc = $home)
            @foreach($segments as $seg)
                @php($acc = $acc.'/'.$seg)
                <span class="text-muted">/</span>
                <a href="{{ $fileRoute($acc) }}" style="text-decoration:none">{{ $seg }}</a>
            @endforeach
        </div>
        <div style="display:flex;gap:8px">
            <details style="position:relative">
                <summary class="pn-btn pn-btn-sm" style="list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px"><i class="ri-folder-add-line"></i>{{ __('client.hosting.files.new_folder') }}</summary>
                <div style="position:absolute;right:0;z-index:10;margin-top:6px;background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:240px">
                    <form method="POST" action="{{ route('client.services.files.create', $service) }}">
                        @csrf
                        <input type="hidden" name="path" value="{{ $cur }}">
                        <input type="hidden" name="type" value="folder">
                        <label class="pn-label">{{ __('client.hosting.files.folder_name') }}</label>
                        <input type="text" name="name" required maxlength="255" class="pn-input" style="margin-bottom:8px">
                        <button type="submit" class="btn btn-primary pn-btn-sm" style="width:100%">{{ __('client.hosting.files.create') }}</button>
                    </form>
                </div>
            </details>
            <details style="position:relative">
                <summary class="pn-btn pn-btn-sm" style="list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px"><i class="ri-file-add-line"></i>{{ __('client.hosting.files.new_file') }}</summary>
                <div style="position:absolute;right:0;z-index:10;margin-top:6px;background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:240px">
                    <form method="POST" action="{{ route('client.services.files.create', $service) }}">
                        @csrf
                        <input type="hidden" name="path" value="{{ $cur }}">
                        <input type="hidden" name="type" value="file">
                        <label class="pn-label">{{ __('client.hosting.files.file_name') }}</label>
                        <input type="text" name="name" required maxlength="255" class="pn-input" style="margin-bottom:8px" placeholder="index.html">
                        <button type="submit" class="btn btn-primary pn-btn-sm" style="width:100%">{{ __('client.hosting.files.create') }}</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('client.hosting.files.name') }}</th>
                    <th>{{ __('client.hosting.files.size') }}</th>
                    <th>{{ __('client.hosting.files.modified') }}</th>
                    <th>{{ __('client.hosting.files.permissions') }}</th>
                    <th style="text-align:right">{{ __('common.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @if($parent !== null)
                <tr>
                    <td colspan="5"><a href="{{ $fileRoute($parent) }}" style="text-decoration:none;color:var(--text-muted,#6b7280)"><i class="ri-arrow-go-back-line" style="margin-right:8px"></i>..</a></td>
                </tr>
                @endif
                @forelse($listing['entries'] as $e)
                    @php($isFolder = ($e['type'] ?? '') === 'folder')
                    @php($ext = strtolower($e['extension'] ?? pathinfo($e['name'] ?? '', PATHINFO_EXTENSION)))
                    @php($canEdit = ! $isFolder && (in_array($ext, $editable, true) || (int)($e['size'] ?? 0) === 0))
                <tr>
                    <td>
                        @if($isFolder)
                        <a href="{{ $fileRoute($e['path']) }}" style="text-decoration:none;font-weight:500"><i class="ri-folder-fill" style="color:#f6c343;margin-right:8px"></i>{{ $e['name'] }}</a>
                        @else
                        <span><i class="ri-file-line" style="color:var(--text-muted,#6b7280);margin-right:8px"></i>{{ $e['name'] }}</span>
                        @endif
                    </td>
                    <td class="text-muted text-sm">{{ $isFolder ? '—' : ($e['size_formatted'] ?? $e['size'] ?? '') }}</td>
                    <td class="text-muted text-sm">{{ $e['modified_str'] ?? '' }}</td>
                    <td class="text-muted text-sm"><span class="pn-code" style="font-size:11px">{{ $e['permissions_octal'] ?? '' }}</span></td>
                    <td style="text-align:right;white-space:nowrap">
                        @if(! $isFolder)
                            <a href="{{ route('client.services.files.download', ['service' => $service, 'path' => $e['path']]) }}" class="pn-btn pn-btn-sm" title="{{ __('client.hosting.files.download') }}"><i class="ri-download-line"></i></a>
                            @if($canEdit)
                            <a href="{{ route('client.services.files.edit', ['service' => $service, 'path' => $e['path']]) }}" class="pn-btn pn-btn-sm" title="{{ __('client.hosting.files.edit') }}"><i class="ri-edit-line"></i></a>
                            @endif
                        @endif
                        <details style="display:inline-block;text-align:left;position:relative">
                            <summary class="pn-btn pn-btn-sm" style="list-style:none;cursor:pointer" title="{{ __('client.hosting.files.rename') }}"><i class="ri-edit-box-line"></i></summary>
                            <div style="position:absolute;right:0;z-index:10;margin-top:6px;background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:240px">
                                <form method="POST" action="{{ route('client.services.files.rename', $service) }}">
                                    @csrf
                                    <input type="hidden" name="path" value="{{ $e['path'] }}">
                                    <label class="pn-label">{{ __('client.hosting.files.new_name') }}</label>
                                    <input type="text" name="new_name" required maxlength="255" class="pn-input" value="{{ $e['name'] }}" style="margin-bottom:8px">
                                    <button type="submit" class="btn btn-primary pn-btn-sm" style="width:100%">{{ __('client.hosting.files.rename') }}</button>
                                </form>
                            </div>
                        </details>
                        <form method="POST" action="{{ route('client.services.files.delete', $service) }}" style="display:inline"
                              onsubmit="return confirm('{{ __('client.hosting.files.delete_confirm') }}')">
                            @csrf
                            <input type="hidden" name="paths[]" value="{{ $e['path'] }}">
                            <button type="submit" class="pn-btn pn-btn-sm pn-btn-danger" title="{{ __('client.hosting.files.delete') }}"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5"><div class="pn-card-body"><span class="text-muted">{{ __('client.hosting.files.empty') }}</span></div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection
