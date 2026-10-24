<?php

use App\Models\Affiliate;
use App\Models\Client;
use Illuminate\Http\Request;
use Modules\Reports\ClientSourcesReport;

/**
 * Client Sources grouped clients by clients.notes - staff's private notes
 * about the customer - so the report listed note texts as "sources", one row
 * per distinct note. What PNLCS records about where a client came from is the
 * referring affiliate.
 */
it('groups clients by referral, never by staff notes', function () {
    $referrer = Client::factory()->create();
    $affiliate = Affiliate::create(['client_id' => $referrer->id, 'code' => 'SRC'.uniqid()]);
    Client::factory()->create(['notes' => 'Late payer, call before suspending', 'affiliate_id' => $affiliate->id]);
    Client::factory()->create(['notes' => 'VIP - owner is a friend']);

    $rows = collect((new ClientSourcesReport)->generate(Request::create('/'))['rows']);
    $sources = $rows->pluck('source')->all();

    expect($sources)->not->toContain('Late payer, call before suspending')
        ->and($sources)->not->toContain('VIP - owner is a friend')
        ->and($sources)->toContain('Affiliate referral', 'Direct/Unknown')
        ->and((int) $rows->sum('clients'))->toBe(Client::count());
});
