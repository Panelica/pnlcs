<?php

namespace Modules\Ksef\Jobs;

use App\Models\KsefInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Ksef\KsefService;

/**
 * Submits a queued invoice to KSeF in the background.
 *
 * The KSeF API is an external service; submitting inside the payment request
 * (or the admin resend click) would let a slow/unreachable endpoint hang the
 * request and turn it into a 503. The scheduler runs queue:work every minute,
 * so a queued submission leaves the request immediately and lands shortly
 * after.
 */
class SubmitInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $ksefInvoiceId) {}

    public function handle(KsefService $service): void
    {
        $record = KsefInvoice::find($this->ksefInvoiceId);

        if (! $record) {
            return;
        }

        $service->send($record);
    }
}
