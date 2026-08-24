{{-- The one card on this page that asks the customer to DO something,
     so it does not dress like the read-only ones around it: the primary
     colour takes the border and a tinted header. --}}
<div class="pn-card" style="margin-top:16px;border:1.5px solid var(--primary);box-shadow:0 0 0 3px var(--primary-light);">
    <div class="pn-card-header" style="background:var(--primary-light);border-bottom:1.5px solid var(--primary);">
        <span class="pn-card-title" style="color:var(--primary);">{{ __('client.invoices.payment_notification_title') }}</span>
    </div>
    <div class="pn-card-body">
        <p class="text-muted text-sm mb-16">{{ __('client.invoices.payment_notification_intro') }}</p>

        @if($errors->any())
        <div class="pn-alert pn-alert-danger mb-16">
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.invoices.payment-notification', $invoice) }}" enctype="multipart/form-data">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_sender_name') }} *</label>
                    <input type="text" name="sender_name" class="pn-input" value="{{ old('sender_name') }}" required maxlength="255">
                </div>
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_bank_name') }}</label>
                    <input type="text" name="bank_name" class="pn-input" value="{{ old('bank_name') }}" maxlength="255">
                </div>
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_amount') }} *</label>
                    <input type="number" name="amount" class="pn-input" step="0.01" min="0.01"
                           value="{{ old('amount', number_format((float) ($balance ?? $invoice->total), 2, '.', '')) }}" required>
                </div>
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_transfer_date') }} *</label>
                    <input type="date" name="transfer_date" class="pn-input" value="{{ old('transfer_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_reference') }}</label>
                    <input type="text" name="reference" class="pn-input" value="{{ old('reference') }}" maxlength="255"
                           placeholder="{{ __('client.invoices.pn_reference_placeholder', ['num' => $invoice->invoice_num ?? $invoice->id]) }}">
                </div>
                <div>
                    <label class="pn-label">{{ __('client.invoices.pn_receipt') }}</label>
                    <input type="file" name="receipt" class="pn-input" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted">{{ __('client.invoices.pn_receipt_hint') }}</small>
                </div>
            </div>
            <div style="margin-top:12px;">
                <label class="pn-label">{{ __('client.invoices.pn_note') }}</label>
                <textarea name="client_note" class="pn-input" rows="2" maxlength="2000">{{ old('client_note') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:14px;">
                {{ __('client.invoices.pn_submit') }}
            </button>
        </form>
    </div>
</div>
