<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UsagePollingCommand extends Command
{
    protected $signature = "pnlcs:usage-polling";
    protected $description = "Poll servers for resource usage (disk, bandwidth) and update active services";

    public function handle(ModuleRegistry $registry): int
    {
        $servers = Server::where("active", true)->get();
        $totalUpdated = 0;

        foreach ($servers as $server) {
            if (!$server->type) continue;

            try {
                $module = $registry->getServerModule($server->type);
                if (!$module) {
                    $this->line("No module found for server \"{$server->name}\" (type: {$server->type}), skipping.");
                    continue;
                }

                $usageData = $module->usageUpdate($server);

                if (!is_array($usageData) || empty($usageData)) {
                    $this->line("Server \"{$server->name}\": no usage data returned.");
                    continue;
                }

                // Usage data expected format: array of [username => ..., disk_usage => ..., bw_usage => ...]
                // Match to services by username + server_id
                $services = Service::where("server_id", $server->id)
                    ->where("status", "active")
                    ->get()
                    ->keyBy("username");

                $serverUpdated = 0;

                foreach ($usageData as $entry) {
                    if (!is_array($entry) || !isset($entry["username"])) continue;

                    $service = $services->get($entry["username"]);
                    if (!$service) continue;

                    $updateData = [];
                    if (isset($entry["disk_usage"]) && is_numeric($entry["disk_usage"])) {
                        $updateData["disk_usage"] = (int) $entry["disk_usage"];
                    }
                    if (isset($entry["bw_usage"]) && is_numeric($entry["bw_usage"])) {
                        $updateData["bw_usage"] = (int) $entry["bw_usage"];
                    }
                    if (isset($entry["disk_limit"]) && is_numeric($entry["disk_limit"])) {
                        $updateData["disk_limit"] = (int) $entry["disk_limit"];
                    }
                    if (isset($entry["bw_limit"]) && is_numeric($entry["bw_limit"])) {
                        $updateData["bw_limit"] = (int) $entry["bw_limit"];
                    }

                    if (!empty($updateData)) {
                        $service->update($updateData);
                        $serverUpdated++;
                    }
                }

                $this->line("Server \"{$server->name}\": updated {$serverUpdated} service(s).");
                $totalUpdated += $serverUpdated;

            } catch (\Throwable $e) {
                Log::warning("Usage polling failed for server \"{$server->name}\" (#{$server->id}): " . $e->getMessage());
                $this->error("Server \"{$server->name}\": " . $e->getMessage());
            }
        }

        $this->info("Usage polling complete. Updated {$totalUpdated} service(s) across {$servers->count()} server(s).");
        return Command::SUCCESS;
    }
}
