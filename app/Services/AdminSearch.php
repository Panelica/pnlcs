<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The admin bar's search: clients, invoices, services, domains, tickets and
 * orders matching what was typed, grouped by kind.
 *
 * The box sent everything to the client list, so an invoice number, a
 * domain, a ticket number or an order number found nothing. Each kind is
 * searched only when the admin may open it - the same permission its page
 * asks for - so the search never shows what a click would refuse.
 */
class AdminSearch
{
    public const PER_KIND = 5;

    public const MIN_LENGTH = 2;

    /** @return list<array{type: string, label: string, items: list<array{title: string, subtitle: string, url: string}>}> */
    public function search(Admin $admin, string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $like = '%'.addcslashes($query, '\\%_').'%';
        $number = ctype_digit(ltrim($query, '#')) ? (int) ltrim($query, '#') : null;

        $kinds = [
            'clients' => ['view_clients', fn () => $this->clients($like, $number)],
            'invoices' => ['view_invoices', fn () => $this->invoices($like, $number)],
            'services' => ['view_services', fn () => $this->services($like, $number)],
            'domains' => ['manage_domains', fn () => $this->domains($like)],
            'tickets' => ['view_tickets', fn () => $this->tickets($like, ltrim($query, '#'))],
            'orders' => ['view_orders', fn () => $this->orders($like, $number)],
        ];

        $groups = [];
        foreach ($kinds as $type => [$permission, $find]) {
            if (! $admin->hasPermission($permission)) {
                continue;
            }
            $items = $find()->values()->all();
            if ($items !== []) {
                $groups[] = ['type' => $type, 'label' => __('admin.search.'.$type), 'items' => $items];
            }
        }

        return $groups;
    }

    private function clients(string $like, ?int $number): Collection
    {
        return Client::query()
            ->where(function (Builder $q) use ($like, $number) {
                $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)->orWhere('email', 'like', $like)
                    ->orWhere('phone_number', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                if ($number) {
                    $q->orWhere('id', $number);
                }
            })
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Client $c) => [
                'title' => '#'.$c->id.' '.$c->display_name,
                'subtitle' => (string) $c->email,
                'url' => route('admin.clients.show', $c),
            ]);
    }

    private function invoices(string $like, ?int $number): Collection
    {
        return Invoice::query()->with('client')
            ->where(function (Builder $q) use ($like, $number) {
                $q->where('invoice_num', 'like', $like);
                if ($number) {
                    $q->orWhere('id', $number);
                }
            })
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Invoice $i) => [
                'title' => __('admin.search.invoice_title', ['num' => $i->invoice_num ?: $i->id]),
                'subtitle' => trim(($i->client?->display_name ?? '').' · '.number_format((float) $i->total, 2).' · '.$i->status, ' ·'),
                'url' => route('admin.invoices.show', $i),
            ]);
    }

    private function services(string $like, ?int $number): Collection
    {
        return Service::query()->with(['client', 'product'])
            ->where(function (Builder $q) use ($like, $number) {
                $q->where('domain', 'like', $like)->orWhere('username', 'like', $like);
                if ($number) {
                    $q->orWhere('id', $number);
                }
            })
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Service $s) => [
                'title' => ($s->domain ?: '#'.$s->id).($s->product ? ' · '.$s->product->name : ''),
                'subtitle' => trim(($s->client?->display_name ?? '').' · '.$s->status, ' ·'),
                'url' => route('admin.services.show', $s),
            ]);
    }

    private function domains(string $like): Collection
    {
        return Domain::query()->with('client')->where('domain', 'like', $like)
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Domain $d) => [
                'title' => (string) $d->domain,
                'subtitle' => trim(($d->client?->display_name ?? '').' · '.$d->status, ' ·'),
                'url' => route('admin.domains.show', $d),
            ]);
    }

    private function tickets(string $like, string $raw): Collection
    {
        return Ticket::query()->with('client')
            ->where(function (Builder $q) use ($like, $raw) {
                $q->where('tid', $raw)->orWhere('tid', 'like', $like)->orWhere('title', 'like', $like);
            })
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Ticket $t) => [
                'title' => '#'.$t->tid.' '.$t->title,
                'subtitle' => trim(($t->client?->display_name ?? $t->name ?? '').' · '.$t->status, ' ·'),
                'url' => route('admin.tickets.show', $t),
            ]);
    }

    private function orders(string $like, ?int $number): Collection
    {
        return Order::query()->with('client')
            ->where(function (Builder $q) use ($like, $number) {
                $q->where('order_num', 'like', $like);
                if ($number) {
                    $q->orWhere('id', $number);
                }
            })
            ->orderByDesc('id')->limit(self::PER_KIND)->get()
            ->map(fn (Order $o) => [
                'title' => __('admin.search.order_title', ['num' => $o->order_num ?: $o->id]),
                'subtitle' => trim(($o->client?->display_name ?? '').' · '.number_format((float) $o->amount, 2).' · '.$o->status, ' ·'),
                'url' => route('admin.orders.show', $o),
            ]);
    }
}
