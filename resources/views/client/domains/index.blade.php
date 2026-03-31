@extends('client.layouts.app')
@section('title', 'My Domains')
@section('content')

<div class="page-header">
    <h1>My Domains</h1>
    <a href="#" class="btn btn-primary btn-sm">Register Domain</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Registration Date</th>
                    <th>Expiry Date</th>
                    <th>Auto-Renew</th>
                </tr>
            </thead>
            <tbody>
                @forelse($domains as $d)
                <tr>
                    <td style="font-weight:500; color:#333;">{{ $d->domain }}</td>
                    <td><span class="badge badge-{{ strtolower($d->status) }}">{{ ucfirst($d->status) }}</span></td>
                    <td style="color:#777;">{{ $d->registration_date?->format('d M Y') ?? '-' }}</td>
                    <td style="color:#777;">{{ $d->expiry_date?->format('d M Y') ?? '-' }}</td>
                    <td>
                        @if($d->auto_renew ?? false)
                            <span class="badge badge-active">Yes</span>
                        @else
                            <span class="badge badge-cancelled">No</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; padding:32px; color:#999;">
                        No domains found. <a href="#" style="color:#337ab7;">Register one now</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($domains instanceof \Illuminate\Pagination\LengthAwarePaginator && $domains->hasPages())
    <div style="margin-top:16px;">{{ $domains->links() }}</div>
@endif

@endsection
