@extends("admin.layouts.app")
@section("title", __("admin.calendar.title"))
@section("content")

@php
    $now = now();
    $month = request("month", $now->month);
    $year = request("year", $now->year);
    $current = \Carbon\Carbon::createFromDate($year, $month, 1);
    $daysInMonth = $current->daysInMonth;
    $startDow = $current->dayOfWeek;
    $prev = $current->copy()->subMonth();
    $next = $current->copy()->addMonth();
@endphp

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1><i class="fas fa-calendar-alt"></i> {{ __('admin.calendar.title') }}</h1>
    <button type="button" onclick="document.getElementById('modal-add-event').style.display='flex'" class="btn btn-primary btn-sm">+ {{ __('admin.calendar.add_event') }}</button>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
        <a href="?month={{ $prev->month }}&year={{ $prev->year }}" class="btn btn-default btn-sm"><i class="fas fa-chevron-left"></i> {{ __('admin.calendar.previous') }}</a>
        <h3 style="margin:0;font-size:18px;font-weight:600;">{{ $current->format('F Y') }}</h3>
        <a href="?month={{ $next->month }}&year={{ $next->year }}" class="btn btn-default btn-sm">{{ __('admin.calendar.next') }} <i class="fas fa-chevron-right"></i></a>
    </div>
</div>

<div class="card">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                @foreach(["Sun","Mon","Tue","Wed","Thu","Fri","Sat"] as $d)
                <th style="padding:10px;text-align:center;border:1px solid #e5e5e5;background:#f8f9fa;font-size:12px;font-weight:600;color:#555;">{{ $d }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $day = 1; $started = false; @endphp
            @for($row = 0; $row < 6 && $day <= $daysInMonth; $row++)
            <tr>
                @for($col = 0; $col < 7; $col++)
                    @if(!$started && $col === $startDow)
                        @php $started = true; @endphp
                    @endif
                    @if($started && $day <= $daysInMonth)
                        @php
                            $cellDate = \Carbon\Carbon::createFromDate($year, $month, $day);
                            $dayEvents = $events->filter(function($e) use ($cellDate) {
                                return $e->start && $e->start->isSameDay($cellDate);
                            });
                            $isToday = $cellDate->isToday();
                            $dayNumStyle = 'font-size:12px;margin-bottom:2px;';
                            $dayNumStyle .= $isToday ? 'font-weight:700;color:#1a4d80;' : 'font-weight:500;color:#333;';
                            $cellStyle = 'border:1px solid #e5e5e5;vertical-align:top;height:90px;width:14.28%;padding:4px;';
                            $cellStyle .= $isToday ? 'background:#f0f7ff;' : '';
                        @endphp
                        <td style="{{ $cellStyle }}">
                            <div style="{{ $dayNumStyle }}">{{ $day }}</div>
                            @foreach($dayEvents->take(3) as $ev)
                                <div style="font-size:10px;padding:1px 4px;margin-bottom:1px;background:#1a4d80;color:#fff;border-radius:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;cursor:pointer;" title="{{ $ev->title }}{{ $ev->description ? ': '.$ev->description : '' }}">
                                    {{ $ev->title }}
                                </div>
                            @endforeach
                            @if($dayEvents->count() > 3)
                                <div style="font-size:9px;color:#999;">+{{ $dayEvents->count() - 3 }} more</div>
                            @endif
                        </td>
                        @php $day++; @endphp
                    @else
                        <td style="border:1px solid #e5e5e5;height:90px;background:#fafafa;"></td>
                    @endif
                @endfor
            </tr>
            @endfor
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    <div class="card">
        <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;">
            <i class="fas fa-list"></i> {{ __('admin.calendar.events_this_month') }}
        </div>
        @php
            $monthEvents = $events->filter(fn($e) => $e->start && $e->start->month == $month && $e->start->year == $year)->sortBy('start');
        @endphp
        @if($monthEvents->isEmpty())
            <div class="card-body" style="text-align:center;padding:30px;color:#999;">{{ __('admin.calendar.no_events') }}</div>
        @else
        <table class="data-table">
            <thead><tr>
                <th>{{ __('admin.calendar.event_title') }}</th>
                <th>{{ __('common.table.date') }}</th>
                <th>{{ __('common.table.description') }}</th>
                <th>{{ __('admin.calendar.by') }}</th>
                <th style="text-align:right;">{{ __('common.table.actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($monthEvents as $ev)
            <tr>
                <td style="font-weight:600;">{{ $ev->title }}</td>
                <td style="font-size:12px;">{{ $ev->start?->timezone(display_tz())->format(datetime_fmt()) }}@if($ev->end) - {{ $ev->end->timezone(display_tz())->format(datetime_fmt()) }}@endif</td>
                <td style="font-size:12px;color:#777;">{{ \Illuminate\Support\Str::limit($ev->description ?? '', 60) ?: '-' }}</td>
                <td style="font-size:12px;">{{ $ev->admin ?? '-' }}</td>
                <td style="text-align:right;">
                    <button type="button" class="btn btn-default btn-xs" onclick="editEvent({{ $ev->id }}, {{ json_encode($ev) }})">{{ __('common.actions.edit') }}</button>
                    <form method="POST" action="{{ route('admin.calendar.destroy', $ev) }}" style="display:inline;" onsubmit="return confirm('{{ __('admin.calendar.confirm_delete') }}')">
                        @csrf @method("DELETE")
                        <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Add Event Modal --}}
<div id="modal-add-event" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-event').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.calendar.add_event_modal') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-event').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.calendar.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.calendar.title_label') }}</label><input type="text" name="title" required class="form-control" placeholder="{{ __('admin.calendar.event_title_placeholder') }}"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" rows="2" class="form-control" placeholder="{{ __('admin.calendar.notes_placeholder') }}"></textarea></div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.calendar.start_date') }}</label><input type="datetime-local" name="start" required class="form-control"></div>
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.calendar.end_date') }}</label><input type="datetime-local" name="end" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label"><input type="checkbox" name="recurring" value="1"> {{ __('admin.calendar.recurring') }}</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-event').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.calendar.create_event') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Event Modal --}}
<div id="modal-edit-event" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-edit-event').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:480px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.calendar.edit_event_modal') }}</h4>
            <button type="button" onclick="document.getElementById('modal-edit-event').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form id="edit-event-form" method="POST" action="">
            @csrf @method("PUT")
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.calendar.title_label') }}</label><input type="text" name="title" id="edit-ev-title" required class="form-control"></div>
                <div class="form-group"><label class="form-label">{{ __('common.form.description') }}</label><textarea name="description" id="edit-ev-desc" rows="2" class="form-control"></textarea></div>
                <div style="display:flex;gap:10px;">
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.calendar.start_date') }}</label><input type="datetime-local" name="start" id="edit-ev-start" required class="form-control"></div>
                    <div class="form-group" style="flex:1;"><label class="form-label">{{ __('admin.calendar.end_date') }}</label><input type="datetime-local" name="end" id="edit-ev-end" class="form-control"></div>
                </div>
                <div class="form-group"><label class="form-label"><input type="checkbox" name="recurring" value="1" id="edit-ev-recurring"> {{ __('admin.calendar.recurring') }}</label></div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-edit-event').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('common.actions.update') }}</button>
            </div>
        </form>
    </div>
</div>

@push("scripts")
<script>
function editEvent(id, data) {
    document.getElementById('edit-event-form').action = '/admin/calendar/' + id;
    document.getElementById('edit-ev-title').value = data.title || '';
    document.getElementById('edit-ev-desc').value = data.description || '';
    if (data.start) {
        var s = new Date(data.start);
        document.getElementById('edit-ev-start').value = s.toISOString().slice(0, 16);
    }
    if (data.end) {
        var e = new Date(data.end);
        document.getElementById('edit-ev-end').value = e.toISOString().slice(0, 16);
    } else {
        document.getElementById('edit-ev-end').value = '';
    }
    document.getElementById('edit-ev-recurring').checked = !!data.recurring;
    document.getElementById('modal-edit-event').style.display = 'flex';
}
</script>
@endpush
@endsection
