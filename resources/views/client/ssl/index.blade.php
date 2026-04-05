@extends("client.layouts.app")

@section("title", __("client.ssl.title"))

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">{{ __('client.ssl.my_certificates') }}</h1>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($orders->count())
<div class="row">
    @foreach($orders as $order)
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="card-title mb-1">{{ $order->domain ?: __('client.ssl.pending_config') }}</h5>
                        <small class="text-muted">{{ $order->cert_type ?: $order->module }}</small>
                    </div>
                    <span class="badge {{ $order->getStatusBadgeClass() }}">{{ $order->status }}</span>
                </div>

                @if($order->crt_expires)
                    <p class="mb-2">
                        <small class="text-muted">{{ __('client.ssl.expires') }}</small>
                        {{ $order->crt_expires->format('d M Y') }}
                        @if($order->daysUntilExpiry() !== null && $order->daysUntilExpiry() <= 30)
                            <span class="text-warning">({{ $order->daysUntilExpiry() }}d)</span>
                        @endif
                    </p>
                @endif

                <p class="mb-0">
                    <small class="text-muted">{{ __('client.ssl.ordered') }}</small>
                    {{ $order->created_at->format('d M Y') }}
                </p>
            </div>
            <div class="card-footer bg-transparent">
                @if($order->status === 'Awaiting Configuration')
                    <a href="{{ route('client.ssl.configure', $order) }}" class="btn btn-primary btn-sm">{{ __('client.ssl.configure_now') }}</a>
                @else
                    <a href="{{ route('client.ssl.show', $order) }}" class="btn btn-outline-primary btn-sm">{{ __('client.ssl.view_details') }}</a>
                @endif

                @if($order->isCompleted())
                    <a href="{{ route('client.ssl.download', $order) }}" class="btn btn-success btn-sm">{{ __('common.actions.download') }}</a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-3">{{ $orders->links() }}</div>
@else
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
        <h5>{{ __('client.ssl.no_ssl_title') }}</h5>
        <p class="text-muted">{{ __('client.ssl.no_ssl_desc') }}</p>
        <a href="{{ route('client.store') }}" class="btn btn-primary">{{ __('client.ssl.browse_ssl') }}</a>
    </div>
</div>
@endif
@endsection
