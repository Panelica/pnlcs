<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\DockerAppImporter;
use App\Services\Module\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Fill in the catalogue images from the console.
 *
 * The same job as the button on the admin screen, reachable without a browser
 * so a new install can be seeded in one go and so the run can be repeated from
 * a deploy step.
 */
class ImportDockerAppsCommand extends Command
{
    protected $signature = 'docker-apps:import-logos
        {--overwrite : Replace images that are already stored}
        {--no-icon-set : Only try the links the panel carries}';

    protected $description = 'Fetch catalogue images for the Docker app catalogue';

    public function handle(DockerAppImporter $importer): int
    {
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if (! $server) {
            $this->error('No active Panelica server is configured.');

            return self::FAILURE;
        }

        $module = app(ModuleRegistry::class)->getServerModule('panelica');
        if (! $module || ! method_exists($module, 'appTemplates')) {
            $this->error('The Panelica module does not expose an app catalogue.');

            return self::FAILURE;
        }

        $templates = $module->appTemplates($server);
        if ($templates === []) {
            $this->error('The panel returned no apps.');

            return self::FAILURE;
        }

        $this->info(count($templates).' apps in the catalogue.');
        $bar = $this->output->createProgressBar(count($templates));
        $bar->start();

        $r = $importer->importMany(
            $templates,
            (bool) $this->option('overwrite'),
            ! $this->option('no-icon-set'),
            function () use ($bar) {
                $bar->advance();
            }
        );

        $bar->finish();
        $this->newLine(2);
        $this->line("  fetched : {$r['done']}");
        $this->line("  failed  : {$r['failed']}");
        $this->line("  skipped : {$r['skipped']} (already had an image)");
        $this->line("  no link : {$r['none']}");

        return self::SUCCESS;
    }
}
