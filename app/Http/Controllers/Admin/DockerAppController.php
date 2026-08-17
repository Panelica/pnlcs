<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DockerApp;
use App\Models\Server;
use App\Services\DockerAppImporter;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
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
    public function __construct(private DockerAppImporter $importer) {}

    public function index(Request $request)
    {
        [$templates, $error] = $this->catalogue();
        $templates = DockerApp::decorate($templates);
        $logos = DockerApp::urlMap();
        $rows = DockerApp::bySlug();

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
            'rows' => $rows,
            'missingOnly' => $request->query('missing') === '1',
            'totalWithLogo' => count($logos),
            'totalSellable' => count($templates) - collect($rows)->where('is_sellable', false)->count(),
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
        $this->importer->store($slug, $request->file('logo')->get(), $ext, 'upload');

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
        $result = $this->importer->import($slug, $request->input('url'));

        return $result === true
            ? back()->with('success', __('admin.docker_apps.saved', ['app' => $slug]))
            : back()->with('error', __('admin.docker_apps.fetch_failed', ['app' => $slug, 'reason' => $result]));
    }

    /**
     * How an app is sold: whether we offer it, where it sits, what it says.
     *
     * Commercial, not technical - the panel decides what an app is and which
     * plans may install it; this decides whether we put it in front of anyone.
     */
    public function updateSelling(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'tagline' => ['nullable', 'string', 'max:160'],
        ]);

        $slug = strtolower($data['slug']);
        DockerApp::updateOrCreate(['slug' => $slug], [
            'is_sellable' => $request->boolean('is_sellable'),
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'tagline' => trim((string) ($data['tagline'] ?? '')) ?: null,
        ]);

        return back()->with('success', __('admin.docker_apps.selling_saved', ['app' => $slug]));
    }

    public function destroy(Request $request)
    {
        $request->validate(['slug' => ['required', 'string', 'max:100']]);
        $app = DockerApp::where('slug', strtolower($request->input('slug')))->first();
        if ($app?->path) {
            Storage::disk('public')->delete($app->path);
            // Only the image goes: the row still carries whether we sell the
            // app and what it says, which removing a picture should not undo.
            $app->update(['path' => null]);
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

        $r = $this->importer->importMany($templates, $request->boolean('overwrite'));

        return back()->with('success', __('admin.docker_apps.import_done', [
            'done' => $r['done'], 'failed' => $r['failed'], 'skipped' => $r['skipped'], 'none' => $r['none'],
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
}
