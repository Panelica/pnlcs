<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SettingController extends Controller
{
    public function general()
    {
        $settings = Setting::where('group', 'general')->pluck('value', 'setting');
        return view('admin.settings.general', compact('settings'));
    }

    public function updateGeneral(Request $request)
    {
        $mailKeys = [
            'MailType', 'SMTPHost', 'SMTPPort', 'SMTPUsername', 'SMTPPassword',
            'SMTPSecurity', 'SystemEmailAddress', 'EmailFromName', 'MailEnabled',
        ];

        // Handle unchecked checkboxes — they are not submitted
        $data = $request->except('_token');

        // Ensure MailEnabled is saved as 0 when unchecked
        if (!isset($data['MailEnabled'])) {
            $data['MailEnabled'] = '0';
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value, 'general');
        }

        return back()->with('success', 'Settings updated.');
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
                'message' => 'No recipient address configured. Set System Email Address first.',
            ]);
        }

        $mailType = $settings['MailType'] ?? 'php_mail';
        $fromAddress = $settings['SystemEmailAddress'] ?? 'noreply@example.com';
        $fromName    = $settings['EmailFromName'] ?? 'PNLCS';

        // Override Laravel mail config dynamically
        if ($mailType === 'smtp') {
            $encryption = ($settings['SMTPSecurity'] ?? 'tls') === 'none' ? null : ($settings['SMTPSecurity'] ?? 'tls');
            config([
                'mail.default'                     => 'smtp',
                'mail.mailers.smtp.host'            => $settings['SMTPHost'] ?? 'localhost',
                'mail.mailers.smtp.port'            => (int)($settings['SMTPPort'] ?? 587),
                'mail.mailers.smtp.username'        => $settings['SMTPUsername'] ?? null,
                'mail.mailers.smtp.password'        => $settings['SMTPPassword'] ?? null,
                'mail.mailers.smtp.encryption'      => $encryption,
                'mail.from.address'                 => $fromAddress,
                'mail.from.name'                    => $fromName,
            ]);
        } else {
            config([
                'mail.default'          => 'sendmail',
                'mail.from.address'     => $fromAddress,
                'mail.from.name'        => $fromName,
            ]);
        }

        try {
            Mail::raw('This is a test email sent from PNLCS to verify your mail configuration is working correctly.', function ($message) use ($toAddress, $fromAddress, $fromName) {
                $message->to($toAddress)
                         ->subject('PNLCS Test Email')
                         ->from($fromAddress, $fromName);
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $toAddress,
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

    public function updateMyAccount(\Illuminate\Http\Request $request)
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

        return back()->with("success", "Account updated successfully.");
    }

    // ═══════════════════════════════════════════════════════
    // APPEARANCE / THEME
    // ═══════════════════════════════════════════════════════

    public function appearance()
    {
        $presets     = ThemeService::getPresets();
        $theme       = ThemeService::getActiveTheme();
        $logoPath    = Setting::get('custom_logo_path', '');
        $faviconPath = Setting::get('custom_favicon_path', '');

        return view('admin.settings.appearance', [
            'presets'      => $presets,
            'activePreset' => $theme['preset'],
            'activeColors' => $theme['colors'],
            'logoPath'     => $logoPath,
            'faviconPath'  => $faviconPath,
        ]);
    }

    public function updateAppearance(Request $request)
    {
        $request->validate([
            'preset' => 'required|string|in:starter,nightforge,lumina,custom',
        ]);

        $preset = $request->input('preset');
        $presets = ThemeService::getPresets();

        if ($preset === 'custom') {
            // Validate each color field
            $colorKeys = array_keys($presets['starter']['colors']);
            $colors = [];
            foreach ($colorKeys as $key) {
                $val = $request->input("colors.{$key}");
                if ($val && preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                    $colors[$key] = strtolower($val);
                } else {
                    // Fallback to starter for invalid/missing colors
                    $colors[$key] = $presets['starter']['colors'][$key];
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

        return back()->with('success', 'Appearance updated successfully.');
    }

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

        return back()->with('success', 'Logo uploaded successfully.');
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

        return back()->with('success', 'Favicon uploaded successfully.');
    }

    public function removeLogo()
    {
        $path = Setting::get('custom_logo_path', '');
        if ($path && file_exists(public_path($path))) {
            unlink(public_path($path));
        }
        Setting::set('custom_logo_path', '', 'appearance');
        ThemeService::clearCache();

        return back()->with('success', 'Logo removed.');
    }
}
