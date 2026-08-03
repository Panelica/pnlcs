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
use App\Mail\AccountSignupMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\InvoicePaidMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\ServiceSuspensionMail;
use App\Mail\ServiceTerminationMail;
use App\Mail\ServiceWelcomeMail;
use App\Mail\TicketOpenedMail;
use App\Mail\TicketReplyMail;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationListener
{
    public function handleClientCreated(ClientCreated $event): void
    {
        try {
            if ($event->client->email) {
                Mail::to($event->client->email)->queue(new AccountSignupMail($event->client));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: ClientCreated email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('client.created', [
            'event_type' => 'client.created',
            'subject' => 'New Client Registered',
            'message' => "New client registered: {$event->client->first_name} {$event->client->last_name} ({$event->client->email})",
            'client_id' => $event->client->id,
        ]);
    }

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        try {
            $email = $event->order->client?->email;
            if ($email) {
                Mail::to($email)->queue(new OrderConfirmationMail($event->order));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: OrderPlaced email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('order.placed', [
            'event_type' => 'order.placed',
            'subject' => 'New Order Placed',
            'message' => "Order #{$event->order->order_num} placed by {$event->order->client?->first_name} {$event->order->client?->last_name}",
            'order_id' => $event->order->id,
            'order_num' => $event->order->order_num,
        ]);
    }

    public function handleInvoiceCreated(InvoiceCreated $event): void
    {
        try {
            $email = $event->invoice->client?->email;
            if ($email) {
                Mail::to($email)->queue(new InvoiceCreatedMail($event->invoice));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: InvoiceCreated email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('invoice.created', [
            'event_type' => 'invoice.created',
            'subject' => 'Invoice Created',
            'message' => "Invoice #{$event->invoice->invoice_num} created for {$event->invoice->client?->first_name} {$event->invoice->client?->last_name} — ".money_fmt($event->invoice->total),
            'invoice_id' => $event->invoice->id,
        ]);
    }

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        try {
            $email = $event->invoice->client?->email;
            if ($email) {
                Mail::to($email)->queue(new InvoicePaidMail($event->invoice, $event->transactionId));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: InvoicePaid email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('invoice.paid', [
            'event_type' => 'invoice.paid',
            'subject' => 'Invoice Paid',
            'message' => "Invoice #{$event->invoice->invoice_num} paid — ".money_fmt($event->invoice->total),
            'invoice_id' => $event->invoice->id,
            'transaction_id' => $event->transactionId,
        ]);
    }

    public function handleTicketOpened(TicketOpened $event): void
    {
        try {
            if ($event->ticket->email) {
                Mail::to($event->ticket->email)->queue(new TicketOpenedMail($event->ticket, false));
            }
            if (! $event->isAdmin) {
                $adminEmail = Setting::get('Email', null);
                if ($adminEmail) {
                    Mail::to($adminEmail)->queue(new TicketOpenedMail($event->ticket, true));
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: TicketOpened email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('ticket.opened', [
            'event_type' => 'ticket.opened',
            'subject' => 'New Ticket Opened',
            'message' => "Ticket #{$event->ticket->id}: {$event->ticket->subject}",
            'ticket_id' => $event->ticket->id,
        ]);
    }

    public function handleTicketReplied(TicketReplied $event): void
    {
        try {
            if ($event->isStaffReply) {
                if ($event->ticket->email) {
                    Mail::to($event->ticket->email)->queue(new TicketReplyMail($event->ticket, $event->replyMessage, true));
                }
            } else {
                $adminEmail = Setting::get('Email', null);
                if ($adminEmail) {
                    Mail::to($adminEmail)->queue(new TicketReplyMail($event->ticket, $event->replyMessage, false));
                }
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: TicketReplied email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('ticket.replied', [
            'event_type' => 'ticket.replied',
            'subject' => 'Ticket Reply',
            'message' => "Ticket #{$event->ticket->id} received a ".($event->isStaffReply ? 'staff' : 'client').' reply',
            'ticket_id' => $event->ticket->id,
        ]);
    }

    public function handleServiceActivated(ServiceActivated $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new ServiceWelcomeMail($event->service));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: ServiceActivated email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('service.activated', [
            'event_type' => 'service.activated',
            'subject' => 'Service Activated',
            'message' => "Service #{$event->service->id} ({$event->service->domain}) activated for {$event->service->client?->first_name} {$event->service->client?->last_name}",
            'service_id' => $event->service->id,
        ]);
    }

    public function handleServiceSuspended(ServiceSuspended $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new ServiceSuspensionMail($event->service, $event->reason));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: ServiceSuspended email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('service.suspended', [
            'event_type' => 'service.suspended',
            'subject' => 'Service Suspended',
            'message' => "Service #{$event->service->id} ({$event->service->domain}) suspended: {$event->reason}",
            'service_id' => $event->service->id,
        ]);
    }

    public function handleServiceTerminated(ServiceTerminated $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new ServiceTerminationMail($event->service));
            }
        } catch (\Throwable $e) {
            Log::error('SendNotification: ServiceTerminated email failed', ['error' => $e->getMessage()]);
        }

        $this->dispatchNotification('service.terminated', [
            'event_type' => 'service.terminated',
            'subject' => 'Service Terminated',
            'message' => "Service #{$event->service->id} ({$event->service->domain}) terminated for {$event->service->client?->first_name} {$event->service->client?->last_name}",
            'service_id' => $event->service->id,
        ]);
    }

    /**
     * Dispatch to NotificationService for slack/webhook channels.
     * Wrapped in try/catch — failures here must never break email flow.
     */
    private function dispatchNotification(string $eventType, array $data): void
    {
        try {
            app(NotificationService::class)->dispatch($eventType, $data);
        } catch (\Throwable $e) {
            Log::error("NotificationService dispatch failed for {$eventType}", ['error' => $e->getMessage()]);
        }
    }
}
