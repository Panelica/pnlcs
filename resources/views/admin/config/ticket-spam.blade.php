@extends("admin.layouts.app")
@section("title", __("admin.ticket_spam_filter"))
@section("content")

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.ticket_spam.title') }}</h1>
</div>

<form method="POST" action="{{ route('admin.config.ticket-spam.update') }}">
    @csrf @method("PUT")

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;">
            <i class="fas fa-envelope-circle-xmark"></i> {{ __('admin.ticket_spam.banned_email_patterns') }}
        </div>
        <div class="card-body" style="padding:20px;">
            <p style="font-size:12px;color:#777;margin-bottom:10px;">{{ __('admin.ticket_spam.email_patterns_hint') }}</p>
            <textarea name="banned_emails" rows="6" class="form-control" placeholder="spam@example.com&#10;@tempmail.com&#10;noreply@">{{ $bannedEmails }}</textarea>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;">
            <i class="fas fa-filter"></i> {{ __('admin.ticket_spam.banned_keywords') }}
        </div>
        <div class="card-body" style="padding:20px;">
            <p style="font-size:12px;color:#777;margin-bottom:10px;">{{ __('admin.ticket_spam.keywords_hint') }}</p>
            <textarea name="banned_keywords" rows="6" class="form-control" placeholder="buy cheap&#10;free money&#10;casino">{{ $bannedKeywords }}</textarea>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;">
            <i class="fas fa-clock"></i> {{ __('admin.ticket_spam.rate_limiting') }}
        </div>
        <div class="card-body" style="padding:20px;">
            <div class="form-group">
                <label class="form-label">{{ __('admin.ticket_spam.max_per_hour') }}</label>
                <input type="number" name="max_per_hour" value="{{ $maxPerHour }}" min="0" max="100" class="form-control" style="width:200px;">
                <p style="font-size:12px;color:#777;margin-top:4px;">{{ __('admin.ticket_spam.rate_limit_hint') }}</p>
            </div>
        </div>
    </div>

    <div style="margin-bottom:20px;">
        <button type="submit" class="btn btn-primary">{{ __('admin.ticket_spam.save_settings') }}</button>
    </div>
</form>

{{-- Existing Filters Table --}}
<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="padding:12px 20px;border-bottom:1px solid #e5e5e5;font-weight:600;display:flex;align-items:center;justify-content:space-between;">
        <span><i class="fas fa-list"></i> {{ __('admin.ticket_spam.stored_filters') }}</span>
        <button type="button" onclick="document.getElementById('modal-add-filter').style.display='flex'" class="btn btn-primary btn-xs">+ {{ __('admin.ticket_spam.add_filter') }}</button>
    </div>
    @if($filters->isEmpty())
        <div class="card-body" style="text-align:center;padding:30px;color:#999;">{{ __('admin.ticket_spam.no_filters') }}</div>
    @else
    <table class="data-table">
        <thead><tr>
            <th>{{ __('common.table.type') }}</th>
            <th>{{ __('admin.ticket_spam.content') }}</th>
            <th>{{ __('common.table.created') }}</th>
            <th style="text-align:right;">{{ __('common.table.actions') }}</th>
        </tr></thead>
        <tbody>
        @foreach($filters as $filter)
        <tr>
            <td><span class="badge {{ $filter->type === 'email' ? 'badge-pending' : 'badge-open' }}">{{ ucfirst($filter->type) }}</span></td>
            <td><code>{{ $filter->content }}</code></td>
            <td style="font-size:12px;">{{ $filter->created_at?->format('d M Y') ?? '-' }}</td>
            <td style="text-align:right;">
                <form method="POST" action="{{ route('admin.config.ticket-spam.filter.destroy', $filter->id) }}" style="display:inline;" onsubmit="return confirm('{{ __(\"admin.ticket_spam.confirm_delete_filter\") }}')">
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

{{-- Add Filter Modal --}}
<div id="modal-add-filter" style="display:none;position:fixed;inset:0;z-index:1050;align-items:center;justify-content:center;">
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);" onclick="document.getElementById('modal-add-filter').style.display='none'"></div>
    <div style="position:relative;background:#fff;border-radius:4px;width:440px;max-width:95%;box-shadow:0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px;border-bottom:1px solid #e5e5e5;display:flex;align-items:center;justify-content:space-between;">
            <h4 style="margin:0;font-size:16px;">{{ __('admin.ticket_spam.add_filter') }}</h4>
            <button type="button" onclick="document.getElementById('modal-add-filter').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#777;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.config.ticket-spam.filter.store') }}">
            @csrf
            <div style="padding:20px;">
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_spam.type') }} *</label>
                    <select name="type" required class="form-control">
                        <option value="email">{{ __('admin.ticket_spam.email_pattern') }}</option>
                        <option value="keyword">{{ __('admin.ticket_spam.keyword') }}</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">{{ __('admin.ticket_spam.content') }} *</label>
                    <input type="text" name="content" required class="form-control" placeholder="e.g. @spammail.com or casino">
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid #e5e5e5;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('modal-add-filter').style.display='none'" class="btn btn-default btn-sm">{{ __('common.actions.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-sm">{{ __('admin.ticket_spam.add_filter') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
