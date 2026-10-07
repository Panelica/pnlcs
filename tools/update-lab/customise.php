<?php

/*
 * Makes an installation look like one an operator has lived in: everything
 * the update must keep, and two edits to core files (one that merges, one
 * that conflicts with the lab's next release).
 *
 *   php customise.php <installation>
 */

$root = rtrim($argv[1] ?? '', '/');
if (! is_file("{$root}/artisan")) {
    fwrite(STDERR, "usage: php customise.php <installation>\n");
    exit(64);
}

function put(string $file, string $content): void
{
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, $content);
}

// Their own theme, replacing a view the next release changes.
put("{$root}/themes/acme/theme.json", json_encode(['name' => 'Acme', 'slug' => 'acme', 'version' => '1.0.0', 'requires' => ['pnlcs' => '>=1.3 <2']], JSON_PRETTY_PRINT));
put("{$root}/themes/acme/views/sections/footer.blade.php", file_get_contents("{$root}/resources/views/sections/footer.blade.php")."\n{{-- acme footer --}}\n");
put("{$root}/themes/acme/assets/site.css", "body { --acme: 1; }\n");

// Their own module, and their own hook.
put("{$root}/modules/Servers/LabMine/pnlcs.json", json_encode(['name' => 'Lab Mine', 'type' => 'server', 'requires' => ['pnlcs' => '>=1.3 <2']], JSON_PRETTY_PRINT));
put("{$root}/modules/Servers/LabMine/README.md", "The operator's own module.\n");
put("{$root}/app/Hooks/lab-operator.php", "<?php\n\n// The operator's own hook.\n");

// An upload, and a line of their own in .env.
put("{$root}/storage/app/public/logo.png", "\x89PNG\r\n\x1a\nlab-logo");
file_put_contents("{$root}/.env", "\n# operator setting\nLAB_OPERATOR=1\n", FILE_APPEND);

// Edits to core files: robots.txt where the next release also writes (a
// conflict); the top of the admin layout, which the release leaves alone
// (merges cleanly, as on the enahosting installation).
$robots = file_get_contents("{$root}/public/robots.txt");
file_put_contents("{$root}/public/robots.txt", str_replace('User-agent: *', "User-agent: *\nDisallow: /operator-private", $robots));
$layout = file_get_contents("{$root}/resources/views/admin/layouts/app.blade.php");
file_put_contents("{$root}/resources/views/admin/layouts/app.blade.php", "{{-- operator: our own note at the top --}}\n".$layout);

// The database: a template the operator rewrote, a translation of theirs,
// and their theme switched on.
require "{$root}/vendor/autoload.php";
$app = require "{$root}/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$template = Illuminate\Support\Facades\DB::table('email_templates')->where('language', 'en')->orderBy('id')->first();
Illuminate\Support\Facades\DB::table('email_templates')->where('id', $template->id)->update(['message' => 'Operator wording', 'custom' => true]);
Illuminate\Support\Facades\DB::table('dynamic_translations')->updateOrInsert(
    ['language' => 'en', 'group' => 'common', 'key' => 'lab_operator'],
    ['value' => 'Operator translation', 'created_at' => now(), 'updated_at' => now()],
);
if (! app(App\Services\ThemeManager::class)->activate('acme')) {
    fwrite(STDERR, "could not activate the acme theme\n");
    exit(1);
}

echo "customised\n";
