<?php

namespace App\Mail;

use App\Models\Service;
use App\Mail\Concerns\LocalizesToRecipient;
use App\Models\Setting;
use App\Services\ProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ServiceWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use LocalizesToRecipient;

    public function __construct(
        public Service $service
    ) {
        $this->localizeTo($this->service);
    }

    public function envelope(): Envelope
    {
        $domain = $this->service->domain ?? $this->service->name ?? $this->service->id;

        return new Envelope(subject: "Your Service {$domain} is Ready!");
    }

    public function content(): Content
    {
        $server = $this->service->server;
        $host = $server?->hostname ?: $server?->ip_address;

        // Only claimed when the product actually grants it. A managed product
        // carries res_ssh_level (none/jailed/full); anything else is left out
        // rather than promising an access the account does not have.
        $sshLevel = $this->service->product?->config_options['res_ssh_level'] ?? null;
        if (! in_array($sshLevel, ['jailed', 'full'], true)) {
            $sshLevel = null;
        }

        return new Content(
            view: 'emails.service-welcome',
            with: [
                'service' => $this->service,
                'companyName' => company_name(),
                // Access details. The control panel URL is the reliable front
                // door (same host:port this system talks to); the password is
                // the provisioning password, decrypted by the model cast.
                'panelUrl' => $host && $server?->port ? "https://{$host}:{$server->port}" : null,
                'accessHost' => $host,
                'password' => $this->service->password,
                'sshLevel' => $sshLevel,
                'nameservers' => array_values(array_filter([
                    $server?->nameserver1, $server?->nameserver2,
                    $server?->nameserver3, $server?->nameserver4,
                ])),
                // Mail client settings, only for a service that actually has
                // mailboxes. This is the first thing customers open a ticket
                // about after buying, so it goes in the welcome mail rather
                // than waiting to be asked for.
                'mailHost' => $this->mailHost(),
            ],
        );
    }

    /**
     * Hostname the customer's mail client connects to, or null when this
     * service has no mailboxes (a domain-only or unprovisioned service).
     */
    private function mailHost(): ?string
    {
        try {
            $module = app(ProvisioningService::class)->resolveModule($this->service);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $module || ! method_exists($module, 'hostingFeatures') || ! method_exists($module, 'mailHostname')) {
            return null;
        }
        if (! in_array('emails', $module->hostingFeatures($this->service), true)) {
            return null;
        }

        return $module->mailHostname($this->service);
    }
}
