<?php

namespace App\Mail;

use App\Models\Service;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ServiceWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Service $service
    ) {}

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
            ],
        );
    }
}
