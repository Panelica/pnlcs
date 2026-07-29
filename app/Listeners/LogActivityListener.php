<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;
use App\Events\OrderPlaced;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

/**
 * The activity log the admin area shows.
 *
 * This listener is wired to ten business events and only ever wrote a line to
 * the file log, so the Activity Log page, the dashboard's recent-activity
 * widget, a customer's Log tab and the getactivitylog API all read a table
 * nothing had written to since the demo data was seeded.
 */
class LogActivityListener
{
    public function handle(object $event): void
    {
        $className = class_basename($event);
        [$details, $clientId] = $this->describe($event);

        Log::info("PNLCS Event: {$className} - {$details}");

        if ($details === null) {
            return;
        }

        ActivityLog::log($details, 'System', $clientId);
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function describe(object $event): array
    {
        return match (true) {
            $event instanceof ClientCreated => ["Client #{$event->client->id} created", $event->client->id],
            $event instanceof OrderPlaced => ["Order #{$event->order->id} placed", $event->order->client_id],
            $event instanceof InvoiceCreated => ["Invoice #{$event->invoice->id} created", $event->invoice->client_id],
            $event instanceof InvoicePaid => ["Invoice #{$event->invoice->id} paid", $event->invoice->client_id],
            $event instanceof TicketOpened => ["Ticket #{$event->ticket->tid} opened", $event->ticket->client_id],
            $event instanceof TicketReplied => ["Ticket #{$event->ticket->tid} replied", $event->ticket->client_id],
            $event instanceof ServiceActivated => ["Service #{$event->service->id} activated", $event->service->client_id],
            $event instanceof ServiceSuspended => ["Service #{$event->service->id} suspended", $event->service->client_id],
            $event instanceof ServiceTerminated => ["Service #{$event->service->id} terminated", $event->service->client_id],
            default => [null, null],
        };
    }
}
