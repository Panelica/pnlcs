<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Invoice;
use App\Support\ApiReference;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/*
 * The API reference (config/api_docs.php, the admin screen, docs/api/) held to
 * the code that answers the calls.
 *
 * The reference screen used to describe a single address taking an "action"
 * parameter (a 404), called a GET-only list with POST (a 405), sent invoice
 * lines in a shape the code ignored, and marked a password parameter by the
 * wrong name. Every integrator who followed it started with an error. These
 * tests call what the reference says to call.
 */

function referenceHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
        'Accept' => 'application/json',
    ];
}

function callDocumented(array $endpoint, array $headers, array $params)
{
    $uri = '/api/v1/'.$endpoint['action'];

    return $endpoint['method'] === 'POST'
        ? test()->withHeaders($headers)->post($uri, $params)
        : test()->withHeaders($headers)->get($uri.($params ? '?'.http_build_query($params) : ''));
}

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    RateLimiter::for('api', fn () => Limit::none());
});

test('every routed endpoint has a reference entry, a section and an English description', function () {
    $english = require lang_path('en/admin.php');
    $problems = [];

    foreach (ApiReference::endpoints() as $action => $endpoint) {
        if ($endpoint['spec'] === []) {
            $problems[] = "{$action}: no entry in config/api_docs.php";
        } elseif (! isset(ApiReference::SECTIONS[$endpoint['spec']['section'] ?? ''])) {
            $problems[] = "{$action}: no valid section";
        }
        if (! isset($english['api_docs.desc_'.$action])) {
            $problems[] = "{$action}: no api_docs.desc_{$action} in lang/en/admin.php";
        }
    }

    expect($problems)->toBe([]);
});

test('the reference pages on disk are what the generator writes', function () {
    $this->artisan('pnlcs:api-docs', ['--check' => true])->assertExitCode(0);
});

test('a call documented as needing nothing succeeds without parameters', function () {
    $headers = referenceHeaders();
    $wrong = [];

    foreach (ApiReference::endpoints() as $action => $endpoint) {
        $spec = $endpoint['spec'];
        $required = array_filter(ApiReference::params($spec), fn ($p) => $p['required']);
        if (isset($spec['unavailable']) || $required !== [] || isset($spec['one_of'])) {
            continue;
        }

        $response = callDocumented($endpoint, $headers, []);
        if ($response->json('result') !== 'success') {
            $wrong[] = "{$action}: {$response->status()} ".json_encode($response->json('message') ?? $response->json());
        }
    }

    expect($wrong)->toBe([]);
});

test('a call documented as needing parameters refuses to run without them', function () {
    $headers = referenceHeaders();
    $wrong = [];

    foreach (ApiReference::endpoints() as $action => $endpoint) {
        $spec = $endpoint['spec'];
        $required = array_filter(ApiReference::params($spec), fn ($p) => $p['required']);
        if (isset($spec['unavailable']) || ($required === [] && ! isset($spec['one_of']))) {
            continue;
        }

        $response = callDocumented($endpoint, $headers, []);
        if ($response->status() < 400 || $response->status() >= 500) {
            $wrong[] = "{$action}: answered {$response->status()} with nothing sent";
        }
    }

    expect($wrong)->toBe([]);
});

test('the calls documented as unavailable refuse, with the status the reference gives', function () {
    $headers = referenceHeaders();
    $wrong = [];

    foreach (ApiReference::endpoints() as $action => $endpoint) {
        $reason = $endpoint['spec']['unavailable'] ?? null;
        if ($reason === null) {
            continue;
        }

        $expected = str_contains($reason, '403') ? 403 : 501;
        $status = callDocumented($endpoint, $headers, ['clientid' => 1])->status();
        if ($status !== $expected) {
            $wrong[] = "{$action}: {$status}, documented {$expected}";
        }
    }

    expect($wrong)->toBe([]);
});

test('every example in the reference reaches its endpoint with the documented method', function () {
    $headers = referenceHeaders();
    $wrong = [];

    foreach (ApiReference::endpoints() as $action => $endpoint) {
        $response = callDocumented($endpoint, $headers, ApiReference::example($endpoint['spec']));

        // A record the example names may not exist here, which is a 404 or a
        // 422 from the endpoint itself - in the API's own shape, with a
        // result field. A wrong address or method answers without one.
        if (in_array($response->status(), [405, 429, 500], true) || $response->json('result') === null) {
            $wrong[] = "{$action}: {$response->status()}";
        }
    }

    expect($wrong)->toBe([]);
});

test('the admin screen examples are calls that work', function () {
    $headers = referenceHeaders();
    $client = Client::factory()->create();

    // Listing clients: GET, credential in the headers.
    $this->withHeaders($headers)->get('/api/v1/getclients?limitnum=25')
        ->assertOk()
        ->assertJsonPath('result', 'success')
        ->assertJsonStructure(['totalresults', 'startnumber', 'numreturned', 'data']);

    // Creating an invoice: POST, lines as items[n][...].
    $response = $this->withHeaders($headers)->post('/api/v1/createinvoice', [
        'userid' => $client->id,
        'duedate' => '2026-01-15',
        'items' => [['description' => 'Hosting - January 2026', 'amount' => 29.99]],
        'paymentmethod' => 'banktransfer',
    ])->assertOk()->assertJsonPath('result', 'success');

    expect((float) Invoice::find($response->json('invoiceid'))->total)->toBeGreaterThan(0.0);

    // And the screen no longer sends anyone to an address that is not there.
    $html = $this->actingAs(\App\Models\Admin::factory()->create(), 'admin')
        ->get(route('admin.api-docs'))->assertOk()->getContent();

    expect($html)->not->toContain('action=getclients')
        ->and($html)->not->toContain("'action'")
        ->and($html)->toContain(url('/api/v1/getclients'));
});

test('every API error has the result field the reference describes', function () {
    $headers = referenceHeaders();

    // A field that fails validation: 422, with the framework's list kept.
    $this->withHeaders($headers)->post('/api/v1/addclient', [])
        ->assertStatus(422)
        ->assertJsonPath('result', 'error')
        ->assertJsonStructure(['message', 'errors' => ['firstname']]);

    // An action that does not exist, and the documented single-address style.
    $this->withHeaders($headers)->get('/api/v1/nosuchaction')
        ->assertStatus(404)->assertJsonPath('result', 'error');
    $this->withHeaders($headers)->post('/api/v1', ['action' => 'getclients'])
        ->assertStatus(404)->assertJsonPath('result', 'error');

    // The wrong method says which one to use.
    $this->withHeaders($headers)->post('/api/v1/getclients')
        ->assertStatus(405)
        ->assertJsonPath('result', 'error')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'GET'));

    // Pages outside the API keep their own 404.
    $page = $this->flushHeaders()->get('/no-such-page-anywhere')->assertStatus(404);
    expect(strtolower((string) $page->headers->get('content-type')))->toStartWith('text/html');
});

test('reaching the rate limit is answered in the API shape, with Retry-After', function () {
    RateLimiter::for('api', fn () => Limit::perMinute(1)->by('reference-test'));

    $this->getJson('/api/health')->assertOk();
    $this->getJson('/api/health')
        ->assertStatus(429)
        ->assertJsonPath('result', 'error')
        ->assertJsonPath('message', 'Too Many Attempts.')
        ->assertHeader('Retry-After');
});
