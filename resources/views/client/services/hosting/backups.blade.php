@extends("client.layouts.app")
@section("title", __('client.hosting.backups.title'))
@section("content")

<style>
    .bk-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;margin-bottom:14px}
    .bk-back:hover{color:var(--primary)}
    .bk-head{display:flex;align-items:center;gap:14px;margin-bottom:18px}
    .bk-head-ic{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#64748b,#475569);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 8px 18px -6px rgba(100,116,139,.6)}
    .bk-head h1{font-size:22px;font-weight:800;margin:0;letter-spacing:-.5px;color:var(--text)}
    .bk-head .sub{font-size:13px;color:var(--muted)}
    .bk-cnt{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--bg);border:1px solid var(--border);padding:6px 13px;border-radius:999px}
    .bk-card{background:var(--card);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);margin-bottom:18px}
    .bk-ch{padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:8px}
    .bk-form{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:18px}
    .bk-fld{flex:1;min-width:150px}
    .bk-lbl{display:block;font-size:12px;font-weight:600;color:var(--muted);margin-bottom:5px}
    .bk-inp,.bk-sel{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:9px;background:var(--bg);color:var(--text);font-size:13.5px;box-sizing:border-box}
    .bk-btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:none;border-radius:9px;background:var(--primary);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;white-space:nowrap}
    .bk-btn:hover{background:var(--primary-dark)}
    .bk-note{padding:16px 18px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--muted);line-height:1.5}
    .bk-note i{font-size:18px;color:#f59e0b;flex-shrink:0}
    .bk-note.info i{color:var(--primary)}
    .bk-table{width:100%;border-collapse:collapse}
    .bk-table thead th{text-align:left;font-size:11.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--bg)}
    .bk-table tbody td{padding:12px 18px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)}
    .bk-table tbody tr:last-child td{border-bottom:none}
    .bk-table tbody tr:hover{background:var(--primary-light)}
    .bk-when{font-weight:700}
    .bk-file{font-size:11.5px;color:var(--muted);font-family:ui-monospace,Menlo,monospace}
    .bk-doms{font-size:11.5px;color:var(--muted)}
    .bk-tag{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:var(--bg);border:1px solid var(--border);color:var(--muted)}
    .bk-enc{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:rgba(16,185,129,.12);color:#059669}
    .bk-act{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border);color:var(--muted);cursor:pointer;background:transparent}
    .bk-act:hover{background:rgba(239,68,68,.1);color:#dc2626;border-color:transparent}
    .bk-empty{padding:26px;text-align:center;color:var(--muted);font-size:13.5px}
</style>

<a href="{{ route('client.services.show', $service) }}" class="bk-back"><i class="ri-arrow-left-line"></i>{{ $service->product?->name ?? __('client.services.title') }}</a>

<div class="bk-head">
    <div class="bk-head-ic"><i class="ri-archive-2-line"></i></div>
    <div><h1>{{ __('client.hosting.backups.title') }}</h1><div class="sub">{{ __('client.hosting.backups.subtitle') }}</div></div>
    <span class="bk-cnt">{{ $policy['count'] }} {{ __('client.hosting.backups.points') }}</span>
</div>

@if(! $policy['enabled'])
<div class="bk-card"><div class="bk-note"><i class="ri-lock-line"></i>{{ __('client.hosting.backups.plan_disabled') }}</div></div>
@elseif(empty($domains))
<div class="bk-card"><div class="bk-empty">{{ __('client.hosting.backups.no_domains') }}</div></div>
@else
<div class="bk-card">
    <div class="bk-ch"><i class="ri-add-circle-line"></i>{{ __('client.hosting.backups.create_title') }}</div>
    <form method="POST" action="{{ route('client.services.backups.store', $service) }}" class="bk-form">
        @csrf
        <div class="bk-fld" style="max-width:260px"><label class="bk-lbl">{{ __('client.hosting.backups.scope') }}</label>
            <select name="domain_id" class="bk-sel">
                <option value="">{{ __('client.hosting.backups.all_domains') }}</option>
                @foreach($domains as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="bk-fld" style="max-width:240px"><label class="bk-lbl">{{ __('client.hosting.backups.name') }}</label>
            <input type="text" name="name" maxlength="100" class="bk-inp" placeholder="{{ __('client.hosting.backups.name_ph') }}">
        </div>
        <button type="submit" class="bk-btn"><i class="ri-save-3-line"></i>{{ __('client.hosting.backups.create') }}</button>
    </form>
    <div class="bk-note info"><i class="ri-information-line"></i>{{ __('client.hosting.backups.create_hint') }}</div>
</div>

<div class="bk-card">
    <div class="bk-ch"><i class="ri-history-line"></i>{{ __('client.hosting.backups.restore_points') }}</div>
    @if(empty($backups))
    <div class="bk-empty">{{ __('client.hosting.backups.empty') }}</div>
    @else
    <div style="overflow-x:auto">
    <table class="bk-table">
        <thead><tr>
            <th>{{ __('client.hosting.backups.created') }}</th>
            <th>{{ __('client.hosting.backups.contents') }}</th>
            <th>{{ __('client.hosting.backups.size') }}</th>
            <th style="text-align:right">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
            @foreach($backups as $b)
            <tr>
                <td>
                    <div class="bk-when">{{ $b['created_at'] ? \Illuminate\Support\Carbon::parse($b['created_at'])->format('d M Y H:i') : '—' }}</div>
                    <div class="bk-file">{{ $b['name'] ?: $b['filename'] }}</div>
                </td>
                <td>
                    <div class="bk-doms">{{ implode(', ', $b['domains']) }}</div>
                    <span class="bk-tag">{{ $b['type'] === 'incremental' ? __('client.hosting.backups.incremental') : __('client.hosting.backups.full') }}</span>
                    @if($b['encrypted'])<span class="bk-enc"><i class="ri-lock-line" style="font-size:10px"></i> {{ __('client.hosting.backups.encrypted') }}</span>@endif
                </td>
                {{-- A small archive is not "0.0 MB"; show the unit that actually reads. --}}
                <td>@php($mb = (float) $b['size_mb'])
                    @if($mb >= 1024) {{ number_format($mb / 1024, 2) }} GB
                    @elseif($mb >= 1) {{ number_format($mb, 1) }} MB
                    @elseif($mb > 0) {{ number_format($mb * 1024, $mb * 1024 >= 100 ? 0 : 1) }} KB
                    @else &mdash;
                    @endif
                </td>
                <td style="text-align:right">
                    <form method="POST" action="{{ route('client.services.backups.destroy', $service) }}" style="display:inline" onsubmit="return confirm('{{ __('client.hosting.backups.delete_confirm') }}')">
                        @csrf<input type="hidden" name="filename" value="{{ $b['filename'] }}">
                        <button type="submit" class="bk-act" title="{{ __('client.hosting.backups.delete') }}"><i class="ri-delete-bin-line"></i></button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <div class="bk-note"><i class="ri-alert-line"></i>{{ __('client.hosting.backups.restore_hint') }}</div>
    @endif
</div>
@endif

@endsection
