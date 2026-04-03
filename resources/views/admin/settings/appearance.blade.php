@extends("admin.layouts.app")
@section("title", "Appearance")

@section("content")
<div class="page-header">
    <h1><i class="fas fa-palette"></i> Appearance Settings</h1>
</div>

{{-- ═══ PRESET CARDS ═══ --}}
<div class="card">
    <div class="card-header">Theme Presets</div>
    <div class="card-body">
        <p style="margin-bottom:16px; color:#666; font-size:13px;">Select a preset theme or customize colors below.</p>
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
            @foreach($presets as $key => $preset)
            <div style="border:2px solid {{ $activePreset === $key ? '#337ab7' : '#e5e7eb' }}; border-radius:8px; padding:20px; position:relative; transition:all 0.2s; {{ $activePreset === $key ? 'box-shadow:0 0 0 3px rgba(51,122,183,0.2);' : '' }}">
                @if($activePreset === $key)
                <span style="position:absolute; top:8px; right:8px; background:#337ab7; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:700;">ACTIVE</span>
                @endif
                <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">{{ $preset['name'] }}</h3>
                <p style="font-size:12px; color:#777; margin-bottom:14px;">{{ $preset['description'] }}</p>
                {{-- Color swatches --}}
                <div style="display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap;">
                    @foreach(['primary','accent','nav_bg','sidebar_bg','welcome_accent','table_header_bg'] as $swatch)
                    <div title="{{ $swatch }}: {{ $preset['colors'][$swatch] }}" style="width:28px; height:28px; border-radius:6px; background:{{ $preset['colors'][$swatch] }}; border:1px solid rgba(0,0,0,0.1);"></div>
                    @endforeach
                </div>
                @if($activePreset !== $key)
                <form action="{{ route('admin.settings.appearance.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="preset" value="{{ $key }}">
                    <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">Activate {{ $preset['name'] }}</button>
                </form>
                @else
                <button class="btn btn-sm btn-default" style="width:100%;" disabled>Currently Active</button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══ CUSTOM COLORS ═══ --}}
<div class="card">
    <div class="card-header">Custom Colors</div>
    <div class="card-body">
        <p style="margin-bottom:16px; color:#666; font-size:13px;">Fine-tune individual colors. Changes will set the theme to "Custom".</p>
        <form action="{{ route('admin.settings.appearance.update') }}" method="POST" id="customColorForm">
            @csrf
            <input type="hidden" name="preset" value="custom">
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
                @php
                    $colorLabels = [
                        'primary' => 'Primary',
                        'primary_light' => 'Primary Light',
                        'primary_dark' => 'Primary Dark',
                        'accent' => 'Accent',
                        'accent_dark' => 'Accent Dark',
                        'nav_bg' => 'Navigation BG',
                        'sidebar_bg' => 'Sidebar BG',
                        'sidebar_text' => 'Sidebar Text',
                        'body_bg' => 'Body BG',
                        'card_bg' => 'Card BG',
                        'border_color' => 'Border',
                        'text_color' => 'Text',
                        'muted_color' => 'Muted Text',
                        'footer_bg' => 'Footer BG',
                        'hero_bg_start' => 'Hero Gradient Start',
                        'hero_bg_mid' => 'Hero Gradient Mid',
                        'hero_bg_end' => 'Hero Gradient End',
                        'welcome_accent' => 'Welcome Accent',
                        'welcome_primary' => 'Welcome Primary',
                        'welcome_secondary' => 'Welcome Secondary',
                        'table_header_bg' => 'Table Header BG',
                        'success' => 'Success',
                    ];
                @endphp
                @foreach($colorLabels as $key => $label)
                <div style="margin-bottom:4px;">
                    <label style="display:block; font-size:11px; font-weight:600; color:#555; margin-bottom:3px;">{{ $label }}</label>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="color" name="colors[{{ $key }}]" value="{{ $activeColors[$key] ?? '#000000' }}"
                               style="width:36px; height:28px; border:1px solid #ccc; border-radius:4px; padding:0; cursor:pointer;"
                               onchange="document.getElementById('hex_{{ $key }}').value = this.value">
                        <input type="text" id="hex_{{ $key }}" value="{{ $activeColors[$key] ?? '#000000' }}"
                               style="width:80px; padding:4px 6px; font-size:11px; border:1px solid #ccc; border-radius:3px; font-family:monospace;"
                               onchange="let v=this.value; if(/^#[0-9a-fA-F]{6}$/.test(v)){this.previousElementSibling.value=v; document.querySelector('input[name=\'colors[{{ $key }}]\']').value=v}">
                    </div>
                </div>
                @endforeach
            </div>
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Custom Colors</button>
                <button type="button" class="btn btn-default" onclick="resetToPreset()"><i class="fas fa-undo"></i> Reset to Active Preset</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ LOGO UPLOAD ═══ --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <div class="card">
        <div class="card-header">Logo</div>
        <div class="card-body">
            @if($logoPath)
            <div style="margin-bottom:12px; padding:12px; background:#f9f9f9; border-radius:6px; text-align:center;">
                <img src="{{ $logoPath }}" alt="Logo" style="max-height:60px; max-width:200px;">
            </div>
            <form action="{{ route('admin.settings.appearance.logo.remove') }}" method="POST" style="margin-bottom:12px;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> Remove Logo</button>
            </form>
            @else
            <p style="color:#999; font-size:12px; margin-bottom:12px;">No custom logo uploaded. "PNLCS" text is shown.</p>
            @endif
            <form action="{{ route('admin.settings.appearance.logo') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control" style="margin-bottom:8px;">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload Logo</button>
                <span style="font-size:11px; color:#999; margin-left:6px;">PNG, JPG, SVG, WebP. Max 2MB.</span>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Favicon</div>
        <div class="card-body">
            @if($faviconPath)
            <div style="margin-bottom:12px; padding:12px; background:#f9f9f9; border-radius:6px; display:flex; align-items:center; gap:10px;">
                <img src="{{ $faviconPath }}" alt="Favicon" style="width:32px; height:32px;">
                <span style="font-size:12px; color:#555;">Current favicon</span>
            </div>
            @else
            <p style="color:#999; font-size:12px; margin-bottom:12px;">No custom favicon uploaded.</p>
            @endif
            <form action="{{ route('admin.settings.appearance.favicon') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml" class="form-control" style="margin-bottom:8px;">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload Favicon</button>
                <span style="font-size:11px; color:#999; margin-left:6px;">PNG, ICO, SVG. Max 512KB.</span>
            </form>
        </div>
    </div>
</div>

{{-- ═══ LIVE PREVIEW ═══ --}}
<div class="card">
    <div class="card-header">Live Preview</div>
    <div class="card-body" id="themePreview">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <p style="font-size:11px; font-weight:700; color:#999; text-transform:uppercase; margin-bottom:8px;">Navigation & Sidebar</p>
                <div style="border-radius:6px; overflow:hidden; border:1px solid #ddd;">
                    <div id="prev-nav" style="background:{{ $activeColors['nav_bg'] }}; padding:10px 16px; color:#fff; font-weight:700; font-size:13px;">
                        PNLCS <span style="font-weight:400; opacity:0.7; margin-left:12px;">Dashboard</span>
                    </div>
                    <div style="display:flex;">
                        <div id="prev-sidebar" style="background:{{ $activeColors['sidebar_bg'] }}; width:120px; padding:10px;">
                            <div id="prev-sidebar-text" style="font-size:11px; color:{{ $activeColors['sidebar_text'] }};">Menu Item 1</div>
                            <div style="font-size:11px; color:{{ $activeColors['sidebar_text'] }}; margin-top:4px;">Menu Item 2</div>
                        </div>
                        <div style="flex:1; padding:10px; background:#fff;">
                            <div id="prev-btn" style="display:inline-block; padding:4px 12px; background:{{ $activeColors['primary'] }}; color:#fff; border-radius:3px; font-size:11px; font-weight:600;">Button</div>
                        </div>
                    </div>
                    <div id="prev-footer" style="background:{{ $activeColors['footer_bg'] }}; padding:6px 16px; color:#fff; font-size:10px; opacity:0.8;">Footer</div>
                </div>
            </div>
            <div>
                <p style="font-size:11px; font-weight:700; color:#999; text-transform:uppercase; margin-bottom:8px;">Welcome Page Hero</p>
                <div style="border-radius:6px; overflow:hidden; border:1px solid #ddd;">
                    <div id="prev-hero" style="background:linear-gradient(135deg, {{ $activeColors['hero_bg_start'] }}, {{ $activeColors['hero_bg_mid'] }}, {{ $activeColors['hero_bg_end'] }}); padding:28px; text-align:center;">
                        <div style="color:#fff; font-size:18px; font-weight:800; margin-bottom:8px;">Your Website</div>
                        <div id="prev-cta" style="display:inline-block; padding:6px 18px; background:{{ $activeColors['welcome_accent'] }}; color:#fff; border-radius:6px; font-size:11px; font-weight:700;">Get Started</div>
                    </div>
                    <div style="padding:12px; background:#fff;">
                        <div id="prev-table-header" style="background:{{ $activeColors['table_header_bg'] }}; color:#fff; padding:4px 10px; font-size:11px; font-weight:600; border-radius:3px;">Table Header</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function resetToPreset() {
    // Reload page to get current preset colors
    window.location.reload();
}
</script>
@endpush
