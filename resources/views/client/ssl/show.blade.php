@extends("client.layouts.app")

@section("title", "SSL Certificate - " . ($order->domain ?: 'Order #' . $order->id))

@section("content")
<div class="mb-4">
    <a href="{{ route('client.ssl.index') }}" class="btn btn-sm btn-secondary">&larr; Back to SSL Certificates</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Certificate Details</h5>
                <span class="badge {{ $order->getStatusBadgeClass() }}">{{ $order->status }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Domain</label>
                        <p class="mb-0 fw-bold">{{ $order->domain ?: '—' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Certificate Type</label>
                        <p class="mb-0">{{ $order->cert_type ?: '—' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Validation Method</label>
                        <p class="mb-0">{{ $order->validation_method ?: '—' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Approver Email</label>
                        <p class="mb-0">{{ $order->approver_email ?: '—' }}</p>
                    </div>
                </div>

                @if($order->crt_expires)
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Issued Date</label>
                        <p class="mb-0">{{ $order->completion_date?->format('d M Y') ?: '—' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Expiry Date</label>
                        <p class="mb-0">
                            {{ $order->crt_expires->format('d M Y') }}
                            @if($order->daysUntilExpiry() !== null)
                                <small class="{{ $order->daysUntilExpiry() <= 7 ? 'text-danger' : ($order->daysUntilExpiry() <= 30 ? 'text-warning' : 'text-success') }}">
                                    ({{ $order->daysUntilExpiry() }} days remaining)
                                </small>
                            @endif
                        </p>
                    </div>
                </div>
                @endif

                @if($order->domains)
                <div class="mb-3">
                    <label class="text-muted small">SAN Domains</label>
                    <p class="mb-0">{{ $order->domains }}</p>
                </div>
                @endif
            </div>
        </div>

        @if($order->status === 'Awaiting Configuration')
        <div class="alert alert-warning">
            <strong>Action Required:</strong> Your SSL certificate needs to be configured before it can be issued.
            <a href="{{ route('client.ssl.configure', $order) }}" class="btn btn-warning btn-sm ms-2">Configure Now</a>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Actions</h5></div>
            <div class="card-body d-grid gap-2">
                @if($order->isCompleted())
                    <a href="{{ route('client.ssl.download', $order) }}" class="btn btn-success">
                        <i class="fas fa-download me-1"></i> Download Certificate
                    </a>
                @endif

                @if(in_array($order->status, ['Awaiting Issuance', 'Configuration Submitted']))
                    <form method="POST" action="{{ route('client.ssl.resendValidation', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-warning w-100">Resend Validation Email</button>
                    </form>
                @endif

                @if($order->status === 'Awaiting Configuration')
                    <a href="{{ route('client.ssl.configure', $order) }}" class="btn btn-primary">Configure Certificate</a>
                @endif
            </div>
        </div>

        @if($order->service?->product)
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Product</h5></div>
            <div class="card-body">
                <p class="mb-1 fw-bold">{{ $order->service->product->name }}</p>
                <p class="mb-0 text-muted small">{{ $order->service->product->description }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
