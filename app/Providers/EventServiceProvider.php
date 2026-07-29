<?php

namespace App\Providers;

use App\Events\ClientCreated;
use App\Events\InvoiceCreated;
use App\Events\InvoicePaid;
use App\Events\OrderPlaced;
use App\Events\ServiceActivated;
use App\Events\ServiceSuspended;
use App\Events\ServiceTerminated;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Listeners\ApplyEmailTemplate;
use App\Listeners\ApplyUpgradeListener;
use App\Listeners\AutoAcceptOrderListener;
use App\Listeners\LogActivityListener;
use App\Listeners\LogSentEmailListener;
use App\Listeners\RecordCronHeartbeat;
use App\Listeners\RenewOnPaymentListener;
use App\Listeners\SendNotificationListener;
use App\Listeners\SuppressMailWhenDisabled;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|array>>
     */
    protected $listen = [
        // Must stay first: cancels the send when mail is disabled in settings.
        MessageSending::class => [
            SuppressMailWhenDisabled::class,
            ApplyEmailTemplate::class,
        ],
        ClientCreated::class => [
            [SendNotificationListener::class, 'handleClientCreated'],
            LogActivityListener::class,
        ],
        OrderPlaced::class => [
            [SendNotificationListener::class, 'handleOrderPlaced'],
            LogActivityListener::class,
        ],
        InvoiceCreated::class => [
            [SendNotificationListener::class, 'handleInvoiceCreated'],
            LogActivityListener::class,
        ],
        InvoicePaid::class => [
            [SendNotificationListener::class, 'handleInvoicePaid'],
            [AutoAcceptOrderListener::class, 'handleInvoicePaid'],
            [RenewOnPaymentListener::class, 'handleInvoicePaid'],
            [ApplyUpgradeListener::class, 'handleInvoicePaid'],
            LogActivityListener::class,
        ],
        TicketOpened::class => [
            [SendNotificationListener::class, 'handleTicketOpened'],
            LogActivityListener::class,
        ],
        TicketReplied::class => [
            [SendNotificationListener::class, 'handleTicketReplied'],
            LogActivityListener::class,
        ],
        ServiceActivated::class => [
            [SendNotificationListener::class, 'handleServiceActivated'],
            LogActivityListener::class,
        ],
        ServiceSuspended::class => [
            [SendNotificationListener::class, 'handleServiceSuspended'],
            LogActivityListener::class,
        ],
        ServiceTerminated::class => [
            [SendNotificationListener::class, 'handleServiceTerminated'],
            LogActivityListener::class,
        ],
        MessageSent::class => [
            LogSentEmailListener::class,
        ],
        ScheduledTaskFinished::class => [
            RecordCronHeartbeat::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
