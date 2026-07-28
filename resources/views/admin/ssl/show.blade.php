@extends("admin.layouts.app")

@section("title", "SSL Order #" . $order->id)

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SSL Order #{{ $order->id }}</h1>
    <a href="{{ route('admin.ssl.index') }}" class="btn btn-secondary">{{ __('admin.ssl.back_to_list') }}</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        {{-- Certificate Details --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.certificate_details') }}</h5></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">{{ __('admin.ssl.domain') }}</label>
                        <p class="mb-0 fw-bold">{{ $order->domain ?: '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ __('admin.ssl.type') }}</label>
                        <p class="mb-0">{{ $order->cert_type ?: '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ __('admin.ssl.status') }}</label>
                        <p class="mb-0"><span class="badge {{ $order->getStatusBadgeClass() }}">{{ $order->status }}</span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.module') }}</label>
                        <p class="mb-0">{{ $order->module ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.remote_id') }}</label>
                        <p class="mb-0">{{ $order->remote_id ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.validation') }}</label>
                        <p class="mb-0">{{ $order->validation_method ?: '—' }}</p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.order_date') }}</label>
                        <p class="mb-0">{{ $order->order_date?->format(datetime_fmt()) ?: $order->created_at->format(datetime_fmt()) }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.completion_date') }}</label>
                        <p class="mb-0">{{ $order->completion_date?->format(datetime_fmt()) ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">{{ __('admin.ssl.expires') }}</label>
                        <p class="mb-0">
                            @if($order->crt_expires)
                                {{ $order->crt_expires->format(date_fmt()) }}
                                @if($order->daysUntilExpiry() !== null)
                                    <small class="{{ $order->daysUntilExpiry() <= 7 ? 'text-danger' : ($order->daysUntilExpiry() <= 30 ? 'text-warning' : 'text-success') }}">
                                        ({{ $order->daysUntilExpiry() }} {{ __('admin.ssl.days') }})
                                    </small>
                                @endif
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

                @if($order->domains)
                    <div class="mb-3">
                        <label class="text-muted small">{{ __('admin.ssl.san_domains') }}</label>
                        <p class="mb-0">{{ $order->domains }}</p>
                    </div>
                @endif

                @if($order->approver_email)
                    <div class="mb-3">
                        <label class="text-muted small">{{ __('admin.ssl.approver_email') }}</label>
                        <p class="mb-0">{{ $order->approver_email }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Admin Contact --}}
        @if($order->admin_first_name)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.admin_contact') }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="text-muted small">{{ __('admin.ssl.name') }}</label><p class="mb-0">{{ $order->admin_first_name }} {{ $order->admin_last_name }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">{{ __('common.form.email') }}</label><p class="mb-0">{{ $order->admin_email }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">{{ __('admin.ssl.phone') }}</label><p class="mb-0">{{ $order->admin_phone ?: '—' }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">{{ __('admin.ssl.organization') }}</label><p class="mb-0">{{ $order->admin_org ?: '—' }}</p></div>
                    <div class="col-12 mb-2"><label class="text-muted small">{{ __('common.form.address') }}</label><p class="mb-0">{{ collect([$order->admin_address, $order->admin_city, $order->admin_state, $order->admin_zip, $order->admin_country])->filter()->implode(', ') ?: '—' }}</p></div>
                </div>
            </div>
        </div>
        @endif

        {{-- CSR --}}
        @if($order->csr)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.csr') }}</h5></div>
            <div class="card-body">
                <textarea class="form-control font-monospace" rows="6" readonly>{{ $order->csr }}</textarea>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Client Info --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.client') }}</h5></div>
            <div class="card-body">
                @if($order->client)
                    <p class="mb-1"><strong>{{ $order->client->first_name }} {{ $order->client->last_name }}</strong></p>
                    <p class="mb-1 text-muted">{{ $order->client->email }}</p>
                    <a href="{{ route('admin.clients.show', $order->client_id) }}" class="btn btn-sm btn-outline-primary mt-2">{{ __('admin.ssl.view_client') }}</a>
                @else
                    <p class="text-muted">{{ __('admin.ssl.no_client') }}</p>
                @endif
            </div>
        </div>

        {{-- Related Service --}}
        @if($order->service)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.related_service') }}</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $order->service->product?->name ?? 'Service #' . $order->service_id }}</strong></p>
                <p class="mb-0 text-muted">Status: {{ $order->service->status }}</p>
                <a href="{{ route('admin.services.show', $order->service_id) }}" class="btn btn-sm btn-outline-primary mt-2">{{ __('admin.ssl.view_service') }}</a>
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.actions') }}</h5></div>
            <div class="card-body d-grid gap-2">
                @if(in_array($order->status, ['Awaiting Issuance', 'Configuration Submitted']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="poll">
                        <button type="submit" class="btn btn-primary w-100">{{ __('admin.ssl.poll_status') }}</button>
                    </form>
                @endif

                @if(in_array($order->status, ['Awaiting Issuance', 'Configuration Submitted']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="resend">
                        <button type="submit" class="btn btn-warning w-100">{{ __('admin.ssl.resend_validation') }}</button>
                    </form>
                @endif

                @if($order->isCompleted())
                    <a href="{{ route('admin.ssl.download', $order) }}" class="btn btn-success w-100">{{ __('admin.ssl.download_cert') }}</a>
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="reissue">
                        <input type="hidden" name="csr" value="{{ $order->csr }}">
                        <button type="submit" class="btn btn-info w-100" onclick="return confirm('Are you sure you want to reissue this certificate?')">{{ __('admin.ssl.reissue') }}</button>
                    </form>
                @endif

                @if(!in_array($order->status, ['Cancelled', 'Revoked', 'Expired']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="revoke">
                        <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to revoke this certificate?')">{{ __('admin.ssl.revoke_certificate') }}</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ __('admin.ssl.timeline') }}</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><small class="text-muted">{{ __('admin.ssl.created') }}</small><br>{{ $order->created_at->format(datetime_fmt()) }}</li>
                    @if($order->order_date)
                        <li class="mb-2"><small class="text-muted">{{ __('admin.ssl.submitted_ca') }}</small><br>{{ $order->order_date->format(datetime_fmt()) }}</li>
                    @endif
                    @if($order->completion_date)
                        <li class="mb-2"><small class="text-muted">{{ __('admin.ssl.completed') }}</small><br>{{ $order->completion_date->format(datetime_fmt()) }}</li>
                    @endif
                    @if($order->last_polled_at)
                        <li class="mb-2"><small class="text-muted">{{ __('admin.ssl.last_polled') }}</small><br>{{ $order->last_polled_at->format(datetime_fmt()) }}</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
