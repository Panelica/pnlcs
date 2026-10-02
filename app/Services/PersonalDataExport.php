<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use App\Models\Service;
use App\Models\SslOrder;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\UserLogin;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything kept about one customer account, as one JSON document: the
 * copy a customer may ask for under the GDPR (Art. 15 and 20) and KVKK
 * (Art. 11).
 *
 * Each section names the columns it carries. Nothing is dumped with
 * toArray(): a column added later - a password, a key, a module's private
 * data - must not end up in the file because nobody thought of it. Left
 * out on purpose: passwords, service module data, transfer (EPP) codes,
 * certificate keys and CSRs, payment tokens, admin-only notes,
 * admin-only custom fields, and the bodies of sent emails.
 */
class PersonalDataExport
{
    public const FORMAT_VERSION = 1;

    /** @return array<string, mixed> */
    public function build(Client $client): array
    {
        $id = $client->id;

        return [
            'format' => ['name' => 'pnlcs-personal-data', 'version' => self::FORMAT_VERSION],
            'exported_at' => now()->toIso8601String(),
            'provider' => company_name(),
            'account' => $this->pick($client, [
                'id', 'first_name', 'last_name', 'company_name', 'client_type', 'email', 'billing_email',
                'address1', 'address2', 'city', 'state', 'postcode', 'country', 'phone_prefix', 'phone_number',
                'tax_id', 'tax_office', 'national_id', 'language', 'status', 'credit', 'ip_address', 'created_at',
            ]) + ['custom_fields' => $this->customFields($client)],
            'logins' => $client->users()->get()->map(fn ($u) => $this->pick($u, [
                'first_name', 'last_name', 'email', 'email_verified_at', 'last_login', 'last_login_ip', 'created_at',
            ]) + [
                'owner' => (bool) $u->pivot->owner,
                // The sign-in history kept for this login (not the device
                // token's hash, which only identifies a browser to us).
                'sign_ins' => UserLogin::where('user_id', $u->id)->orderByDesc('id')->get()->map(fn ($l) => [
                    'at' => $l->created_at?->format(DATE_ATOM),
                    'successful' => (bool) $l->successful,
                    'method' => $l->method,
                    'ip_address' => $l->ip_address,
                    'device' => LoginRecorder::describe($l->user_agent),
                ])->all(),
            ])->all(),
            'contacts' => $client->contacts()->get()->map(fn ($c) => $this->pick($c, [
                'first_name', 'last_name', 'email', 'company_name', 'address1', 'address2', 'city', 'state',
                'postcode', 'country', 'phone_number', 'created_at',
            ]))->all(),
            'services' => Service::where('client_id', $id)->with('product')->get()->map(fn ($s) => $this->pick($s, [
                'id', 'domain', 'status', 'billing_cycle', 'amount', 'registration_date', 'next_due_date', 'username',
                'termination_date',
            ]) + ['product' => $s->product?->name])->all(),
            'domains' => Domain::where('client_id', $id)->get()->map(fn ($d) => $this->pick($d, [
                'domain', 'type', 'status', 'registrar', 'registration_date', 'expiry_date', 'next_due_date',
                'recurring_amount', 'nameservers', 'id_protection',
            ]))->all(),
            'ssl_certificates' => SslOrder::where('client_id', $id)->get()->map(fn ($o) => $this->pick($o, [
                'domain', 'cert_type', 'status', 'order_date', 'crt_expires', 'admin_first_name', 'admin_last_name',
                'admin_email', 'admin_phone', 'admin_org', 'admin_address', 'admin_city', 'admin_state', 'admin_zip',
                'admin_country',
            ]))->all(),
            'orders' => Order::where('client_id', $id)->get()->map(fn ($o) => $this->pick($o, [
                'order_num', 'date', 'amount', 'status', 'payment_method', 'promo_code', 'ip_address',
                'terms_accepted_at', 'terms_version', 'terms_ip',
            ]))->all(),
            'invoices' => Invoice::where('client_id', $id)->with('items')->get()->map(fn ($i) => $this->pick($i, [
                'invoice_num', 'date', 'due_date', 'date_paid', 'status', 'subtotal', 'credit', 'tax', 'total',
                'payment_method', 'buyer_first_name', 'buyer_last_name', 'buyer_company_name', 'buyer_email',
                'buyer_address1', 'buyer_address2', 'buyer_city', 'buyer_state', 'buyer_postcode', 'buyer_country',
                'buyer_tax_id', 'buyer_tax_office', 'buyer_national_id',
            ]) + ['items' => $i->items->map(fn ($it) => $this->pick($it, ['description', 'qty', 'amount']))->all()])->all(),
            'transactions' => Transaction::where('client_id', $id)->get()->map(fn ($t) => $this->pick($t, [
                'date', 'gateway', 'description', 'amount_in', 'amount_out', 'fees', 'transaction_id', 'invoice_id',
            ]))->all(),
            'credit_history' => Credit::where('client_id', $id)->get()->map(fn ($c) => $this->pick($c, [
                'date', 'description', 'amount',
            ]))->all(),
            'quotes' => Quote::where('client_id', $id)->with('items')->get()->map(fn ($q) => $this->pick($q, [
                'id', 'subject', 'date', 'valid_until', 'total', 'status', 'customer_notes',
            ]) + ['items' => $q->items->map(fn ($it) => $this->pick($it, ['description', 'quantity', 'unit_price']))->all()])->all(),
            'tickets' => Ticket::where('client_id', $id)->with('replies')->get()->map(fn ($t) => $this->pick($t, [
                'tid', 'title', 'status', 'priority', 'name', 'email', 'message', 'created_at',
            ]) + ['replies' => $t->replies->map(fn ($r) => [
                'from' => filled($r->admin) ? 'staff' : 'customer',
                'message' => $r->message,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all()])->all(),
            // Which emails were sent, not their bodies: a welcome email carries
            // the service's password, and the bodies are in the mailbox they
            // went to.
            'emails_sent' => Email::where('client_id', $id)->orderBy('date')->get()->map(fn ($e) => $this->pick($e, [
                'date', 'to', 'subject',
            ]))->all(),
        ];
    }

    /** @return array<string, string> custom fields the customer can see, by name */
    private function customFields(Client $client): array
    {
        $fields = CustomField::where('type', 'client')->where('admin_only', false)->get()->keyBy('id');
        if ($fields->isEmpty()) {
            return [];
        }

        return CustomFieldValue::whereIn('field_id', $fields->keys())->where('rel_id', $client->id)->get()
            ->mapWithKeys(fn ($v) => [$fields[$v->field_id]->field_name => (string) $v->value])->all();
    }

    /** @param list<string> $columns */
    private function pick(Model $model, array $columns): array
    {
        $out = [];
        foreach ($columns as $column) {
            $value = $model->getAttribute($column);
            $out[$column] = $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
        }

        return $out;
    }
}
