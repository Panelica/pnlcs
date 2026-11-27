<?php

namespace Modules\Ksef\Console;

use App\Services\AddonManager;
use Illuminate\Console\Command;
use Modules\Ksef\KsefInvoiceImporter;

class ImportKsefInvoicesCommand extends Command
{
    protected $signature = 'pnlcs:ksef-import-invoices';

    protected $description = 'Import sales and cost invoices from KSeF';

    public function handle(AddonManager $addons, KsefInvoiceImporter $importer): int
    {
        if (! $addons->isActive('ksef')) {
            return self::SUCCESS;
        }

        $result = $importer->import();

        $this->line(sprintf(
            '%s: fetched %d, created %d, updated %d, errors %d',
            $result['status'],
            $result['fetched'],
            $result['created'],
            $result['updated'],
            $result['errors'],
        ));

        if ($result['status'] === 'error') {
            $this->error($result['message']);
        }

        return $result['status'] === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
