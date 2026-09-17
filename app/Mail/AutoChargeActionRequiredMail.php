<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The bank will release the money once the cardholder says so.
 *
 * This is not a decline and must not read like one. The card works, the money
 * is there, and the issuer has asked for the one thing an unattended charge
 * cannot produce: the person. Telling that customer their card was refused is
 * how a renewal that was never in trouble turns into a cancellation —
 * ChargeAttemptState keeps ActionRequired apart from Exhausted for the same
 * reason, and so does this.
 *
 * THE WAY TO COMPLETE IT IS THE INVOICE PAGE, not the intent the off-session
 * attempt left behind. That intent is sitting at Stripe in requires_action and
 * could in principle be confirmed, but confirming it needs its client_secret
 * in a browser running Stripe.js, and PNLCS has no screen that resumes one —
 * the id is kept on the attempt row so the operator can find the payment, not
 * because the panel can hand it back to the customer. What the panel does have
 * is the ordinary Pay Now form on the invoice, which opens a fresh on-session
 * intent; the bank asks its question there, the customer answers it, and
 * PaymentService credits the invoice through the same door every other payment
 * goes through. Sending them somewhere that works beats sending them somewhere
 * that would be tidier.
 */
class AutoChargeActionRequiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice,
        public PaymentMethod $paymentMethod,
        /** What the bank is holding, which is the invoice's balance rather than its total. */
        public float $amount,
    ) {
        $this->localizeTo($this->invoice);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.auto_charge_action_required.subject', [
            'number' => $this->invoice->invoice_num ?? $this->invoice->id,
        ]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auto-charge-action-required',
            with: [
                'invoice' => $this->invoice,
                'paymentMethod' => $this->paymentMethod,
                'amount' => $this->amount,
                'companyName' => company_name(),
            ],
        );
    }
}
