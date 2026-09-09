@extends("admin.layouts.app")
@section("title", __("admin.automation_status"))
@section("content")

{{--
     Bu sayfa eskiden sekiz satirlik, elle yazilmis bir tabloydu: her satirda
     "Hic calismadi" ve "Yapilandirilmamis" yaziyordu — sunucuda ne olursa
     olsun. Otomasyonun yasadigini soylemesi gereken tek ekran, inanilamayacak
     tek ekrandi. Artik gercek zamanlanmis gorevleri ve gercek son calisma
     kayitlarini okuyor.
--}}
@php
    $tasks = app(\App\Services\ScheduleStatusService::class)->tasks();
    $counts = app(\App\Services\ScheduleStatusService::class)->summary();
    $tone = [
        'ok'      => ['#1f8a4c', '#e7f5ec'],
        'overdue' => ['#a76a00', '#fdf1dc'],
        'failed'  => ['#b0322a', '#fbe6e4'],
        'pending' => ['#5b6673', '#eef1f4'],
    ];
@endphp

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <h1>{{ __('admin.automation.title') }}</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @foreach(['failed','overdue','pending','ok'] as $state)
            @if($counts[$state] > 0 || $state === 'ok')
                <span style="padding:4px 11px;border-radius:20px;font-size:12px;font-weight:600;background:{{ $tone[$state][1] }};color:{{ $tone[$state][0] }};">
                    {{ __('admin.automation.state_' . $state) }} {{ $counts[$state] }}
                </span>
            @endif
        @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>{{ __('admin.automation.cron_job_status') }}</strong></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="min-width:720px;">
            <thead>
                <tr>
                    <th>{{ __('admin.automation.task') }}</th>
                    <th>{{ __('admin.automation.frequency') }}</th>
                    <th>{{ __('admin.automation.last_run') }}</th>
                    <th>{{ __('admin.automation.next_due') }}</th>
                    <th style="text-align:right;">{{ __('common.table.status') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($tasks as $t)
                <tr>
                    <td>
                        <strong>{{ $t['label'] }}</strong>
                        <br><code style="font-size:11px;color:#98a1ab;">{{ $t['command'] }}</code>
                    </td>
                    <td style="white-space:nowrap;">{{ $t['frequency'] }}</td>
                    <td style="white-space:nowrap;">
                        @if($t['last_run_at'])
                            {{ $t['last_run_at']->diffForHumans() }}
                            <br><small style="color:#98a1ab;">
                                {{ $t['last_run_at']->setTimezone(display_tz())->format('d.m.Y H:i') }}
                                @if($t['runtime_ms'] !== null) · {{ number_format($t['runtime_ms'] / 1000, 1) }}s @endif
                            </small>
                        @else
                            <span style="color:#98a1ab;">{{ __('admin.automation.never_yet') }}</span>
                        @endif
                        @if($t['failures'] > 0)
                            <br><small style="color:#b0322a;">{{ $t['failures'] }}× {{ __('admin.automation.state_failed') }}</small>
                        @endif
                    </td>
                    <td style="white-space:nowrap;color:#98a1ab;">
                        {{ $t['next_due'] ? $t['next_due']->diffForHumans() : '—' }}
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <span style="padding:3px 9px;border-radius:4px;font-size:11.5px;font-weight:600;background:{{ $tone[$t['state']][1] }};color:{{ $tone[$t['state']][0] }};">
                            {{ __('admin.automation.state_' . $t['state']) }}
                        </span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:15px;">
    <div class="card-header"><strong>{{ __('admin.automation.cron_configuration') }}</strong></div>
    <div class="card-body">
        <p style="font-size:13px;color:#555;">{{ __('admin.automation.cron_instruction') }}</p>
        <div style="background:#f5f5f5;padding:12px;border-radius:4px;font-family:monospace;font-size:12px;color:#333;margin:10px 0;">
            * * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1
        </div>
        <p style="font-size:12px;color:#999;margin-top:8px;">{{ __('admin.automation.cron_explanation') }}</p>
    </div>
</div>

@endsection
