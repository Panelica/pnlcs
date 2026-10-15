<?php

use Illuminate\Support\Facades\DB;

/*
 * The install wizard spoke English only. The language comes from the database,
 * and while the wizard runs there is none yet, so a German or Turkish operator
 * met their first PNLCS screens in English. The wizard now takes a language at
 * its top (?lang=), or the browser's, keeps it for every step, and offers it as
 * the default language on the last one.
 */

/** Run the request as a fresh deployment: no lock, no administrator. */
function onAFreshDeployment(Closure $run): void
{
    $lock = storage_path('installed.lock');
    $parked = $lock.'.parked-by-language-test';
    $wasLocked = file_exists($lock);
    if ($wasLocked) {
        rename($lock, $parked);
    }

    try {
        DB::table('admins')->delete();
        $run();
    } finally {
        if ($wasLocked && file_exists($parked)) {
            rename($parked, $lock);
        }
    }
}

test('the wizard speaks the browser\'s language when it has it', function () {
    onAFreshDeployment(function () {
        $this->get('/install/requirements', ['Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.5'])
            ->assertOk()
            ->assertSee('Sistem gereksinimleri')
            ->assertSee('<html lang="tr">', false);
    });
});

test('a language picked at the top is kept for the next steps', function () {
    onAFreshDeployment(function () {
        $this->get('/install/requirements?lang=de')->assertOk()->assertSee('Systemvoraussetzungen');

        $this->get('/install/admin')->assertOk()->assertSee('Administrator anlegen');
    });
});

test('a language the wizard is not written in leaves it in English', function () {
    onAFreshDeployment(function () {
        $this->get('/install/requirements?lang=xx', ['Accept-Language' => 'ja'])
            ->assertOk()
            ->assertSee('System Requirements');
    });
});

test('every step offers the five languages, the current one not as a link', function () {
    onAFreshDeployment(function () {
        $page = $this->get('/install/requirements?lang=pl')->assertOk();

        foreach (['English' => 'en', 'Türkçe' => 'tr', 'Deutsch' => 'de', '简体中文' => 'zh'] as $name => $code) {
            $page->assertSee('href="?lang='.$code.'"', false)->assertSee($name);
        }
        $page->assertSee('<strong class="text-slate-800">Polski</strong>', false)
            ->assertDontSee('href="?lang=pl"', false);
    });
});

test('the last step offers the wizard\'s language as the default', function () {
    onAFreshDeployment(function () {
        $this->get('/install/requirements?lang=pl');

        $this->get('/install/app')->assertOk()
            ->assertSee('<option value="pl" selected>', false)
            ->assertSee('Domyślny język');
    });
});
