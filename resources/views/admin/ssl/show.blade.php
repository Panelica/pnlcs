@extends("admin.layouts.app")

@section("title", "SSL Order #" . $order->id)

@section("content")
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SSL Order #{{ $order->id }}</h1>
    <a href="{{ route('admin.ssl.index') }}" class="btn btn-secondary">Back to List</a>
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
            <div class="card-header"><h5 class="mb-0">Certificate Details</h5></div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Domain</label>
                        <p class="mb-0 fw-bold">{{ $order->domain ?: '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Type</label>
                        <p class="mb-0">{{ $order->cert_type ?: '—' }}</p>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">Status</label>
                        <p class="mb-0"><span class="badge {{ $order->getStatusBadgeClass() }}">{{ $order->status }}</span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Module</label>
                        <p class="mb-0">{{ $order->module ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Remote ID</label>
                        <p class="mb-0">{{ $order->remote_id ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Validation</label>
                        <p class="mb-0">{{ $order->validation_method ?: '—' }}</p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="text-muted small">Order Date</label>
                        <p class="mb-0">{{ $order->order_date?->format('d M Y H:i') ?: $order->created_at->format('d M Y H:i') }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Completion Date</label>
                        <p class="mb-0">{{ $order->completion_date?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Expires</label>
                        <p class="mb-0">
                            @if($order->crt_expires)
                                {{ $order->crt_expires->format('d M Y') }}
                                @if($order->daysUntilExpiry() !== null)
                                    <small class="{{ $order->daysUntilExpiry() <= 7 ? 'text-danger' : ($order->daysUntilExpiry() <= 30 ? 'text-warning' : 'text-success') }}">
                                        ({{ $order->daysUntilExpiry() }} days)
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
                        <label class="text-muted small">SAN Domains</label>
                        <p class="mb-0">{{ $order->domains }}</p>
                    </div>
                @endif

                @if($order->approver_email)
                    <div class="mb-3">
                        <label class="text-muted small">Approver Email</label>
                        <p class="mb-0">{{ $order->approver_email }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Admin Contact --}}
        @if($order->admin_first_name)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Admin Contact</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="text-muted small">Name</label><p class="mb-0">{{ $order->admin_first_name }} {{ $order->admin_last_name }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">Email</label><p class="mb-0">{{ $order->admin_email }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">Phone</label><p class="mb-0">{{ $order->admin_phone ?: '—' }}</p></div>
                    <div class="col-md-6 mb-2"><label class="text-muted small">Organization</label><p class="mb-0">{{ $order->admin_org ?: '—' }}</p></div>
                    <div class="col-12 mb-2"><label class="text-muted small">Address</label><p class="mb-0">{{ collect([$order->admin_address, $order->admin_city, $order->admin_state, $order->admin_zip, $order->admin_country])->filter()->implode(', ') ?: '—' }}</p></div>
                </div>
            </div>
        </div>
        @endif

        {{-- CSR --}}
        @if($order->csr)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">CSR</h5></div>
            <div class="card-body">
                <textarea class="form-control font-monospace" rows="6" readonly>{{ $order->csr }}</textarea>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Client Info --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Client</h5></div>
            <div class="card-body">
                @if($order->client)
                    <p class="mb-1"><strong>{{ $order->client->first_name }} {{ $order->client->last_name }}</strong></p>
                    <p class="mb-1 text-muted">{{ $order->client->email }}</p>
                    <a href="{{ route('admin.clients.show', $order->client_id) }}" class="btn btn-sm btn-outline-primary mt-2">View Client</a>
                @else
                    <p class="text-muted">No client linked</p>
                @endif
            </div>
        </div>

        {{-- Related Service --}}
        @if($order->service)
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Related Service</h5></div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $order->service->product?->name ?? 'Service #' . $order->service_id }}</strong></p>
                <p class="mb-0 text-muted">Status: {{ $order->service->status }}</p>
                <a href="{{ route('admin.services.show', $order->service_id) }}" class="btn btn-sm btn-outline-primary mt-2">View Service</a>
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Actions</h5></div>
            <div class="card-body d-grid gap-2">
                @if(in_array($order->status, ['Awaiting Issuance', 'Configuration Submitted']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="poll">
                        <button type="submit" class="btn btn-primary w-100">Poll Status</button>
                    </form>
                @endif

                @if(in_array($order->status, ['Awaiting Issuance', 'Configuration Submitted']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="resend">
                        <button type="submit" class="btn btn-warning w-100">Resend Validation</button>
                    </form>
                @endif

                @if($order->isCompleted())
                    <a href="{{ route('admin.ssl.download', $order) }}" class="btn btn-success w-100">Download Certificate</a>
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="reissue">
                        <input type="hidden" name="csr" value="{{ $order->csr }}">
                        <button type="submit" class="btn btn-info w-100" onclick="return confirm('Are you sure you want to reissue this certificate?')">Reissue</button>
                    </form>
                @endif

                @if(!in_array($order->status, ['Cancelled', 'Revoked', 'Expired']))
                    <form method="POST" action="{{ route('admin.ssl.action', $order) }}">
                        @csrf
                        <input type="hidden" name="action" value="revoke">
                        <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to revoke this certificate?')">Revoke Certificate</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Timeline --}}
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Timeline</h5></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><small class="text-muted">Created:</small><br>{{ $order->created_at->format('d M Y H:i') }}</li>
                    @if($order->order_date)
                        <li class="mb-2"><small class="text-muted">Submitted to CA:</small><br>{{ $order->order_date->format('d M Y H:i') }}</li>
                    @endif
                    @if($order->completion_date)
                        <li class="mb-2"><small class="text-muted">Completed:</small><br>{{ $order->completion_date->format('d M Y H:i') }}</li>
                    @endif
                    @if($order->last_polled_at)
                        <li class="mb-2"><small class="text-muted">Last Polled:</small><br>{{ $order->last_polled_at->format('d M Y H:i') }}</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
