@extends('admin.layouts.app')
@section('title', __('admin.addons.service_edit_title'))
@section('content')
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>
        {{ __('admin.addons.service_edit_title') }}
        <span class="badge-{{ strtolower($addon->status) }}" style="font-size:13px;vertical-align:middle;margin-left:8px;">{{ status_label($addon->status) }}</span>
    </h1>
    <a href="{{ route('admin.services.show', $service) }}" class="btn btn-default btn-sm">&larr; {{ __('admin.services.back') }}</a>
</div>

<div class="card">
    <div class="card-header">
        <strong>{{ $addon->label() }}</strong>
        <span style="color:#777;"> &mdash; {{ $service->domain ?: ('#'.$service->id) }}</span>
    </div>
    <div class="card-body">
        @if($addon->isEnded())
            <div class="alert alert-danger">{{ __('admin.addons.service_ended') }}</div>
        @else
            @if($addon->isDraft())
                <div class="alert alert-info">{{ __('admin.addons.service_draft_note', ['date' => $addon->next_due_date?->format(date_fmt()) ?? now()->format(date_fmt())]) }}</div>
            @elseif($addon->isPending())
                <div class="alert alert-warning">{{ __('admin.addons.service_pending_edit_note') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.services.addons.update', [$service, $addon]) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label" for="addon-amount">{{ __('common.table.amount') }}</label>
                    <input type="number" step="0.01" min="0" id="addon-amount" name="amount"
                           value="{{ old('amount', number_format((float) $addon->amount, 2, '.', '')) }}"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" for="addon-cycle">{{ __('common.table.billing_cycle') }}</label>
                    <select id="addon-cycle" name="billing_cycle" class="form-control">
                        @foreach($cycles as $cycle)
                            <option value="{{ $cycle }}" @selected(old('billing_cycle', $addon->billing_cycle) === $cycle)>{{ cycle_label($cycle) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="addon-next-due">{{ $addon->isDraft() ? __('admin.addons.service_issue_date') : __('client.services.next_due_date') }}</label>
                    <input type="date" id="addon-next-due" name="next_due_date"
                           value="{{ old('next_due_date', $addon->next_due_date?->toDateString()) }}"
                           class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label" for="addon-notes">{{ __('admin.addons.service_notes') }}</label>
                    <textarea id="addon-notes" name="notes" rows="3" class="form-control">{{ old('notes', $addon->notes) }}</textarea>
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="submit" class="btn btn-primary">{{ __('common.actions.save') }}</button>
                    @if($addon->isDraft())
                    <button type="submit" name="accept" value="1" class="btn btn-success">{{ __('admin.addons.service_save_accept') }}</button>
                    @endif
                    <a href="{{ route('admin.services.show', $service) }}" class="btn btn-default">{{ __('common.actions.cancel') }}</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
