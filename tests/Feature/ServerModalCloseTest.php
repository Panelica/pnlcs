<?php

use App\Models\Admin;
use App\Models\Server;

/*
 * Edit a server, press Cancel, and an empty box stayed on screen. The close
 * buttons hid "the nearest element with an id", and the edit form has one
 * (form-edit-server, which editServer() points at the server), so Cancel hid
 * the form inside the dialog and left the dialog. Every later Edit opened that
 * empty dialog until the page was reloaded.
 *
 * This walks the rendered page the way the browser does: for every close
 * button, the element its closest() lands on must be the dialog itself.
 */
test('every close button on the servers page closes its dialog, not something inside it', function () {
    Server::factory()->create();

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/admin/config/servers')->assertOk()->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);

    $checked = 0;
    $wrong = [];

    foreach ($dom->getElementsByTagName('button') as $button) {
        if (! preg_match("/this\\.closest\\('([^']+)'\\)\\.style\\.display='none'/", (string) $button->getAttribute('onclick'), $m)) {
            continue;
        }

        $selector = $m[1];
        $matches = match (true) {
            $selector === '[id]' => fn (DOMElement $el) => $el->hasAttribute('id'),
            (bool) preg_match('/^\[id\^=([\w-]+)\]$/', $selector, $p) => fn (DOMElement $el) => str_starts_with($el->getAttribute('id'), $p[1]),
            default => throw new RuntimeException("selector this test does not know: {$selector}"),
        };

        $target = $button->parentNode;
        while ($target instanceof DOMElement && ! $matches($target)) {
            $target = $target->parentNode;
        }

        $checked++;
        $id = $target instanceof DOMElement ? $target->getAttribute('id') : '(none)';
        if (! str_starts_with($id, 'modal-')) {
            $wrong[] = trim($button->textContent).' closes '.$id;
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(4)
        ->and($wrong)->toBe([]);
});
