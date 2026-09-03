<?php

namespace App\Services;

use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\SslOrder;

/**
 * The email templates screen edits 19 templates with merge fields, a disable
 * switch, a from name and address, and a copy-to address — and not one of the
 * 23 mailables read any of it. An operator could rewrite the invoice email,
 * switch off the suspension notice or add an accounts address to every copy,
 * save, and nothing changed.
 *
 * This maps a mailable to its template and fills the merge fields in.
 */
class EmailTemplateService
{
    /**
     * Mailable class → template name, as seeded.
     *
     * Deliberately explicit: guessing from the class name would silently attach
     * the wrong template the first time somebody renames one.
     *
     * BulkMassMail is deliberately absent: it carries the subject and body the
     * operator has just written, and a template would overwrite both.
     */
    private const MAP = [
        'AccountSignupMail' => 'Account Signup Email',
        'AffiliateWelcomeMail' => 'Affiliate Welcome Email',
        'CancellationConfirmMail' => 'Cancellation Confirmation',
        'DomainRegistrationMail' => 'Domain Registration Confirmation',
        'DomainRenewalReminderMail' => 'Domain Renewal Reminder',
        'InvoiceCreatedMail' => 'Invoice Created',
        'InvoiceOverdueMail' => 'Invoice Overdue',
        'InvoicePaidMail' => 'Invoice Payment Confirmation',
        'OrderConfirmationMail' => 'Order Confirmation',
        'PasswordResetMail' => 'Password Reset Confirmation',
        'PaymentReminderMail' => 'Invoice Reminder',
        'ServiceSuspensionMail' => 'Service Suspension',
        'ServiceTerminationMail' => 'Service Termination',
        'ServiceUnsuspensionMail' => 'Service Unsuspension',
        'ServiceWelcomeMail' => 'Service Welcome Email',
        'CreditCardExpiryMail' => 'Credit Card Expiry Notice',
        'LoginEmailChangedMail' => 'Login Email Changed',
        'PaymentNotificationRejectedMail' => 'Payment Notification Rejected',
        'SslCertificateExpiringMail' => 'SSL Certificate Expiring',
        'SslCertificateIssuedMail' => 'SSL Certificate Issued',
        'ContainerAccessDetailsMail' => 'App Connection Details',
        'SslConfigurationRequiredMail' => 'SSL Configuration Required',
        'TicketOpenedMail' => 'Support Ticket Opened',
        'TicketReplyMail' => 'Support Ticket Reply',
    ];

    /**
     * The mailable arrives as a class name: Laravel puts the string, not the
     * instance, into the event data under __laravel_mailable.
     *
     * Templates are stored per language; the copy for the recipient's language
     * is preferred, falling back to English (the canonical set) when a language
     * has no row or no translation yet.
     */
    public function forMailable(object|string $mailable, ?string $locale = null): ?EmailTemplate
    {
        $short = class_basename($mailable);

        if (! isset(self::MAP[$short])) {
            return null;
        }

        $name = self::MAP[$short];

        try {
            $query = EmailTemplate::where('name', $name);

            if ($locale !== null && $locale !== '' && $locale !== 'en') {
                $template = (clone $query)->where('language', $locale)->first();
                if (! $template) {
                    $template = (clone $query)->where('language', 'en')->first();
                }

                return $template;
            }

            return $query->where('language', 'en')->first();
        } catch (\Throwable) {
            // Templates unreadable (installer, broken database) — never let this
            // stand between a customer and their email.
            return null;
        }
    }

    /** The client an email is about, taken from whatever the mailable carries. */
    public function clientFrom(array $data): ?Client
    {
        foreach (['invoice', 'service', 'order', 'domain', 'ticket', 'client'] as $property) {
            $model = $data[$property] ?? null;

            if (! is_object($model)) {
                continue;
            }

            if ($property === 'client') {
                return $model;
            }

            if (isset($model->client) && $model->client) {
                return $model->client;
            }
        }

        return null;
    }

    /** Replace {merge_field} with what the mailable is carrying. */
    public function merge(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * The values a template can refer to, taken from the view data the
     * mailable carries — the same models it hands to its Blade view.
     *
     * @return array<string, string>
     */
    public function varsFor(array $data): array
    {
        $vars = ['CompanyName' => $this->companyName()];

        // The connection-details email carries no model at all: an app name,
        // a link, and label => value pairs. Flattened here so a template can
        // say {app_name}, {app_url} and {app_details}.
        if (isset($data['appName']) && is_string($data['appName'])) {
            $vars['app_name'] = $data['appName'];
        }
        if (! empty($data['accessUrl']) && is_string($data['accessUrl'])) {
            $vars['app_url'] = $data['accessUrl'];
        }
        if (isset($data['items']) && is_array($data['items']) && $data['items'] !== []) {
            $lines = [];
            foreach ($data['items'] as $label => $value) {
                if (is_scalar($value)) {
                    $lines[] = $label.': '.$value;
                }
            }
            $vars['app_details'] = implode("\n", $lines);
        }

        $client = null;

        foreach (['invoice', 'service', 'order', 'domain', 'ticket', 'client'] as $property) {
            $model = $data[$property] ?? null;

            if (! is_object($model)) {
                continue;
            }

            match ($property) {
                'invoice' => $vars += [
                    'invoice_num' => $model->invoice_num ?? $model->id,
                    'invoice_total' => number_format((float) $model->total, 2),
                    'invoice_due_date' => $model->due_date?->format(date_fmt()) ?? '',
                ],
                'service' => $vars += [
                    'service_domain' => $model->domain ?? '',
                    // The credentials the module wrote onto the service when the
                    // account was built. cPanel and Plesk welcome mail carries
                    // them too; a welcome mail without them tells the customer a
                    // service exists that they cannot open.
                    'service_username' => $model->username ?? '',
                    'service_password' => (string) ($model->password ?? ''),
                    'service_product' => $model->product->name ?? '',
                    // The templates say {product_name}.
                    'product_name' => $model->product->name ?? '',
                ],
                // An SSL order is not a shop order: it has no order number and
                // no total, and reading those off it would put "#" and "0.00"
                // into the customer's subject line.
                'order' => $vars += $model instanceof SslOrder
                    ? [
                        'ssl_domain' => $model->domain ?: 'Order #'.$model->id,
                        'ssl_status' => (string) ($model->status ?? ''),
                    ]
                    : [
                        'order_num' => $model->order_num ?? $model->id,
                        'order_total' => number_format((float) $model->amount, 2),
                    ],
                'domain' => $vars += [
                    'domain_name' => $model->domain ?? '',
                    // The templates say {domain}, and every domain email went
                    // out with the braces showing until they agreed.
                    'domain' => $model->domain ?? '',
                    'expiry_date' => $model->expiry_date?->format(date_fmt()) ?? '',
                    'reg_date' => $model->registration_date?->format(date_fmt()) ?? '',
                ],
                'ticket' => $vars += [
                    'ticket_tid' => $model->tid ?? '',
                    // The templates say {ticket_id}.
                    'ticket_id' => $model->tid ?? '',
                    'ticket_subject' => $model->title ?? '',
                    'ticket_dept' => $model->department->name ?? '',
                    'ticket_message' => $model->message ?? '',
                ],
                default => null,
            };

            $client ??= $model->client ?? ($property === 'client' ? $model : null);
        }

        // Nothing the password reset carries is a model - it is a link and an
        // address - so the customer asking for their account back was greeted
        // as "{client_name}". The address it is going to identifies them.
        if (! $client && is_string($data['email'] ?? null) && $data['email'] !== '') {
            try {
                $client = Client::where('email', $data['email'])->first();
            } catch (\Throwable) {
                $client = null;
            }
        }

        if ($client) {
            $vars['client_name'] = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
            $vars['client_email'] = $client->email ?? '';
        }

        // What the mailable carries alongside its models. Without these an
        // operator who customised a body sent "{reset_url}" to a customer who
        // had asked to change their password.
        if (is_object($data['affiliate'] ?? null)) {
            $vars['affiliate_link'] = url('/?ref='.$data['affiliate']->id);
        }

        foreach ([
            'resetUrl' => 'reset_url',
            'replyMessage' => 'ticket_reply',
            'reason' => 'suspend_reason',
            // The address change warning is about two addresses and carries
            // nothing else; the counts below are what their subjects are made
            // of - "expiring in 14 days" reads as "expiring in {} days"
            // without them.
            'previousEmail' => 'previous_email',
            'newEmail' => 'new_email',
            'daysRemaining' => 'days_remaining',
            'daysOverdue' => 'days_overdue',
            'daysUntilExpiry' => 'days_until_expiry',
        ] as $property => $name) {
            $value = $data[$property] ?? null;

            if (is_scalar($value) && ! is_bool($value) && (string) $value !== '') {
                $vars[$name] = (string) $value;
            }
        }

        // {whmcs_url} is what the seeded templates call the client area. The
        // second name is for templates written from here on.
        $vars['client_area_url'] = url('/client');
        $vars['whmcs_url'] = $vars['client_area_url'];

        return $vars;
    }

    private function companyName(): string
    {
        try {
            return company_name();
        } catch (\Throwable) {
            return (string) config('app.name', 'PNLCS');
        }
    }
}
