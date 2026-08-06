<?php

namespace App\Services;

use App\Contracts\SslModuleInterface;
use App\Mail\SslCertificateIssuedMail;
use App\Mail\SslConfigurationRequiredMail;
use App\Models\SslOrder;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SslProvisioningService
{
    public function __construct(
        protected ModuleRegistry $registry,
    ) {}

    public function getModuleForOrder(SslOrder $order): ?SslModuleInterface
    {
        if (empty($order->module)) {
            // Try to resolve from product
            $product = $order->service?->product;
            if ($product && $product->ssl_module) {
                $order->update(['module' => $product->ssl_module]);

                return $this->registry->getSslModule($product->ssl_module);
            }

            return null;
        }

        return $this->registry->getSslModule($order->module);
    }

    public function submitConfiguration(SslOrder $order, array $config): array
    {
        // r129-once: configuring submits the order to the certificate
        // authority, which is a purchase. The form will not open once that has
        // happened; the handler behind it checked nothing, so a resubmitted
        // form, a double click or the API endpoint bought the certificate
        // again - another one to pay for, a duplicate order at the authority,
        // and the CSR and contact details of an issued certificate overwritten.
        // A submission that failed leaves the order awaiting configuration, so
        // a genuine retry still goes through.
        if ($order->status !== 'Awaiting Configuration') {
            return ['success' => false, 'message' => __('messages.error.this_certificate_has_already_been_configured')];
        }

        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found')];
        }

        // Update order with configuration
        $order->update([
            'webserver_type' => $config['webserver_type'] ?? null,
            'validation_method' => $config['validation_method'] ?? 'EMAIL',
            'csr' => $config['csr'] ?? null,
            'approver_email' => $config['approver_email'] ?? null,
            'admin_first_name' => $config['admin_first_name'] ?? null,
            'admin_last_name' => $config['admin_last_name'] ?? null,
            'admin_email' => $config['admin_email'] ?? null,
            'admin_phone' => $config['admin_phone'] ?? null,
            'admin_org' => $config['admin_org'] ?? null,
            'admin_address' => $config['admin_address'] ?? null,
            'admin_city' => $config['admin_city'] ?? null,
            'admin_state' => $config['admin_state'] ?? null,
            'admin_zip' => $config['admin_zip'] ?? null,
            'admin_country' => $config['admin_country'] ?? null,
            'domain' => $config['domain'] ?? $order->domain,
            'domains' => $config['domains'] ?? null,
        ]);

        // Submit to CA
        $result = $module->purchaseCertificate($order, $config);

        if ($result['success']) {
            $order->update(['status' => 'Configuration Submitted']);
        }

        return $result;
    }

    public function pollCertificateStatus(SslOrder $order): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        $previousStatus = $order->status;
        $result = $module->getCertificateStatus($order);

        // If certificate was just issued, send notification
        $order->refresh();
        if ($previousStatus !== 'Completed' && $order->status === 'Completed') {
            $this->sendCertificateIssuedEmail($order);
        }

        return $result;
    }

    public function renewCertificate(SslOrder $order): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        return $module->renewCertificate($order);
    }

    public function revokeCertificate(SslOrder $order, string $reason = ''): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        return $module->revokeCertificate($order, $reason);
    }

    public function reissueCertificate(SslOrder $order, string $newCsr): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        return $module->reissueCertificate($order, $newCsr);
    }

    public function resendValidation(SslOrder $order): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        return $module->resendValidationEmail($order);
    }

    public function changeValidation(SslOrder $order, string $method): array
    {
        $module = $this->getModuleForOrder($order);
        if (! $module) {
            return ['success' => false, 'message' => __('messages.error.ssl_module_not_found_short')];
        }

        return $module->changeValidationMethod($order, $method);
    }

    public function downloadCertificate(SslOrder $order): array
    {
        if (! $order->isCompleted()) {
            return ['success' => false, 'message' => __('messages.error.certificate_not_yet_issued')];
        }

        return [
            'success' => true,
            'data' => [
                'cert' => $order->cert,
                'ca_cert' => $order->ca_cert,
                'fullchain' => $order->fullchain,
                'private_key' => $order->private_key,
                'domain' => $order->domain,
            ],
        ];
    }

    public function sendConfigurationRequiredEmail(SslOrder $order): void
    {
        try {
            $client = $order->client;
            if ($client && $client->email) {
                Mail::to($client->email)->send(new SslConfigurationRequiredMail($order));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to send SSL config email for order #{$order->id}: {$e->getMessage()}");
        }
    }

    protected function sendCertificateIssuedEmail(SslOrder $order): void
    {
        try {
            $client = $order->client;
            if ($client && $client->email) {
                Mail::to($client->email)->send(new SslCertificateIssuedMail($order));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to send SSL issued email for order #{$order->id}: {$e->getMessage()}");
        }
    }
}
