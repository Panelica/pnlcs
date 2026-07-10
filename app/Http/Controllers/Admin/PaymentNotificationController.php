<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentNotification;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentNotificationController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        $status = $request->get('status', 'pending');

        $notifications = PaymentNotification::with(['invoice', 'client', 'admin'])
            ->when(in_array($status, ['pending', 'approved', 'rejected']), fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payment-notifications.index', compact('notifications', 'status'));
    }

    public function receipt(PaymentNotification $paymentNotification)
    {
        abort_unless($paymentNotification->receipt_path && Storage::disk('local')->exists($paymentNotification->receipt_path), 404);

        return Storage::disk('local')->response($paymentNotification->receipt_path);
    }

    /**
     * Approve: records the reported amount through the payment chain —
     * marks the invoice paid (or partially paid) and triggers provisioning.
     */
    public function approve(Request $request, PaymentNotification $paymentNotification): RedirectResponse
    {
        if ($paymentNotification->status !== 'pending') {
            return back()->with('info', __('admin.payment_notifications.already_reviewed'));
        }

        $validated = $request->validate([
            'amount'     => ['nullable', 'numeric', 'min:0.01'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $amount = (float) ($validated['amount'] ?? $paymentNotification->amount);

        $result = $this->payments->applyPayment(
            $paymentNotification->invoice,
            $paymentNotification->gateway,
            'PN-' . $paymentNotification->id,
            $amount
        );

        $paymentNotification->update([
            'status'      => 'approved',
            'amount'      => $amount,
            'admin_id'    => auth('admin')->id(),
            'admin_note'  => $validated['admin_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        // Partial transfer — invoice stays open for the remainder.
        if (($result['balance'] ?? 0) > 0.009) {
            return back()->with('success', __('admin.payment_notifications.approved_partial', [
                'balance' => number_format($result['balance'], 2),
            ]));
        }

        return back()->with('success', __('admin.payment_notifications.approved'));
    }

    public function reject(Request $request, PaymentNotification $paymentNotification): RedirectResponse
    {
        if ($paymentNotification->status !== 'pending') {
            return back()->with('info', __('admin.payment_notifications.already_reviewed'));
        }

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
        ]);

        $paymentNotification->update([
            'status'      => 'rejected',
            'admin_id'    => auth('admin')->id(),
            'admin_note'  => $validated['admin_note'],
            'reviewed_at' => now(),
        ]);

        // Release the invoice back to unpaid so the client can pay again.
        $invoice = $paymentNotification->invoice;
        if ($invoice && strtolower((string) $invoice->status) === InvoiceStatus::PaymentPending->value) {
            $invoice->update(['status' => InvoiceStatus::Unpaid->value]);
        }

        try {
            $email = $paymentNotification->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\PaymentNotificationRejectedMail($paymentNotification));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Payment notification reject mail failed: ' . $e->getMessage());
        }

        return back()->with('success', __('admin.payment_notifications.rejected'));
    }
}
