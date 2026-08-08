<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UsagePollingCommand extends Command
{
    protected $signature = 'pnlcs:usage-polling';

    protected $description = 'Poll servers for resource usage (disk, bandwidth) and update active services';

    /**
     * Ask every server what its accounts are using.
     *
     * The modules do the writing: each one answers with
     * ['updated' => n, 'errors' => n] and has already stored the figures on the
     * services. This used to expect the shape from before that - a list of rows
     * keyed by username, which it would write itself - so it walked an array of
     * two integers, matched nothing, and reported "updated 0 service(s)" every
     * hour while the work was quietly being done. The errors the modules count -
     * an account the server no longer has, a service with no remote id - went
     * nowhere, and those are the ones an operator needs.
     */
    public function handle(ModuleRegistry $registry): int
    {
        $servers = Server::where('active', true)->get();
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($servers as $server) {
            if (! $server->type) {
                continue;
            }

            try {
                $module = $registry->getServerModule($server->type);

                if (! $module) {
                    $this->line("No module found for server \"{$server->name}\" (type: {$server->type}), skipping.");

                    continue;
                }

                $result = $module->usageUpdate($server);

                $updated = (int) ($result['updated'] ?? 0);
                $errors = (int) ($result['errors'] ?? 0);

                $totalUpdated += $updated;
                $totalErrors += $errors;

                $line = "Server \"{$server->name}\": updated {$updated} service(s)";

                if ($errors > 0) {
                    $line .= ", {$errors} error(s)";
                    Log::warning("Usage polling: {$errors} account(s) the server could not answer for", [
                        'server_id' => $server->id,
                        'server' => $server->name,
                    ]);
                }

                $this->line($line.'.');
            } catch (\Throwable $e) {
                $totalErrors++;
                Log::warning("Usage polling failed for server \"{$server->name}\" (#{$server->id}): ".$e->getMessage());
                $this->error("Server \"{$server->name}\": ".$e->getMessage());
            }
        }

        $summary = "Usage polling complete. Updated {$totalUpdated} service(s) across {$servers->count()} server(s)";

        if ($totalErrors > 0) {
            $summary .= ", {$totalErrors} error(s)";
        }

        $this->info($summary.'.');

        return Command::SUCCESS;
    }
}
