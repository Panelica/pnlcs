<?php

use App\Services\Updates\UpdateState;

/**
 * The updater's own records (the run, its status, the history): what the
 * self-healing reads to know an update stopped part way. A record must never
 * be lost or emptied by a write that could not be made.
 */
function stateDir(): UpdateState
{
    return new UpdateState(storage_path('framework/testing/state-'.bin2hex(random_bytes(6))));
}

test('a record whose text is not valid UTF-8 is still written, and nothing of it is lost', function () {
    $state = stateDir();
    // A database or a process may answer in another encoding: the message of
    // the error that stopped the update is then not valid UTF-8.
    $state->write('current-run.json', ['id' => 'r1', 'phase' => 'migrating', 'error' => "SQLSTATE: Fehler \xE4 in Spalte"]);

    $run = $state->read('current-run.json');
    expect($run['phase'])->toBe('migrating')
        ->and($run['id'])->toBe('r1')
        ->and($run['error'])->toStartWith('SQLSTATE: Fehler ');
});

test('a write that cannot be made keeps the record as it was', function () {
    $state = stateDir();
    $state->write('current-run.json', ['id' => 'r1', 'phase' => 'files']);

    // Something that cannot be written at all (here: a resource).
    expect(fn () => $state->write('current-run.json', ['id' => 'r1', 'phase' => 'migrating', 'handle' => fopen('php://memory', 'r')]))
        ->toThrow(RuntimeException::class);

    expect($state->read('current-run.json'))->toBe(['id' => 'r1', 'phase' => 'files']);
});

test('a write leaves no temporary file behind, and two writers never share one', function () {
    $state = stateDir();
    foreach (range(1, 5) as $i) {
        $state->write('status.json', ['state' => 'applying', 'n' => $i]);
    }

    expect(glob($state->path('*.tmp*')))->toBe([])
        ->and($state->read('status.json')['n'])->toBe(5);
});
