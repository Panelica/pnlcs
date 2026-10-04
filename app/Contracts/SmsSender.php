<?php

namespace App\Contracts;

/**
 * Sends one text message. An addon for a local SMS provider (NetGSM, İletimerkezi,
 * MessageBird...) binds its implementation in its service provider:
 *
 *     $this->app->bind(\App\Contracts\SmsSender::class, NetgsmSender::class);
 *
 * Phone verification then sends its codes through it when Twilio Verify is not
 * set up, and anything else that needs an SMS can ask the container for it.
 */
interface SmsSender
{
    /** @param  string  $to  E.164, e.g. +905321234567 */
    public function send(string $to, string $message): bool;
}
