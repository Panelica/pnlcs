<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Events\ClientCreated;
use App\Events\OrderPlaced;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;

class SendNotificationListener
{
    public function handleClientCreated(ClientCreated $event): void
    {
        try {
            if ($event->client->email) {
                Mail::to($event->client->email)->queue(new \App\Mail\AccountSignupMail($event->client));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: ClientCreated email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleOrderPlaced(OrderPlaced $event): void
    {
        try {
            $email = $event->order->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\OrderConfirmationMail($event->order));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: OrderPlaced email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleInvoiceCreated(InvoiceCreated $event): void
    {
        try {
            $email = $event->invoice->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\InvoiceCreatedMail($event->invoice));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: InvoiceCreated email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        try {
            $email = $event->invoice->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\InvoicePaidMail($event->invoice, $event->transactionId));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: InvoicePaid email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleTicketOpened(TicketOpened $event): void
    {
        try {
            // Send to client
            if ($event->ticket->email) {
                Mail::to($event->ticket->email)->queue(new \App\Mail\TicketOpenedMail($event->ticket, false));
            }
            // If client opened, also notify admin
            if (!$event->isAdmin) {
                $adminEmail = \App\Models\Setting::get('Email', null);
                if ($adminEmail) {
                    Mail::to($adminEmail)->queue(new \App\Mail\TicketOpenedMail($event->ticket, true));
                }
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: TicketOpened email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleTicketReplied(TicketReplied $event): void
    {
        try {
            if ($event->isStaffReply) {
                // Staff replied, notify client
                if ($event->ticket->email) {
                    Mail::to($event->ticket->email)->queue(new \App\Mail\TicketReplyMail($event->ticket, $event->replyMessage, true));
                }
            } else {
                // Client replied, notify admin
                $adminEmail = \App\Models\Setting::get('Email', null);
                if ($adminEmail) {
                    Mail::to($adminEmail)->queue(new \App\Mail\TicketReplyMail($event->ticket, $event->replyMessage, false));
                }
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: TicketReplied email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleServiceActivated(ServiceActivated $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\ServiceWelcomeMail($event->service));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: ServiceActivated email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleServiceSuspended(ServiceSuspended $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\ServiceSuspensionMail($event->service, $event->reason));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: ServiceSuspended email failed", ['error' => $e->getMessage()]);
        }
    }

    public function handleServiceTerminated(ServiceTerminated $event): void
    {
        try {
            $email = $event->service->client?->email;
            if ($email) {
                Mail::to($email)->queue(new \App\Mail\ServiceTerminationMail($event->service));
            }
        } catch (\Throwable $e) {
            Log::error("SendNotification: ServiceTerminated email failed", ['error' => $e->getMessage()]);
        }
    }
}
