<?php

use App\Events\InvoicePaid;
use App\Listeners\RenewOnPaymentListener;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;
use Modules\Servers\Custom\CustomModule;

/*
 * What a server module may add to a service's screens and mail
 * (App\Services\Module\ModuleScreens), and the renewal a module refused
 * being tried again (ProvisioningService::renewAccount()).
 *
 * Every hook is optional: a module that offers none of them leaves the pages
 * exactly as they were, and one that throws leaves them standing.
 */

class ScreensTestModule extends CustomModule
{
    public static bool $throw = false;

    public static bool $refuseRenew = false;

    public static array $ran = [];

    public function getModuleName(): string
    {
        return 'screenstest';
    }

    public function clientAreaPanel(Service $service): HtmlString
    {
        if (self::$throw) {
            throw new RuntimeException('drawing failed');
        }

        return new HtmlString('<div id="screens-client">client section for #'.$service->id.'</div>');
    }

    public function adminAreaPanel(Service $service): HtmlString
    {
        return new HtmlString('<div id="screens-admin">staff section</div>');
    }

    public function customFunctions(): array
    {
        return ['staffOnly' => 'Staff only', 'both' => 'Both sides'];
    }

    public function clientFunctions(): array
    {
        return ['both' => 'Both sides'];
    }

    public function servicePageHides(): array
    {
        return ['server_details', 'change_password'];
    }

    public function isProvisioned(Service $service): bool
    {
        return (bool) ($service->module_data['done'] ?? false);
    }

    public function emailMergeFields(Service $service): array
    {
        return ['service_extra' => 'extra value', 'service_product' => 'must not win'];
    }

    public function staffOnly(Service $service): array
    {
        self::$ran[] = 'staffOnly';

        return ['success' => true, 'message' => 'staff function ran'];
    }

    public function both(Service $service): array
    {
        self::$ran[] = 'both';

        return ['success' => true, 'message' => 'shared function ran'];
    }

    public function renew(Service $service): array
    {
        self::$ran[] = 'renew';

        return self::$refuseRenew ? ['success' => false, 'message' => 'vendor balance too low'] : ['success' => true, 'message' => 'renewed'];
    }
}

beforeEach(function () {
    ScreensTestModule::$throw = false;
    ScreensTestModule::$refuseRenew = false;
    ScreensTestModule::$ran = [];
    app(ModuleRegistry::class)->registerServer('screenstest', ScreensTestModule::class);
});

function screensService(string $type, array $attrs = []): Service
{
    $server = Server::factory()->create(['type' => $type, 'hostname' => 'node7.example.net', 'ip_address' => '198.51.100.70', 'active' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => $type]);

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'username' => 'acct7', 'billing_cycle' => 'Monthly', 'next_due_date' => '2026-11-01',
    ], $attrs));
}

function screensCustomer(Service $service): User
{
    $user = User::factory()->create();
    $user->clients()->attach($service->client_id);

    return $user;
}

function screensStaff(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'S'.uniqid(), 'permissions' => ['view_services', 'manage_services']])->id]);
}

test('a module that offers none of it leaves both service pages as they were', function () {
    $service = screensService('custom');

    $client = test()->actingAs(screensCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();
    $staff = test()->actingAs(screensStaff(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();

    expect($client)->toContain(__('client.services.server_info'))
        ->and($client)->toContain('node7.example.net')
        ->and($staff)->toContain(route('admin.services.module-action', [$service, 'changepassword']))
        // Active with a username: there is nothing to create, as before.
        ->and($staff)->not->toContain(route('admin.services.module-action', [$service, 'create']));
});

test('a module can show its own section and hide a server that means nothing to the customer', function () {
    $service = screensService('screenstest');

    $client = test()->actingAs(screensCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();

    expect($client)->toContain('<div id="screens-client">client section for #'.$service->id.'</div>')
        ->and($client)->not->toContain(__('client.services.server_info'))
        ->and($client)->not->toContain('node7.example.net');
});

test('a section that fails to draw leaves the page standing', function () {
    ScreensTestModule::$throw = true;
    $service = screensService('screenstest');

    $client = test()->actingAs(screensCustomer($service))->get(route('client.services.show', $service))->assertOk()->getContent();

    expect($client)->not->toContain('screens-client')
        ->and($client)->toContain(__('client.services.service_details'));
});

test('staff get the module\'s section and buttons; Create and password change follow what the module says', function () {
    $service = screensService('screenstest', ['username' => null]);
    $staff = screensStaff();

    $page = test()->actingAs($staff, 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();
    expect($page)->toContain('<div id="screens-admin">staff section</div>')
        ->and($page)->toContain(route('admin.services.module-action', [$service, 'staffOnly']))
        ->and($page)->toContain(route('admin.services.module-action', [$service, 'create']))
        ->and($page)->not->toContain(route('admin.services.module-action', [$service, 'changepassword']));

    // No username, but the module says it is done: no Create.
    $service->forceFill(['module_data' => ['done' => true]])->save();
    $page = test()->actingAs($staff, 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();
    expect($page)->not->toContain(route('admin.services.module-action', [$service, 'create']));

    test()->actingAs($staff, 'admin')->post(route('admin.services.module-action', [$service, 'staffOnly']))
        ->assertRedirect()->assertSessionHas('success', 'staff function ran');
    expect(ScreensTestModule::$ran)->toBe(['staffOnly']);
});

test('the customer reaches only the functions offered to the customer, on their own service', function () {
    $service = screensService('screenstest');
    $user = screensCustomer($service);

    test()->actingAs($user)->post(route('client.services.module-function', [$service, 'both']))
        ->assertRedirect()->assertSessionHas('success', 'shared function ran');
    test()->actingAs($user)->post(route('client.services.module-function', [$service, 'staffOnly']))->assertNotFound();
    test()->actingAs($user)->post(route('client.services.module-function', [$service, 'renew']))->assertNotFound();
    test()->actingAs(screensCustomer(screensService('screenstest')))->post(route('client.services.module-function', [$service, 'both']))->assertForbidden();

    expect(ScreensTestModule::$ran)->toBe(['both']);
});

test('a module function is not run for a module that does not offer it', function () {
    $service = screensService('custom');

    test()->actingAs(screensCustomer($service))->post(route('client.services.module-function', [$service, 'create']))->assertNotFound();
    test()->actingAs(screensStaff(), 'admin')->post(route('admin.services.module-action', [$service, 'staffOnly']))
        ->assertRedirect()->assertSessionHas('error');
});

test('a module adds fields to the service\'s mail, and the built-in ones win a clash', function () {
    $service = screensService('screenstest');

    $vars = app(EmailTemplateService::class)->varsFor(['service' => $service->load('product')]);

    expect($vars['service_extra'] ?? null)->toBe('extra value')
        ->and($vars['service_product'])->toBe($service->product->name);
});

test('a refused renewal is queued and bought on the next run; a service ended meanwhile is not', function () {
    ScreensTestModule::$refuseRenew = true;
    $service = screensService('screenstest');
    $invoice = Invoice::factory()->create(['client_id' => $service->client_id, 'status' => 'paid', 'total' => 10]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $service->client_id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Renewal', 'amount' => 10]);

    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));
    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'renew')->firstOrFail();
    expect($entry->status)->toBe('pending')->and($entry->last_error)->toBe('vendor balance too low');

    ScreensTestModule::$refuseRenew = false;
    $entry->update(['next_attempt_at' => now()->subMinute()]);
    Artisan::call('pnlcs:module-queue');
    expect($entry->fresh()->status)->toBe('completed')
        ->and(ScreensTestModule::$ran)->toBe(['renew', 'renew']);

    // Refused again, then the service ends before the retry.
    ScreensTestModule::$refuseRenew = true;
    app(RenewOnPaymentListener::class)->handleInvoicePaid(new InvoicePaid($invoice->fresh()));
    $again = ModuleQueue::where('service_id', $service->id)->where('action', 'renew')->where('status', 'pending')->firstOrFail();
    $service->forceFill(['status' => 'terminated'])->save();
    $again->update(['next_attempt_at' => now()->subMinute()]);
    Artisan::call('pnlcs:module-queue');

    expect($again->fresh()->status)->toBe('completed')
        ->and(ScreensTestModule::$ran)->toBe(['renew', 'renew', 'renew']);
});
