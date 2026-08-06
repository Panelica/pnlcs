<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Project;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An addon screen whose every button leads nowhere.
 *
 * The Project Management addon renders its own list, a "+ New Project" button
 * and a create form, all pointing at /admin/addons/project_management. No such
 * route has ever existed - addon screens are served from
 * /admin/config/addons/modules/{name} - so the button 404s, and the form the
 * operator fills in is thrown away by a 404 on submit.
 *
 * The real project screens are right there in the core: /admin/projects.
 */
function addonOutputHtml(string $name): string
{
    $module = app(AddonManager::class)->discover()->find($name);

    expect($module)->not->toBeNull();

    return $module->output(Request::create('/admin/config/addons/modules/'.$name));
}

/**
 * The links and form targets in a fragment that no route answers.
 *
 * @return array<int, string>
 */
function deadTargetsIn(string $html): array
{
    preg_match_all('/(?:href|action)="([^"]+)"/', $html, $matches);

    $routes = app('router')->getRoutes();
    $dead = [];

    foreach (array_unique($matches[1]) as $target) {
        if (! str_starts_with($target, '/')) {
            continue;
        }

        $path = (string) parse_url($target, PHP_URL_PATH);

        foreach (['GET', 'POST'] as $method) {
            try {
                $routes->match(Request::create($path, $method));

                continue 2;
            } catch (NotFoundHttpException $e) {
                // try the next verb
            } catch (Throwable $e) {
                // a method mismatch still means the path is served
                continue 2;
            }
        }

        $dead[] = $target;
    }

    return $dead;
}

it('does not offer links no route answers', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    Project::create([
        'client_id' => Client::factory()->create()->id,
        'title' => 'Website rebuild',
        'status' => 'in_progress',
    ]);

    expect(deadTargetsIn(addonOutputHtml('project_management')))->toBe([]);
});

it('sends the operator to the project screens that exist', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    $html = addonOutputHtml('project_management');

    expect($html)->toContain('/admin/projects');

    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get('/admin/projects')->assertOk();
    $this->actingAs($admin, 'admin')->get('/admin/projects/create')->assertOk();
});

it('colours the statuses a project can actually have', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    $client = Client::factory()->create();

    foreach (['pending', 'in_progress', 'completed', 'cancelled'] as $status) {
        Project::create([
            'client_id' => $client->id,
            'title' => 'Project '.$status,
            'status' => $status,
        ]);
    }

    $html = addonOutputHtml('project_management');

    preg_match_all('/color:(#[0-9a-f]{3,6});font-weight:600/i', $html, $matches);

    expect($matches[1])->toHaveCount(4)
        ->and(array_unique($matches[1]))->toHaveCount(4);
});

it('still renders with no projects at all', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    $html = addonOutputHtml('project_management');

    expect(deadTargetsIn($html))->toBe([])
        ->and(strtolower($html))->toContain('no projects');
});

it('still names the client and the assigned admin', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    $client = Client::factory()->create(['first_name' => 'Aylin', 'last_name' => 'Kaya']);
    $admin = Admin::factory()->create(['first_name' => 'Deniz', 'last_name' => 'Arslan']);

    Project::create([
        'client_id' => $client->id,
        'admin_id' => $admin->id,
        'title' => 'Website rebuild',
        'status' => 'pending',
    ]);

    $html = addonOutputHtml('project_management');

    expect($html)->toContain('Aylin Kaya')
        ->and($html)->toContain('Deniz Arslan')
        ->and($html)->toContain('Website rebuild');
});

it('links each row to the project it names', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    $project = Project::create([
        'client_id' => Client::factory()->create()->id,
        'title' => 'Website rebuild',
        'status' => 'pending',
    ]);

    $html = addonOutputHtml('project_management');

    expect($html)->toContain('/admin/projects/'.$project->id);

    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/projects/'.$project->id)
        ->assertOk()
        ->assertSee('Website rebuild', false);
});

it('escapes what an operator typed into a project title', function () {
    Setting::set('addon_project_management_active', '1', 'addons');

    Project::create([
        'client_id' => Client::factory()->create()->id,
        'title' => '<script>alert(1)</script>',
        'status' => 'pending',
    ]);

    $html = addonOutputHtml('project_management');

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});
