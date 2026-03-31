@extends('client.layouts.app')
@section('title', 'Add Funds')
@section('content')

<div class="page-header">
    <h1>Add Funds to Account</h1>
</div>

<div class="card" style="max-width:520px;">
    <div class="card-header">Select Amount &amp; Payment Method</div>
    <div class="card-body">
        @if($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:16px;">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('client.funds.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Quick Amount</label>
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:10px;">
                    @foreach([10, 20, 50, 100, 200, 500] as $preset)
                    <button type="button" class="btn btn-default btn-sm" onclick="document.getElementById('amount').value='{{ $preset }}'" style="justify-content:center;">
                        ${{ $preset }}
                    </button>
                    @endforeach
                </div>
                <label class="form-label" for="amount">Custom Amount (USD)</label>
                <div style="position:relative;">
                    <span style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#777; font-size:14px;">$</span>
                    <input type="number" id="amount" name="amount" value="{{ old('amount') }}" min="5" max="10000" step="0.01" required
                        class="form-control" style="padding-left:24px;" placeholder="0.00">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="payment_method">Payment Method <span style="color:#c43c35;">*</span></label>
                <select id="payment_method" name="payment_method" required class="form-control">
                    <option value="">-- Select payment method --</option>
                    @if(isset($gateways) && $gateways->isNotEmpty())
                        @foreach($gateways as $gateway)
                        <option value="{{ $gateway }}" {{ old('payment_method') === $gateway ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $gateway)) }}
                        </option>
                        @endforeach
                    @else
                        <option value="banktransfer">Bank Transfer</option>
                        <option value="paypal">PayPal</option>
                    @endif
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add Funds &rarr;</button>
        </form>
    </div>
</div>

@endsection
