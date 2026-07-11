<?php

use App\Models\GatewaySettings;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Gateway and registrar secret values are encrypted at rest. Reads are graceful:
 * a legacy plaintext row (written before the cast existed) is returned as-is
 * rather than throwing, so live payments never break during the transition.
 */

it('stores a gateway secret encrypted at rest and reads it back in plaintext', function () {
    $s = GatewaySettings::create(['gateway' => 'stripe', 'setting' => 'secret_key', 'value' => 'sk_live_SECRET']);

    $raw = DB::table('gateway_settings')->where('id', $s->id)->value('value');
    expect($raw)->not->toBe('sk_live_SECRET');            // ciphertext at rest
    expect(Crypt::decryptString($raw))->toBe('sk_live_SECRET');

    expect(GatewaySettings::find($s->id)->value)->toBe('sk_live_SECRET'); // cast decrypts
});

it('encrypts registrar secrets at rest too', function () {
    $s = RegistrarSettings::create(['registrar' => 'enom', 'setting' => 'password', 'value' => 'enom_pw']);
    $raw = DB::table('registrar_settings')->where('id', $s->id)->value('value');
    expect($raw)->not->toBe('enom_pw');
    expect(RegistrarSettings::find($s->id)->value)->toBe('enom_pw');
});

it('reads a legacy plaintext value without throwing', function () {
    $id = DB::table('gateway_settings')->insertGetId([
        'gateway' => 'paypal', 'setting' => 'client_secret', 'value' => 'legacy_plain',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(GatewaySettings::find($id)->value)->toBe('legacy_plain');
});
