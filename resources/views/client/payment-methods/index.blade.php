@extends("client.layouts.app")
@section("title", __("client.payment_methods.title"))
@section("content")

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ __('client.payment_methods.title') }}</h1>
        <p class="pn-page-subtitle">{{ __('client.payment_methods.subtitle') }}</p>
    </div>
</div>

<div class="pn-card mb-24">
    <div class="pn-card-body-flush">
        <table class="pn-table">
            <thead>
                <tr>
                    <th>{{ __('common.table.description') }}</th>
                    <th>{{ __('client.payment_methods.type') }}</th>
                    <th>{{ __('client.payment_methods.details') }}</th>
                    <th>{{ __('common.table.status') }}</th>
                    <th>{{ __('common.table.action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($methods as $method)
                <tr>
                    <td style="font-weight:600">{{ $method->description ?: '—' }}</td>
                    <td class="text-muted text-sm">{{ __('client.payment_methods.type_' . strtolower($method->payment_type ?: 'other')) }}</td>
                    <td class="text-muted text-sm">
                        @if($method->last_four)•••• {{ $method->last_four }}@endif
                        @if($method->expiry_date) &nbsp;{{ __('client.payment_methods.expires') }} {{ $method->expiry_date }}@endif
                    </td>
                    <td>
                        @if($method->is_default)
                        <span class="badge badge-active">{{ __('client.payment_methods.default') }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            @if(!$method->is_default)
                            <form method="POST" action="{{ route('client.payment-methods.default', $method) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-xs">{{ __('client.payment_methods.make_default') }}</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('client.payment-methods.destroy', $method) }}"
                                  onsubmit="return confirm('{{ __('client.payment_methods.remove_confirm') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">{{ __('common.actions.delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="pn-empty">
                            <div class="pn-empty-icon">&#128179;</div>
                            <p>{{ __('client.payment_methods.none') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-header"><span class="pn-card-title">{{ __('client.payment_methods.add_bank_account') }}</span></div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">{{ __('client.payment_methods.add_bank_hint') }}</p>
        <form method="POST" action="{{ route('client.payment-methods.store') }}" style="max-width:560px">
            @csrf
            <input type="hidden" name="payment_type" value="BankAccount">
            <div style="display:grid;grid-template-columns:1fr 160px;gap:12px">
                <div>
                    <label class="pn-label">{{ __('client.payment_methods.account_label') }} *</label>
                    <input type="text" name="description" class="pn-input" required maxlength="255"
                           placeholder="{{ __('client.payment_methods.account_placeholder') }}">
                </div>
                <div>
                    <label class="pn-label">{{ __('client.payment_methods.last_four') }}</label>
                    <input type="text" name="last_four" class="pn-input" maxlength="4" pattern="[0-9]{4}" placeholder="1234">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:14px">{{ __('client.payment_methods.add_button') }}</button>
        </form>
    </div>
</div>

@endsection
