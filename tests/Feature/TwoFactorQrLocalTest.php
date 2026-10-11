<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;
use App\Support\QrCode;

/*
 * The two-factor setup screens drew their QR code through a public QR service:
 * <img src="https://api.qrserver.com/...&data=otpauth://...secret=...">. The
 * browser sent the otpauth address, and the shared secret in it, to a third
 * party every time someone set up two-factor sign-in. The code is now drawn on
 * this server and embedded in the page.
 */

test('the staff setup screen draws its QR code here and sends the secret nowhere', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $page = $this->actingAs($admin, 'admin')->get(route('admin.2fa.enable'))->assertOk();

    $page->assertDontSee('qrserver.com', false)->assertSee('src="data:image/svg+xml;base64,', false);
    expect($page->getContent())->not->toContain('otpauth%3A%2F%2F')->not->toContain('otpauth://');
});

test('the customer setup screen draws its QR code here and sends the secret nowhere', function () {
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    $page = $this->actingAs($user)->get(route('client.2fa.enable'))->assertOk();

    $page->assertDontSee('qrserver.com', false)->assertSee('src="data:image/svg+xml;base64,', false);
    expect($page->getContent())->not->toContain('otpauth%3A%2F%2F')->not->toContain('otpauth://');
});

test('the code is an SVG image of the address it was given', function () {
    $uri = QrCode::dataUri('otpauth://totp/PNLCS:a%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=PNLCS');

    expect($uri)->toStartWith('data:image/svg+xml;base64,');
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg')->toContain('width="200"')
        // The image, not the text: nothing readable of the secret is in it.
        ->not->toContain('JBSWY3DPEHPK3PXP');
});
