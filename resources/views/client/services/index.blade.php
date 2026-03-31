@extends('client.layouts.app')
@section('title', 'My Services')
@section('content')

<div class="page-header">
    <h1>My Services</h1>
    <a href="{{ route('client.store') }}" class="btn btn-primary btn-sm">Order New Service</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Domain</th>
                    <th>Billing Cycle</th>
                    <th>Amount</th>
                    <th>Next Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $s)
                <tr style="cursor:pointer;" onclick="window.location='{{ route('client.services.show', $s) }}'">
                    <td style="font-weight:500;">
                        <a href="{{ route('client.services.show', $s) }}" style="color:#337ab7;">{{ $s->product->name ?? 'N/A' }}</a>
                    </td>
                    <td style="color:#555;">{{ $s->domain ?? '-' }}</td>
                    <td style="color:#777; text-transform:capitalize;">{{ $s->billing_cycle ?? '-' }}</td>
                    <td style="font-weight:500;">${{ number_format($s->amount, 2) }}</td>
                    <td style="color:#777;">{{ $s->next_due_date?->format('d M Y') ?? '-' }}</td>
                    <td><span class="badge badge-{{ strtolower($s->status) }}">{{ ucfirst($s->status) }}</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:32px; color:#999;">
                        No services found. <a href="{{ route('client.store') }}" style="color:#337ab7;">Order one now</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($services instanceof \Illuminate\Pagination\LengthAwarePaginator && $services->hasPages())
    <div style="margin-top:16px;">{{ $services->links() }}</div>
@endif

@endsection
