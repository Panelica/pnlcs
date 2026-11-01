<?php

use App\Mail\TicketOpenedMail;
use App\Models\Client;
use App\Models\DomainPricing;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Services\WhoisLookup;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Two public doors that did more than they needed for anybody:
 * - the contact form acknowledges to whatever address is typed in, and the
 *   acknowledgement quoted the subject and message back, so the panel sent
 *   any text to any address in the operator's name. A ticket without an
 *   account is now acknowledged with its reference and department only;
 * - a domain search in the address bar asked the registries with no limit,
 *   while the same search posted from the form was throttled.
 */
function limitsDepartment(): TicketDepartment
{
    return TicketDepartment::create(['name' => 'Sales', 'email' => 'sales@example.com', 'hidden' => false]);
}

test('the acknowledgement to a contact form sender does not quote what was written', function () {
    Mail::fake();
    $department = limitsDepartment();

    $this->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'someone-else@example.test',
        'department_id' => $department->id,
        'subject' => 'Cheap pills here',
        'message' => 'Visit our shop at spam.example',
    ])->assertRedirect();

    $ticket = Ticket::firstOrFail();
    $mail = new TicketOpenedMail($ticket);
    $html = $mail->render();

    expect($mail->envelope()->subject)->toBe("[Ticket #{$ticket->tid}] ".company_name())
        ->and($html)->not->toContain('Cheap pills here')
        ->and($html)->not->toContain('spam.example')
        ->and($html)->toContain((string) $ticket->tid);
    Mail::assertQueued(TicketOpenedMail::class, fn ($m) => $m->hasTo('someone-else@example.test'));
});

test('a customer\'s own ticket, and the staff alert, still quote it', function () {
    $department = limitsDepartment();
    $client = Client::factory()->create();
    $ticket = Ticket::create([
        'tid' => '123456', 'department_id' => $department->id, 'client_id' => $client->id,
        'name' => 'Ada', 'email' => 'ada@example.test', 'title' => 'Backup question',
        'message' => 'Is my site backed up daily?', 'status' => 'Open', 'priority' => 'Medium',
    ]);

    expect((new TicketOpenedMail($ticket))->envelope()->subject)->toBe('[Ticket #123456] Backup question')
        ->and((new TicketOpenedMail($ticket))->render())->toContain('Is my site backed up daily?');

    $guest = Ticket::create([
        'tid' => '654321', 'department_id' => $department->id, 'client_id' => null,
        'name' => 'V', 'email' => 'v@example.test', 'title' => 'Pre-sales', 'message' => 'Hello there',
        'status' => 'Open', 'priority' => 'Medium',
    ]);
    expect((new TicketOpenedMail($guest, true))->render())->toContain('Hello there');
});

test('a domain search in the address bar is limited like the form', function () {
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 9.99, 'transfer_price' => 9.99, 'renew_price' => 9.99,
        'min_years' => 1, 'max_years' => 10, 'sort_order' => 0, 'enabled' => true,
    ]);
    Setting::set('default_registrar', 'manual');
    app()->instance(WhoisLookup::class, new class extends WhoisLookup
    {
        public function check(string $domain, ?string $server): array
        {
            return ['available' => true, 'checked' => true, 'response' => ''];
        }
    });
    RateLimiter::clear('domain-search-get:127.0.0.1');

    for ($i = 0; $i < 20; $i++) {
        $this->get(route('client.domain.search', ['domain' => 'marka'.$i]))->assertOk();
    }
    $this->get(route('client.domain.search', ['domain' => 'one-more']))->assertStatus(429);

    // The page itself, without a search, stays open.
    $this->get(route('client.domain.search'))->assertOk();
});
