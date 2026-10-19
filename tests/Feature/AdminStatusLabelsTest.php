<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Service;

/*
 * The admin screens printed a stored status with ucfirst(), so a Turkish admin
 * read "Terminated" next to every cancelled service, and "Fraud", "Grace" or
 * "Customer-Reply" the same way. The label now comes from common.status.*.
 */

test('a stored status is shown in the reader\'s language, whatever its spelling', function () {
    app()->setLocale('tr');

    expect(status_label('terminated'))->toBe('Sonlandırıldı')
        ->and(status_label('Terminated'))->toBe('Sonlandırıldı')
        ->and(status_label('Customer-Reply'))->toBe(__('common.status.customer-reply'))
        ->and(status_label('In Progress'))->toBe(__('common.status.in-progress'))
        ->and(status_label('transferred_away'))->toBe('Başka Firmaya Taşındı')
        ->and(status_label('Awaiting Configuration'))->toBe('Yapılandırma Bekliyor')
        ->and(status_label(\App\Enums\ServiceStatus::Fraud))->toBe(__('common.status.fraud'));
});

test('a status with no translation is shown as before, and nothing for none', function () {
    expect(status_label('some_new_state'))->toBe('Some new state')
        ->and(status_label(null))->toBe('')
        ->and(invoice_status_label('partially_paid'))->toBe(__('common.status.partially_paid'));
});

test('the admin service screens show a terminated service in the admin\'s language', function () {
    $admin = Admin::factory()->create(['language' => 'tr']);
    $service = Service::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'terminated']);

    app()->setLocale('tr');
    $this->actingAs($admin, 'admin')->get(route('admin.services.show', $service).'?lang=tr')->assertOk()
        ->assertSee('Sonlandırıldı')->assertDontSee('>Terminated<', false);
    $this->actingAs($admin, 'admin')->get(route('admin.services.index').'?lang=tr')->assertOk()
        ->assertSee('Sonlandırıldı');
});
