<?php

namespace Modules\Servers\PanelicaLicense\Console;

use Illuminate\Console\Command;
use Modules\Servers\PanelicaLicense\LicenseSync;

/** Every five minutes: see LicenseSync. */
class SyncLicensesCommand extends Command
{
    protected $signature = 'pnlcs:panelica-licenses {--service= : Check one service now}';

    protected $description = 'Find servers installed with a Panelica license link, buy their licenses and read bought ones back';

    public function handle(LicenseSync $sync): int
    {
        $sync->run($this->option('service') ? (int) $this->option('service') : null, fn (string $line) => $this->line($line));

        return self::SUCCESS;
    }
}
