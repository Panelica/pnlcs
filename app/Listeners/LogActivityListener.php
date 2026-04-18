<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;

class LogActivityListener
{
    public function handle(object $event): void
    {
        $className = class_basename($event);

        $details = match (true) {
            $event instanceof \App\Events\ClientCreated => "Client #{$event->client->id} created",
            $event instanceof \App\Events\OrderPlaced => "Order #{$event->order->id} placed",
            $event instanceof \App\Events\InvoiceCreated => "Invoice #{$event->invoice->id} created",
            $event instanceof \App\Events\InvoicePaid => "Invoice #{$event->invoice->id} paid",
            $event instanceof \App\Events\TicketOpened => "Ticket #{$event->ticket->tid} opened",
            $event instanceof \App\Events\TicketReplied => "Ticket #{$event->ticket->tid} replied",
            $event instanceof \App\Events\ServiceActivated => "Service #{$event->service->id} activated",
            $event instanceof \App\Events\ServiceSuspended => "Service #{$event->service->id} suspended",
            $event instanceof \App\Events\ServiceTerminated => "Service #{$event->service->id} terminated",
            default => "Unknown event",
        };

        Log::info("PNLCS Event: {$className} - {$details}");
    }
}
