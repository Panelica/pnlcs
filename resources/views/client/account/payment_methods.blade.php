@extends('client.layouts.app')
@section('title', __('client.payment_methods.title'))
@section('content')
<div class='container' style='max-width:800px;margin:40px auto;padding:0 20px'>
    <h2 style='margin-bottom:24px'>{{ __('client.payment_methods.title') }}</h2>
    @if(isset($paymentMethods) && count($paymentMethods) > 0)
        @foreach($paymentMethods as $pm)
        <div style='border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center'>
            <div>
                <strong>{{ $pm->type ?? 'Card' }}</strong>
                <span style='color:#64748b;margin-left:8px'>ending {{ $pm->last_four ?? '****' }}</span>
            </div>
        </div>
        @endforeach
    @else
        <div style='text-align:center;padding:60px 20px;color:#94a3b8'>
            <p style='font-size:48px;margin-bottom:16px'>💳</p>
            <h3>{{ __('client.payment_methods.no_methods') }}</h3>
            <p>{{ __('client.payment_methods.will_appear') }}</p>
        </div>
    @endif
</div>
@endsection
