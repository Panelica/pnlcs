<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DockerAppLogo;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * The app catalogue as the operator sees it.
 *
 * The catalogue itself lives on the panel - what exists, what each plan may
 * install, whether an app is active - and none of that is edited here. What is
 * ours is how the catalogue looks to a customer, which today means the image.
 * The panel's logo_url points at third-party servers and mostly does not
 * resolve, so images are stored on our side and served from our storage.
 */
class DockerAppController extends Controller
{
    /** Images we accept; anything else is refused before it is written. */
    private const ACCEPTED = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'];

    private const MAX_BYTES = 512 * 1024;

    public function index(Request $request)
    {
        [$templates, $error] = $this->catalogue();
        $logos = DockerAppLogo::urlMap();

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $templates = array_values(array_filter($templates, fn ($t) => str_contains(mb_strtolower($t['name'].' '.$t['slug']), $needle)));
        }

        if ($request->query('missing') === '1') {
            $templates = array_values(array_filter($templates, fn ($t) => ! isset($logos[$t['slug']])));
        }

        return view('admin.docker-apps.index', [
            'templates' => $templates,
            'logos' => $logos,
            'error' => $error,
            'q' => $q,
            'missingOnly' => $request->query('missing') === '1',
            'totalWithLogo' => count($logos),
        ]);
    }

    /** Store an uploaded image for one app. */
    public function upload(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'logo' => ['required', 'file', 'max:512', 'mimes:png,jpg,jpeg,svg,webp,gif'],
        ]);

        $slug = strtolower($request->input('slug'));
        $ext = strtolower($request->file('logo')->getClientOriginalExtension());
        $this->put($slug, $request->file('logo')->get(), $ext, 'upload');

        return back()->with('success', __('admin.docker_apps.saved', ['app' => $slug]));
    }

    /** Pull an image from a URL the operator pasted. */
    public function fetch(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'url' => ['required', 'url', 'max:500'],
        ]);

        $slug = strtolower($request->input('slug'));
        $result = $this->fetchOne($slug, $request->input('url'));

        return $result === true
            ? back()->with('success', __('admin.docker_apps.saved', ['app' => $slug]))
            : back()->with('error', __('admin.docker_apps.fetch_failed', ['app' => $slug, 'reason' => $result]));
    }

    public function destroy(Request $request)
    {
        $request->validate(['slug' => ['required', 'string', 'max:100']]);
        $logo = DockerAppLogo::where('slug', strtolower($request->input('slug')))->first();
        if ($logo) {
            Storage::disk('public')->delete($logo->path);
            $logo->delete();
        }

        return back()->with('success', __('admin.docker_apps.removed'));
    }

    /**
     * Take every logo the panel still has a working link for, in one pass.
     *
     * This is the "fill it in for me" button: most apps have no usable image,
     * so doing them one at a time is not a realistic starting point. Links that
     * are dead - about half of the ones the panel carries - are counted and
     * reported rather than silently skipped, so the operator knows what is left
     * to do by hand.
     */
    public function importAll(Request $request)
    {
        [$templates, $error] = $this->catalogue();
        if ($error) {
            return back()->with('error', $error);
        }

        $have = DockerAppLogo::pluck('slug')->all();
        $overwrite = $request->boolean('overwrite');

        $done = 0;
        $failed = 0;
        $skipped = 0;
        $noUrl = 0;
        foreach ($templates as $t) {
            $slug = $t['slug'];
            $url = trim((string) ($t['logo_url'] ?? ''));
            if ($url === '') {
                $noUrl++;

                continue;
            }
            if (! $overwrite && in_array($slug, $have, true)) {
                $skipped++;

                continue;
            }
            $this->fetchOne($slug, $url) === true ? $done++ : $failed++;
        }

        return back()->with('success', __('admin.docker_apps.import_done', [
            'done' => $done, 'failed' => $failed, 'skipped' => $skipped, 'none' => $noUrl,
        ]));
    }

    /**
     * The panel's whole catalogue, plus a reason when it cannot be read.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: ?string}
     */
    private function catalogue(): array
    {
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if (! $server) {
            return [[], __('admin.docker_apps.no_server')];
        }
        try {
            $module = app(ModuleRegistry::class)->getServerModule('panelica');
            if (! $module || ! method_exists($module, 'appTemplates')) {
                return [[], __('admin.docker_apps.no_module')];
            }
            $templates = $module->appTemplates($server);
        } catch (\Throwable $e) {
            return [[], __('admin.docker_apps.catalogue_failed', ['error' => $e->getMessage()])];
        }

        return [$templates, $templates === [] ? __('admin.docker_apps.catalogue_empty') : null];
    }

    /** @return true|string true, or why it could not be fetched */
    private function fetchOne(string $slug, string $url)
    {
        try {
            $resp = Http::timeout(12)->withHeaders(['User-Agent' => 'PNLCS'])->get($url);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        if (! $resp->successful()) {
            return 'HTTP '.$resp->status();
        }

        $body = $resp->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return $body === '' ? 'empty response' : 'larger than 512 KB';
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (! in_array($ext, self::ACCEPTED, true)) {
            $ext = match (true) {
                str_contains($resp->header('Content-Type'), 'svg') => 'svg',
                str_contains($resp->header('Content-Type'), 'png') => 'png',
                str_contains($resp->header('Content-Type'), 'webp') => 'webp',
                str_contains($resp->header('Content-Type'), 'gif') => 'gif',
                str_contains($resp->header('Content-Type'), 'jpeg') => 'jpg',
                default => '',
            };
        }
        if ($ext === '') {
            return 'not an image';
        }

        $this->put($slug, $body, $ext, 'fetch');

        return true;
    }

    /** Write the image and point the app at it, replacing whatever was there. */
    private function put(string $slug, string $bytes, string $ext, string $source): void
    {
        $existing = DockerAppLogo::where('slug', $slug)->first();
        if ($existing) {
            Storage::disk('public')->delete($existing->path);
        }

        // The name carries a counter so a replaced image is not served from a
        // browser cache under its old name.
        $path = 'docker-apps/'.$slug.'-'.substr(md5($bytes), 0, 8).'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        DockerAppLogo::updateOrCreate(['slug' => $slug], ['path' => $path, 'source' => $source]);
    }
}
