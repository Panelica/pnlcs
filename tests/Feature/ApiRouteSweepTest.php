<?php

use App\Models\Announcement;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TodoItem;
use App\Models\Transaction;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * Every API endpoint, called three ways, must answer - never crash.
 *
 * The API is about two hundred endpoints, most of them reached by scripts and
 * by the MCP server rather than by a person who would notice a stack trace. A
 * 500 there is an integration that silently stops working. So every route is
 * called:
 *
 *   empty    - no parameters at all: must be a 4xx (or a 2xx for a list)
 *   missing  - every id pointing at a record that does not exist: must be 404/422
 *   real     - every id pointing at a real record: must not be a 500
 *
 * No test here may reach the network: a module or registrar that tries is
 * reported like a crash, because on a real install it would be one.
 */

/** Fields that must never appear, filled, in an API response. */
const SWEEP_SECRET_KEYS = ['password', 'secret', 'remote_token', 'access_hash', 'second_factor_secret', 'backup_codes', 'epp_code', 'gateway_customer_id', 'remember_token', 'api_secret', 'import_password', 'private_key'];

/** Endpoints whose whole purpose is to hand one of those to the caller, once. */
const SWEEP_SECRET_ALLOWED = ['/api/v1/createoauthcredential', '/api/v1/domainrequestepp'];

function array_walk_recursive_keys(array $data, callable $visit): void
{
    foreach ($data as $key => $value) {
        $visit($key, $value);
        if (is_array($value)) {
            array_walk_recursive_keys($value, $visit);
        }
    }
}

function sweepRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        // The API is the routes in the "api" middleware group. The company
        // lookup behind the checkout form also lives under /api/ but is a web
        // route with its own 30-a-minute limit; it is not part of this API.
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/') && in_array('api', $r->gatherMiddleware(), true))
        ->map(fn ($r) => [
            'method' => in_array('POST', $r->methods(), true) ? 'POST' : 'GET',
            'uri' => '/'.$r->uri(),
        ])
        ->unique(fn ($r) => $r['method'].$r['uri'])
        ->values()
        ->all();
}

function sweepIdParams(array $ids): array
{
    return [
        'clientid' => $ids['client'], 'userid' => $ids['client'],
        'invoiceid' => $ids['invoice'], 'serviceid' => $ids['service'],
        'domainid' => $ids['domain'], 'ticketid' => $ids['ticket'],
        'orderid' => $ids['order'], 'quoteid' => $ids['quote'],
        'projectid' => $ids['project'], 'taskid' => $ids['task'],
        'contactid' => $ids['contact'], 'announcementid' => $ids['announcement'],
        'transactionid' => $ids['transaction'], 'replyid' => $ids['reply'],
        'noteid' => $ids['reply'], 'itemid' => $ids['todo'],
        'credentialid' => $ids['credential'], 'id' => $ids['client'],
        'mergeid' => $ids['ticket'], 'packageid' => $ids['product'], 'pid' => $ids['product'],
        'deptid' => $ids['department'], 'order_id' => $ids['sslorder'], 'client_id' => $ids['client'],
        'adminid' => $ids['admin'],
    ];
}

/**
 * Every field a caller could send, filled with a value of the wrong kind: a
 * word where a date, a number, an id, a boolean or an array belongs. An
 * endpoint that writes a field without checking it answers this with a 500.
 */
function sweepGarbageParams(array $ids): array
{
    $bad = 'not-a-valid-value';

    return sweepIdParams($ids) + [
        'status' => $bad, 'priority' => $bad, 'type' => $bad, 'flag' => $bad,
        'due_date' => $bad, 'duedate' => $bad, 'date' => $bad, 'valid_until' => $bad,
        'next_due_date' => $bad, 'expiry_date' => $bad, 'startdate' => $bad,
        'amount' => $bad, 'amountin' => $bad, 'amountout' => $bad, 'years' => $bad,
        'register_price' => $bad, 'renew_price' => $bad, 'transfer_price' => $bad,
        'completed' => $bad, 'published' => $bad, 'enabled' => $bad, 'sticky' => $bad,
        'lockstatus' => $bad, 'idprotect' => $bad, 'active' => $bad,
        'billing_cycle' => $bad, 'billingcycle' => $bad, 'paymentmethod' => $bad, 'payment_method' => $bad,
        'registration_date' => $bad, 'regdate' => $bad, 'expirydate' => $bad, 'nextduedate' => $bad, 'validuntil' => $bad,
        'dns_management' => $bad, 'email_forwarding' => $bad, 'id_protection' => $bad, 'set_as_default' => $bad,
        'moduleType' => $bad, 'moduleName' => $bad, 'sorting' => $bad, 'subject' => str_repeat('s', 300),
        'items' => $bad, 'priceoverride' => $bad, 'ip' => $bad, 'email' => $bad,
        'domain' => $bad, 'extension' => $bad, 'registrar' => $bad, 'module' => $bad,
        'limitnum' => $bad, 'limitstart' => $bad, 'orderby' => $bad, 'order' => $bad,
        'title' => str_repeat('x', 300), 'message' => '', 'note' => '', 'description' => '',
        'firstname' => '', 'lastname' => '', 'password' => 'x', 'password2' => 'x',
        'setting' => $bad, 'value' => $bad, 'attachmentindex' => $bad,
    ];
}

test('no API endpoint answers with a server error', function () {
    Http::preventStrayRequests();
    Mail::fake();

    // Thousands of calls from one credential: the API's rate limit (300 a
    // minute) answered everything after the first few hundred with 429, and
    // those were counted as fine - most of the sweep tested nothing. The limit
    // has its own test (ApiRateLimitTest); here it is lifted, and a 429 that
    // still appears fails the sweep below instead of hiding in it.
    RateLimiter::for('api', fn () => Limit::none());

    $cred = ApiCredential::factory()->create();
    $headers = [
        'X-API-Key' => $cred->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
        'Accept' => 'application/json',
    ];

    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id]);
    $domain = Domain::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    $ticket = Ticket::factory()->create(['client_id' => $client->id]);
    $project = Project::factory()->create(['client_id' => $client->id]);

    $real = [
        'client' => $client->id,
        'invoice' => $invoice->id,
        'service' => $service->id,
        'domain' => $domain->id,
        'ticket' => $ticket->id,
        'order' => Order::factory()->create(['client_id' => $client->id])->id,
        'quote' => Quote::factory()->create(['client_id' => $client->id])->id,
        'project' => $project->id,
        'task' => ProjectTask::factory()->create(['project_id' => $project->id])->id,
        'contact' => Contact::factory()->create(['client_id' => $client->id])->id,
        'announcement' => Announcement::factory()->create()->id,
        'transaction' => Transaction::factory()->create(['client_id' => $client->id, 'invoice_id' => $invoice->id])->id,
        'reply' => TicketReply::factory()->create(['ticket_id' => $ticket->id])->id,
        'todo' => TodoItem::factory()->create()->id,
        // Not the sweep's own key: deleteoauthcredential used to be handed it,
        // deleted it, and every route after that answered 401 - untested, and
        // counted as fine.
        'credential' => ApiCredential::factory()->create()->id,
        'product' => $service->product_id,
        'department' => $ticket->department_id ?? \App\Models\TicketDepartment::factory()->create()->id,
        'sslorder' => \App\Models\SslOrder::create(['client_id' => $client->id, 'module' => 'gogetssl', 'status' => 'Awaiting Configuration', 'private_key' => '-----BEGIN PRIVATE KEY----- sweep'])->id,
        'admin' => $cred->admin_id,
    ];
    // Every secret the records can hold, filled in, so a response that
    // carries one is caught - an empty column leaks nothing and proves nothing.
    $domain->forceFill(['epp_code' => 'EPP-SWEEP-SECRET'])->save();
    $service->forceFill(['password' => 'service-sweep-secret'])->save();
    Contact::whereKey($real['contact'])->update(['password' => bcrypt('contact-sweep-secret')]);
    \App\Models\TicketDepartment::whereKey($real['department'])->first()?->forceFill(['import_password' => 'import-sweep-secret'])->save();
    \App\Models\PaymentMethod::create(['client_id' => $client->id, 'gateway_name' => 'stripe', 'payment_type' => 'card', 'remote_token' => 'pm_sweep_secret', 'gateway_customer_id' => 'cus_sweep_secret', 'last_four' => '4242']);

    $missing = array_map(fn () => 987654321, $real);

    $failures = [];
    $notImplemented = [];

    foreach (sweepRoutes() as $route) {
        // The garbage run sends ONE bad field at a time, on top of real ids.
        // All of them at once stopped at the first field an endpoint does
        // check, and never reached the one it does not.
        $valid = sweepIdParams($real);
        $modes = ['empty' => [], 'missing' => sweepIdParams($missing), 'real' => $valid];
        if ($route['method'] === 'POST') {
            foreach (array_diff_key(sweepGarbageParams($real), $valid) as $field => $value) {
                $modes['bad:'.$field] = [$field => $value] + $valid;
            }
        }

        foreach ($modes as $mode => $params) {
            $response = $route['method'] === 'POST'
                ? $this->withHeaders($headers)->post($route['uri'], $params)
                : $this->withHeaders($headers)->get($route['uri'].'?'.http_build_query($params));

            $status = $response->status();
            // 501 is the honest answer of an endpoint that is not implemented;
            // it is listed separately, not treated as a crash.
            if ($status === 501) {
                $notImplemented[$route['method'].' '.$route['uri']] = true;

                continue;
            }
            // 401 means the sweep itself stopped being able to call anything;
            // every route after it would pass without being tested.
            $bad = $status >= 500 || $status === 429 || $status === 401
                || ($mode === 'missing' && $status === 200 && $route['method'] === 'POST' && ($response->json('result') === 'success')
                    // These create something new and take no id, so a success
                    // with made-up ids is the right answer.
                    && ! in_array($route['uri'], ['/api/v1/createoauthcredential'], true));

            // Nothing that signs in or moves a domain may leave in a response:
            // a model that forgets to hide a column hands it to every caller
            // allowed to list that model.
            if ($status < 400) {
                $leaks = [];
                array_walk_recursive_keys($response->json() ?? [], function ($key, $value) use (&$leaks) {
                    if (in_array(strtolower((string) $key), SWEEP_SECRET_KEYS, true) && $value !== null && $value !== '' && $value !== []) {
                        $leaks[] = $key;
                    }
                });
                if ($leaks && ! in_array($route['uri'], SWEEP_SECRET_ALLOWED, true)) {
                    $failures[] = sprintf('%-4s %-40s %-22s LEAK %s', $route['method'], $route['uri'], $mode, implode(',', array_unique($leaks)));
                }
            }

            if ($bad) {
                $why = $response->exception ? class_basename($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 160) : mb_substr((string) $response->getContent(), 0, 160);
                $failures[] = sprintf('%-4s %-40s %-22s %d  %s', $route['method'], $route['uri'], $mode, $status, $why);
            }

            // Each call must start from a clean guard: the middleware logs the
            // credential's owner in for the request.
            auth('admin')->logout();
        }
    }

    // A value of the wrong kind that did not crash anything may still have
    // been written. Every text column is searched for it: where it turns up,
    // an endpoint stored a status, a date or a type it never checked.
    $db = \DB::getDatabaseName();
    $columns = \DB::select(
        "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ('varchar','char','text','mediumtext','longtext','enum')",
        [$db]
    );
    foreach ($columns as $col) {
        $hits = \DB::table($col->t)->where($col->c, 'not-a-valid-value')->count();
        if ($hits > 0) {
            $failures[] = sprintf('STORED  %s.%s  (%d row(s) hold the unchecked value)', $col->t, $col->c, $hits);
        }
    }

    if ($failures) {
        fwrite(STDERR, "\n".implode("\n", $failures)."\n");
    }
    if (getenv('SWEEP_LIST_501')) {
        fwrite(STDERR, "\nNOT IMPLEMENTED (501):\n".implode("\n", array_keys($notImplemented))."\n");
    }

    expect($failures)->toBe([]);
});
