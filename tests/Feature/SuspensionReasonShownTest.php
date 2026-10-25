<?php

use App\Mail\ServiceSuspensionMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * #306. Staff suspending a service by hand could not say why: the Suspend
 * button posted no reason, the customer's service page did not say the
 * service was suspended at all, and the suspension email filled the "Reason"
 * row with "Unpaid invoice" whatever had happened - an abuse case was told to
 * pay a bill. Suspend now takes a reason; the customer reads it on the service
 * page and in the email, and the next member of staff sees it on the admin page.
 */

function srsService(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $service = Service::factory()->create(['client_id' => $client->id, 'server_id' => null, 'status' => 'active', 'suspension_reason' => null]);

    return [$service, $user];
}

function srsAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a reason given when suspending is kept, shown to the customer and to staff', function () {
    Mail::fake();
    [$service, $user] = srsService();
    $service->update(['status' => 'suspended', 'suspension_date' => now(), 'suspension_reason' => 'Outgoing spam from the server (abuse report #4411)']);

    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()
        ->assertSee(__('client.services.suspended_because', ['reason' => 'Outgoing spam from the server (abuse report #4411)']));
    $this->actingAs(srsAdmin(), 'admin')->get(route('admin.services.show', $service))->assertOk()
        ->assertSee('Outgoing spam from the server (abuse report #4411)')
        ->assertSee('name="reason"', false);

    $html = (new ServiceSuspensionMail($service->fresh(), 'Outgoing spam from the server (abuse report #4411)'))->render();
    expect($html)->toContain('Outgoing spam from the server')->not->toContain(__('email.common.unpaid_invoice'));
});

test('the suspend action validates and records the reason', function () {
    [$service] = srsService();

    $this->actingAs(srsAdmin(), 'admin')->post(route('admin.services.module-action', [$service, 'suspend']), ['reason' => str_repeat('x', 256)])
        ->assertSessionHasErrors('reason');
});

test('without a reason the customer is asked to contact support, and a billing suspension reads as an unpaid invoice', function () {
    [$service, $user] = srsService();
    $service->update(['status' => 'suspended', 'suspension_reason' => '']);
    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()->assertSee(__('client.services.suspended_ask_support'));
    expect((new ServiceSuspensionMail($service->fresh(), ''))->render())->toContain(e(__('email.service_suspension.reason_ask_support')));

    $service->update(['suspension_reason' => 'Overdue Invoice - Automatic Suspension']);
    expect($service->fresh()->customerSuspensionReason())->toBe(__('email.common.unpaid_invoice'));
    $this->actingAs($user)->get(route('client.services.show', $service))->assertDontSee('Overdue Invoice - Automatic Suspension');
});

test('an active service says nothing about suspension', function () {
    [$service, $user] = srsService();

    $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()
        ->assertDontSee(__('client.services.suspended_ask_support'));
    expect($service->customerSuspensionReason())->toBeNull();
});
