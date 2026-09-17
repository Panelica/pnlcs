<?php

namespace App\Mail;

use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The card on file did not pay the invoice.
 *
 * NOT ONE PER ATTEMPT. The charger sends this on the first refusal — while
 * there is still something the customer can do about it — and again if it
 * gives up, and never in between. Three of these on top of the reminder chain
 * the operator already runs at 08:00 (late1, late3, late7) would be six emails
 * about one invoice in a week, which is how a billing system teaches people to
 * filter it into a folder they never open.
 *
 * WHAT THE GATEWAY SAID IS DELIBERATELY NOT IN HERE. last_message holds
 * whatever the module wrote — "Stripe error: Your card has insufficient
 * funds", but also "Stripe secret key not configured" and "Stored payment
 * method does not belong to this invoice's client" — and a customer must never
 * be shown the second kind. The one distinction that changes what they should
 * do is carried explicitly instead: $cardEnded says the issuer has finished
 * with the card, which means storing a different one rather than topping this
 * one up. The reason in full stays in the log and on the attempt row, where the
 * operator reads it.
 *
 * @param  float  $amount  what was actually asked of the card, which is the invoice's
 *                         balance and not always its total: an applied credit or a part
 *                         payment lowers it, and a customer told the wrong figure writes
 *                         a ticket
 * @param  CarbonInterface|null  $retryAt  when the card will be tried again; null once it will not be
 * @param  bool  $cardEnded  the issuer has ended this card (PaymentMethod::STATUS_REQUIRES_UPDATE)
 */
class AutoChargeFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Invoice $invoice,
        public PaymentMethod $paymentMethod,
        public float $amount,
        public ?CarbonInterface $retryAt = null,
        public bool $cardEnded = false,
    ) {
        $this->localizeTo($this->invoice);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('email.auto_charge_failed.subject', [
            'number' => $this->invoice->invoice_num ?? $this->invoice->id,
        ]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auto-charge-failed',
            with: [
                'invoice' => $this->invoice,
                'paymentMethod' => $this->paymentMethod,
                'amount' => $this->amount,
                'retryAt' => $this->retryAt,
                'cardEnded' => $this->cardEnded,
                'companyName' => company_name(),
            ],
        );
    }
}
