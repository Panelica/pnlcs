{{--
    Which tickets a rule applies to. Without these three fields every rule was
    global: a rule meant for one department escalated every ticket in the
    system and mailed its auto-reply to unrelated customers.

    Selecting nothing in a list means "any", which is what the escalation
    service already does with an empty scope.
--}}
@php
    $scopeDepartments = is_array($rule?->departments) ? array_map('strval', $rule->departments) : [];
    $scopeStatuses = is_array($rule?->statuses) ? $rule->statuses : [];
    $scopePriorities = is_array($rule?->priorities) ? $rule->priorities : [];
@endphp
<div style="margin-top:12px;padding:12px;border:1px solid #e5e5e5;border-radius:4px;">
    <div style="font-size:13px;font-weight:600;margin-bottom:4px;">{{ __('admin.ticket_escalation.applies_to') }}</div>
    <div style="font-size:12px;color:#777;margin-bottom:10px;">{{ __('admin.ticket_escalation.scope_hint') }}</div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div class="form-group">
            <label class="form-label">{{ __('admin.ticket_escalation.departments') }}</label>
            <select name="departments[]" multiple size="4" class="form-control">
                @foreach($departments as $dept)
                <option value="{{ $dept->id }}" {{ in_array((string) $dept->id, $scopeDepartments, true) ? 'selected' : '' }}>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">{{ __('admin.ticket_escalation.statuses') }}</label>
            <select name="statuses[]" multiple size="4" class="form-control">
                @foreach($statuses as $status)
                <option value="{{ $status }}" {{ in_array($status, $scopeStatuses, true) ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">{{ __('admin.ticket_escalation.priorities') }}</label>
            <select name="priorities[]" multiple size="4" class="form-control">
                @foreach($priorities as $priority)
                <option value="{{ $priority }}" {{ in_array($priority, $scopePriorities, true) ? 'selected' : '' }}>{{ __('admin.ticket_escalation.priority_'.$priority) }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
