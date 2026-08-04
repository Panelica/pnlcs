<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageContent;
use App\Models\HomepageSection;
use App\Models\Setting;
use App\Services\ThemeManager;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SettingController extends Controller
{
    public function general()
    {
        $settings = Setting::where('group', 'general')->pluck('value', 'setting');

        return view('admin.settings.general', [
            'settings' => $settings,
            'mailTransport' => (string) config('mail.default'),
        ]);
    }

    /**
     * The settings this form owns.
     *
     * Anything else in the request is ignored. It used to be stored, so a
     * stray field became a setting of its own, and a field named after a
     * setting belonging to another screen was overwritten and moved into
     * "general" - Setting::set() writes the group as well as the value, and
     * the screen that owns it looks it up by group.
     */
    private const GENERAL_KEYS = [
        'ActiveClientAreaTemplate', 'Address', 'AdminDir', 'CompanyCity', 'CompanyName',
        'Country', 'DateFormat', 'DefaultLanguage', 'Domain', 'Email', 'EmailFromName',
        'LateFeeAmount', 'LateFeeMinDays', 'LateFeeType',
        'MailEnabled', 'MailType', 'MaintenanceMode', 'OrderFormDisplayedOn', 'PhoneNumber',
        'SMTPHost', 'SMTPPassword', 'SMTPPort', 'SMTPSecurity', 'SMTPUsername',
        'SystemEmailAddress', 'TaxID', 'Timezone',
    ];

    public function updateGeneral(Request $request)
    {
        $data = $request->only(self::GENERAL_KEYS);

        // An unticked checkbox is absent from the request, not false.
        if (! isset($data['MailEnabled'])) {
            $data['MailEnabled'] = '0';
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value, 'general');
        }

        return back()->with('success', __('messages.success.settings_updated'));
    }

    public function testEmail(Request $request)
    {
        $settings = Setting::where('group', 'general')->pluck('value', 'setting')->toArray();

        $toAddress = $settings['SystemEmailAddress'] ?? null;
        if (empty($toAddress)) {
            $toAddress = $settings['Email'] ?? null;
        }
        if (empty($toAddress)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.error.no_recipient_address_configured'),
            ]);
        }

        // The same resolver the application boots with. This used to be a
        // second copy that handled PHP mail when the real one did not, so the
        // button could succeed down a road no real email ever travelled.
        $transport = \App\Support\MailTransport::configure();

        $fromAddress = $settings['SystemEmailAddress'] ?? 'noreply@example.com';
        $fromName = $settings['EmailFromName'] ?? 'PNLCS';

        try {
            Mail::raw(__('messages.email.test_body'), function ($message) use ($toAddress, $fromAddress, $fromName) {
                $message->to($toAddress)
                         ->subject(__('messages.email.test_subject'))
                         ->from($fromAddress, $fromName);
            });

            return response()->json([
                'success' => true,
                // Which road it went down. "log" means it went into a file and
                // nobody received it, which is worth saying out loud.
                'transport' => $transport,
                'message' => __('messages.email.test_sent', ['address' => $toAddress])
                    .' ('.__('admin.settings.sending_via', ['transport' => $transport]).')',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function myAccount()
    {
        $admin = auth("admin")->user();
        return view("admin.settings.my-account", compact("admin"));
    }

    public function updateMyAccount(Request $request)
    {
        $admin = auth("admin")->user();

        $request->validate([
            "first_name"   => "required|string|max:100",
            "last_name"    => "required|string|max:100",
            "email"        => "required|email|unique:admins,email," . $admin->id,
            "signature"    => "nullable|string|max:1000",
            "new_password" => "nullable|min:8|confirmed",
        ]);

        $data = $request->only(["first_name", "last_name", "email", "signature"]);

        if ($request->filled("new_password")) {
            $data["password"] = bcrypt($request->new_password);
        }

        $admin->update($data);

        return back()->with("success", __("admin.messages.account_updated"));
    }

    // ═══════════════════════════════════════════════════════
    // APPEARANCE / THEME
    // ═══════════════════════════════════════════════════════

    public function appearance(ThemeManager $themeManager)
    {
        $presets     = ThemeService::getPresets();
        $theme       = ThemeService::getActiveTheme();
        $logoPath    = Setting::get('custom_logo_path', '');
        $faviconPath = Setting::get('custom_favicon_path', '');

        // Homepage sections for builder tab
        $sections = HomepageSection::orderBy('sort_order')->get();

        // White-label settings
        $whitelabel = [
            'company_name'  => Setting::get('whitelabel_company_name', ''),
            'company_url'   => Setting::get('whitelabel_company_url', ''),
            'support_email' => Setting::get('whitelabel_support_email', ''),
            'copyright'     => Setting::get('whitelabel_copyright', ''),
            'remove_branding' => Setting::get('whitelabel_remove_branding', '0'),
        ];

        // Dark mode
        $darkModeEnabled = Setting::get('dark_mode_enabled', '0');

        // Installed themes (WordPress-style)
        $installedThemes = $themeManager->getInstalled();

        return view('admin.settings.appearance', [
            'presets'           => $presets,
            'activePreset'      => $theme['preset'],
            'activeColors'      => $theme['colors'],
            'logoPath'          => $logoPath,
            'faviconPath'       => $faviconPath,
            'sections'          => $sections,
            'whitelabel'        => $whitelabel,
            'darkModeEnabled'   => $darkModeEnabled,
            'tokenGroups'       => ThemeService::getTokenGroups(),
            'tokenLabels'       => ThemeService::getTokenLabels(),
            'colorKeys'         => ThemeService::getColorKeys(),
            'installedThemes'   => $installedThemes,
        ]);
    }

    public function updateAppearance(Request $request)
    {
        $presetNames = array_keys(ThemeService::getPresets());
        $presetNames[] = 'custom';

        $request->validate([
            'preset' => 'required|string|in:' . implode(',', $presetNames),
        ]);

        $preset = $request->input('preset');
        $presets = ThemeService::getPresets();

        if ($preset === 'custom') {
            $allKeys = [];
            foreach (ThemeService::getTokenGroups() as $keys) {
                $allKeys = array_merge($allKeys, $keys);
            }
            $colorKeys = ThemeService::getColorKeys();
            $colors = [];
            foreach ($allKeys as $key) {
                $val = $request->input("colors.{$key}");
                if ($val) {
                    if (in_array($key, $colorKeys) && preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                        $colors[$key] = strtolower($val);
                    } else {
                        $colors[$key] = $val;
                    }
                } else {
                    $colors[$key] = $presets['starter']['colors'][$key] ?? '';
                }
            }
            Setting::set('active_theme_preset', 'custom', 'appearance');
            Setting::set('active_theme', json_encode($colors), 'appearance');
        } else {
            $colors = $presets[$preset]['colors'];
            Setting::set('active_theme_preset', $preset, 'appearance');
            Setting::set('active_theme', json_encode($colors), 'appearance');
        }

        ThemeService::clearCache();

        return back()->with('success', __('messages.success.appearance_updated_successfully'));
    }

    // ═══════════════════════════════════════════════════════
    // THEME CRUD (WordPress-style)
    // ═══════════════════════════════════════════════════════

    public function activateTheme(Request $request, ThemeManager $themeManager)
    {
        $request->validate(['slug' => 'required|string|max:50']);
        $slug = $request->input('slug');

        if ($themeManager->activate($slug)) {
            return back()->with('success', __('admin.messages.theme_activated', ['name' => $slug]));
        }

        return back()->with('error', __('messages.error.theme_not_found_or_invalid'));
    }

    public function installTheme(Request $request, ThemeManager $themeManager)
    {
        $request->validate([
            'theme_zip' => 'required|file|mimes:zip|max:20480',
        ]);

        $result = $themeManager->install($request->file('theme_zip'));

        if ($result['success']) {
            return back()->with('success', __('admin.messages.theme_installed', ['name' => $result['name']]));
        }

        return back()->with('error', $result['message']);
    }

    public function deleteTheme(string $slug, ThemeManager $themeManager)
    {
        $result = $themeManager->delete($slug);

        if ($result['success']) {
            return back()->with('success', __('messages.success.theme_deleted'));
        }

        return back()->with('error', $result['message']);
    }


    public function downloadTheme(string $slug, ThemeManager $themeManager)
    {
        $themes = $themeManager->getInstalled();
        if (!isset($themes[$slug])) {
            return back()->with('error', __('messages.error.theme_not_found'));
        }

        $themePath = base_path('themes/' . $slug);
        $zipName = $slug . '-v' . ($themes[$slug]->version ?? '1.0.0') . '.zip';
        $tmpZip = storage_path('app/tmp/' . $zipName);

        if (!is_dir(dirname($tmpZip))) {
            mkdir(dirname($tmpZip), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', __('messages.error.could_not_create_zip_archive'));
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $slug . '/' . substr($filePath, strlen($themePath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return response()->download($tmpZip, $zipName)->deleteFileAfterSend(true);
    }
    // ═══════════════════════════════════════════════════════
    // LOGO & FAVICON
    // ═══════════════════════════════════════════════════════

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $file = $request->file('logo');
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('branding'), $filename);

        Setting::set('custom_logo_path', '/branding/' . $filename, 'appearance');
        ThemeService::clearCache();

        return back()->with('success', __('messages.success.logo_uploaded'));
    }

    public function uploadFavicon(Request $request)
    {
        $request->validate([
            'favicon' => 'required|image|mimes:png,ico,svg|max:512',
        ]);

        $file = $request->file('favicon');
        $filename = 'favicon_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('branding'), $filename);

        Setting::set('custom_favicon_path', '/branding/' . $filename, 'appearance');
        ThemeService::clearCache();

        return back()->with('success', __('messages.success.favicon_uploaded'));
    }

    public function removeLogo()
    {
        $path = Setting::get('custom_logo_path', '');
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
        Setting::set('custom_logo_path', '', 'appearance');
        ThemeService::clearCache();

        return back()->with('success', __('messages.success.logo_removed'));
    }

    // ═══════════════════════════════════════════════════════
    // HOMEPAGE BUILDER
    // ═══════════════════════════════════════════════════════

    public function removeFavicon()
    {
        $path = Setting::get("custom_favicon_path", "");
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
        Setting::set("custom_favicon_path", "", "appearance");
        ThemeService::clearCache();

        return back()->with("success", __("admin.messages.favicon_removed"));
    }

    public function sectionsList()
    {
        $sections = HomepageSection::orderBy('sort_order')->get();
        return response()->json(['sections' => $sections]);
    }

    public function sectionsReorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:homepage_sections,id',
        ]);

        foreach ($request->input('order') as $index => $id) {
            HomepageSection::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function sectionUpdate(Request $request, HomepageSection $section)
    {
        $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'config' => 'sometimes|nullable|array',
        ]);

        if ($request->has('is_enabled')) {
            $section->is_enabled = (bool) $request->input('is_enabled');
        }
        if ($request->has('config')) {
            $section->config = $request->input('config');
        }

        $section->save();

        return response()->json(['success' => true, 'section' => $section]);
    }

    public function sectionContent(string $slug)
    {
        $section = HomepageSection::where('slug', $slug)->firstOrFail();
        $content = HomepageContent::where('section_slug', $slug)->get();

        return response()->json([
            'section' => $section,
            'content' => $content,
        ]);
    }

    public function sectionContentSave(Request $request, string $slug)
    {
        $section = HomepageSection::where('slug', $slug)->firstOrFail();

        $request->validate([
            'content' => 'required|array',
            'content.*.key' => 'required|string',
            'content.*.value' => 'nullable|string',
            'content.*.type' => 'sometimes|string|in:text,html,json',
        ]);

        foreach ($request->input('content') as $item) {
            HomepageContent::updateOrCreate(
                [
                    'section_slug' => $slug,
                    'content_key' => $item['key'],
                ],
                [
                    'content_value' => $item['value'] ?? '',
                    'content_type' => $item['type'] ?? 'text',
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════
    // WHITE-LABEL
    // ═══════════════════════════════════════════════════════

    public function whitelabelSave(Request $request)
    {
        $request->validate([
            'company_name'  => 'nullable|string|max:100',
            'company_url'   => 'nullable|url|max:255',
            'support_email' => 'nullable|email|max:255',
            'copyright'     => 'nullable|string|max:200',
            'remove_branding' => 'sometimes|boolean',
        ]);

        Setting::set('whitelabel_company_name', $request->input('company_name', ''), 'whitelabel');
        Setting::set('whitelabel_company_url', $request->input('company_url', ''), 'whitelabel');
        Setting::set('whitelabel_support_email', $request->input('support_email', ''), 'whitelabel');
        Setting::set('whitelabel_copyright', $request->input('copyright', ''), 'whitelabel');
        Setting::set('whitelabel_remove_branding', $request->input('remove_branding', '0'), 'whitelabel');

        ThemeService::clearCache();

        return back()->with('success', __('messages.success.whitelabel_saved'));
    }

    // ═══════════════════════════════════════════════════════
    // DARK MODE
    // ═══════════════════════════════════════════════════════

    public function darkModeSave(Request $request)
    {
        $request->validate([
            'dark_mode_enabled' => 'sometimes|boolean',
        ]);

        Setting::set('dark_mode_enabled', $request->input('dark_mode_enabled', '0'), 'appearance');
        ThemeService::clearCache();

        return back()->with('success', __('messages.success.darkmode_saved'));
    }
}
