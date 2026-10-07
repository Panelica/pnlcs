<?php

use Illuminate\Contracts\Foundation\MaintenanceMode;

/**
 * pnlcs:update-health, the updater's last step: a failure rolls the update
 * back, so it must fail only when the new version does not work.
 */
test('the new version is checked as it is, also when the site is in maintenance', function (array $data) {
    // In maintenance as `php artisan down` puts it, with or without a secret:
    // the operator's own maintenance, kept by the update.
    app()->instance(MaintenanceMode::class, new class($data) implements MaintenanceMode
    {
        public function __construct(private array $payload) {}

        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        public function data(): array
        {
            return $this->payload;
        }
    });

    $this->artisan('pnlcs:update-health')->expectsOutput('healthy')->assertExitCode(0);
})->with([
    'no secret' => [['retry' => 60, 'status' => 503]],
    'a secret' => [['retry' => 60, 'status' => 503, 'secret' => 'operator-secret']],
]);

test('a page that fails with a server error fails the check', function () {
    \Illuminate\Support\Facades\Route::get('/client/login', fn () => abort(500, 'broken view'));

    $this->artisan('pnlcs:update-health')->assertExitCode(1);
});
