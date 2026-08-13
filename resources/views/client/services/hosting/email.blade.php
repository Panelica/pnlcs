@extends("client.layouts.app")
@section("title", __('client.hosting.email.title'))
@section("content")

<a href="{{ route('client.services.show', $service) }}" class="pn-back">
    <i class="ri-arrow-left-line"></i>
    {{ $service->product?->name ?? __('client.services.title') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title"><i class="ri-mail-line" style="margin-right:8px;color:var(--primary,#3b82f6)"></i>{{ __('client.hosting.email.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.hosting.email.subtitle') }}</p>
    </div>
    <span class="text-muted" style="font-size:13px">{{ count($emails) }} {{ __('client.hosting.email.mailboxes') }}</span>
</div>

@if(empty($domains))
<div class="pn-card mb-24">
    <div class="pn-card-body">
        <p class="text-muted" style="margin:0">{{ __('client.hosting.email.no_domains') }}</p>
    </div>
</div>
@else
<div class="pn-card mb-24">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.hosting.email.create_title') }}</span></div>
    <div class="pn-card-body">
        <form method="POST" action="{{ route('client.services.emails.store', $service) }}">
            @csrf
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div style="flex:1;min-width:160px">
                    <label class="pn-label">{{ __('client.hosting.email.mailbox_name') }}</label>
                    <input type="text" name="local_part" required maxlength="64" class="pn-input" placeholder="info" value="{{ old('local_part') }}">
                </div>
                <div style="font-size:20px;color:var(--text-muted,#6b7280);padding-bottom:8px">@</div>
                <div style="flex:1;min-width:160px">
                    <label class="pn-label">{{ __('client.hosting.email.domain') }}</label>
                    <select name="domain_id" required class="pn-input">
                        @foreach($domains as $id => $name)
                        <option value="{{ $id }}" @selected(old('domain_id') === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;min-width:160px">
                    <label class="pn-label">{{ __('client.hosting.email.password') }}</label>
                    <input type="password" name="password" required minlength="8" maxlength="128" class="pn-input" autocomplete="new-password">
                </div>
                <div style="width:130px">
                    <label class="pn-label">{{ __('client.hosting.email.quota_mb') }}</label>
                    <input type="number" name="quota_mb" min="0" max="1048576" class="pn-input" placeholder="1024" value="{{ old('quota_mb') }}">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="white-space:nowrap">
                        <i class="ri-add-line"></i> {{ __('client.hosting.email.create_button') }}
                    </button>
                </div>
            </div>
            @error('local_part')<div class="text-danger" style="font-size:12px;margin-top:6px">{{ $message }}</div>@enderror
            @error('password')<div class="text-danger" style="font-size:12px;margin-top:6px">{{ $message }}</div>@enderror
        </form>
    </div>
</div>
@endif

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.hosting.email.accounts_title') }}</span></div>
    <div class="pn-card-body-flush">
        @if(empty($emails))
        <div class="pn-card-body"><p class="text-muted" style="margin:0">{{ __('client.hosting.email.empty') }}</p></div>
        @else
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('client.hosting.email.address') }}</th>
                    <th>{{ __('client.hosting.email.usage') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th style="text-align:right">{{ __('common.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($emails as $mail)
                <tr>
                    <td><span class="pn-code">{{ $mail['email'] }}</span></td>
                    <td class="text-muted text-sm">
                        {{ number_format($mail['used_mb']) }}
                        @if($mail['quota_mb'] > 0)/ {{ number_format($mail['quota_mb']) }} MB@else/ {{ __('client.hosting.email.unlimited') }}@endif
                    </td>
                    <td><span class="badge badge-{{ strtolower($mail['status']) === 'active' ? 'active' : 'suspended' }}">{{ $mail['status'] ?: '-' }}</span></td>
                    <td style="text-align:right;white-space:nowrap">
                        <details style="display:inline-block;text-align:left;position:relative">
                            <summary class="pn-btn pn-btn-sm" style="list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px"><i class="ri-key-2-line"></i>{{ __('client.hosting.email.change_password') }}</summary>
                            <div style="position:absolute;right:0;z-index:10;margin-top:6px;background:var(--card-bg,#fff);border:1px solid var(--border,#e5e7eb);border-radius:8px;padding:12px;box-shadow:0 6px 20px rgba(0,0,0,.12);min-width:240px">
                                <form method="POST" action="{{ route('client.services.emails.password', $service) }}">
                                    @csrf
                                    <input type="hidden" name="email_id" value="{{ $mail['id'] }}">
                                    <label class="pn-label">{{ __('client.hosting.email.new_password') }}</label>
                                    <input type="password" name="password" required minlength="8" maxlength="128" class="pn-input" autocomplete="new-password" style="margin-bottom:8px">
                                    <button type="submit" class="btn btn-primary pn-btn-sm" style="width:100%">{{ __('client.hosting.email.save') }}</button>
                                </form>
                            </div>
                        </details>
                        <form method="POST" action="{{ route('client.services.emails.destroy', $service) }}" style="display:inline"
                              onsubmit="return confirm('{{ __('client.hosting.email.delete_confirm') }}')">
                            @csrf
                            <input type="hidden" name="email_id" value="{{ $mail['id'] }}">
                            <button type="submit" class="pn-btn pn-btn-sm pn-btn-danger"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@endsection
