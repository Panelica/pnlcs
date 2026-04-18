<?php
namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class DomainSyncCommand extends Command
{
    protected $signature = "pnlcs:domain-sync";
    protected $description = "Sync domain statuses with registrars";

    public function handle(): int
    {
        $domains = Domain::where("status", "active")->whereNotNull("registrar")->get();
        $this->info("Domain sync: {$domains->count()} domains to check. (Module integration required)");
        return Command::SUCCESS;
    }
}
