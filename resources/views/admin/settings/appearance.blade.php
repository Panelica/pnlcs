@extends("admin.layouts.app")
@section("title", __("admin.settings.appearance"))

@section("content")
<div class="page-header">
    <h1><i class="fas fa-palette"></i> {{ __('admin.settings.appearance_settings') }}</h1>
</div>

{{-- TAB NAVIGATION --}}
<div style="display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:24px;">
    <button class="appearance-tab active" data-tab="themes" onclick="switchTab('themes', this)">
        <i class="fas fa-swatchbook"></i> {{ __('admin.appearance.tab_themes') }}
    </button>
    <button class="appearance-tab" data-tab="colors" onclick="switchTab('colors', this)">
        <i class="fas fa-tint"></i> {{ __('admin.appearance.tab_colors') }}
    </button>
    <button class="appearance-tab" data-tab="builder" onclick="switchTab('builder', this)">
        <i class="fas fa-puzzle-piece"></i> {{ __('admin.appearance.tab_builder') }}
    </button>
    <button class="appearance-tab" data-tab="whitelabel" onclick="switchTab('whitelabel', this)">
        <i class="fas fa-tag"></i> {{ __('admin.appearance.tab_whitelabel') }}
    </button>
    <button class="appearance-tab" data-tab="darkmode" onclick="switchTab('darkmode', this)">
        <i class="fas fa-moon"></i> {{ __('admin.appearance.tab_darkmode') }}
    </button>
</div>

<style>
    .appearance-tab {
        padding: 12px 24px; background: none; border: none; font-size: 14px; font-weight: 600;
        color: #666; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;
        transition: all 0.2s; display: flex; align-items: center; gap: 8px;
    }
    .appearance-tab:hover { color: #333; }
    .appearance-tab.active { color: var(--theme-primary, #405189); border-bottom-color: var(--theme-primary, #405189); }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    .theme-card { border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: all 0.3s; position: relative; }
    .theme-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
    .theme-card.active { border-color: #337ab7; box-shadow: 0 0 0 3px rgba(51,122,183,0.2); }
    .theme-card__screenshot { width: 100%; height: 180px; object-fit: cover; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .theme-card__screenshot img { width: 100%; height: 100%; object-fit: cover; }
    .theme-card__screenshot--placeholder { color: #94a3b8; font-size: 48px; }
    .theme-card__body { padding: 16px; }
    .theme-card__badge { position: absolute; top: 12px; right: 12px; background: #337ab7; color: #fff; font-size: 10px; padding: 3px 10px; border-radius: 6px; font-weight: 700; text-transform: uppercase; }
    .theme-card__badge--builtin { background: #6366f1; }
    .theme-upload { border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .theme-upload:hover { border-color: #337ab7; background: #f8fafc; }
</style>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- TAB 1: THEMES (WordPress-style grid) --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane active" id="tab-themes">
    {{-- LOGO & FAVICON --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div class="card">
            <div class="card-header">{{ __('common.form.company_name') }}</div>
            <div class="card-body">
                {{-- The name used to live only under the White-label tab, which
                     nobody opens looking for "what is my company called". People
                     upload a logo here, so the name belongs here too. It posts to
                     the same place; the handler now writes only what it is sent. --}}
                <form action="{{ route('admin.settings.appearance.whitelabel') }}" method="POST">
                    @csrf
                    <input type="hidden" name="return_tab" value="themes">
                    <div style="display:flex; gap:8px; align-items:flex-start;">
                        <div style="flex:1;">
                            <input type="text" name="company_name" value="{{ $whitelabel['company_name'] }}" class="form-control" placeholder="e.g. MyHosting">
                            <span style="font-size:11px; color:#999;">{{ __('admin.appearance.replaces_hint') }}</span>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('admin.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">{{ __('admin.appearance.logo') }}</div>
            <div class="card-body">
                @if($logoPath)
                <div style="margin-bottom:12px; padding:12px; background:#f9f9f9; border-radius:6px; text-align:center;">
                    <img src="{{ $logoPath }}" alt="Logo" style="max-height:60px; max-width:200px;">
                </div>
                <form action="{{ route('admin.settings.appearance.logo.remove') }}" method="POST" style="margin-bottom:12px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> {{ __('admin.appearance.remove_logo') }}</button>
                </form>
                @else
                <p style="color:#999; font-size:12px; margin-bottom:12px;">{{ __('admin.appearance.no_logo') }}</p>
                @endif
                <form action="{{ route('admin.settings.appearance.logo') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-control" style="margin-bottom:8px;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> {{ __('admin.appearance.upload_logo') }}</button>
                    <span style="font-size:11px; color:#999; margin-left:6px;">PNG, JPG, SVG, WebP. Max 2MB.</span>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">{{ __('admin.appearance.favicon') }}</div>
            <div class="card-body">
                @if($faviconPath)
                <div style="margin-bottom:12px; padding:12px; background:#f9f9f9; border-radius:6px; display:flex; align-items:center; gap:10px;">
                    <img src="{{ $faviconPath }}" alt="Favicon" style="width:32px; height:32px;">
                    <span style="font-size:12px; color:#555;">{{ __('admin.appearance.current_favicon') }}</span>
                </div>
                <form action="{{ route('admin.settings.appearance.favicon.remove') }}" method="POST" style="margin-bottom:12px;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i> {{ __('admin.appearance.remove_favicon') }}</button>
                </form>
                @else
                <p style="color:#999; font-size:12px; margin-bottom:12px;">{{ __('admin.appearance.no_favicon') }}</p>
                @endif
                <form action="{{ route('admin.settings.appearance.favicon') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml" class="form-control" style="margin-bottom:8px;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> {{ __('admin.appearance.upload_favicon') }}</button>
                    <span style="font-size:11px; color:#999; margin-left:6px;">PNG, ICO, SVG. Max 512KB.</span>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span><i class="fas fa-palette"></i> {{ __('admin.appearance.installed_themes') }}</span>
            <span style="font-size:12px; color:#999;">{{ count($installedThemes) }} {{ __('admin.appearance.themes_installed') }}</span>
        </div>
        <div class="card-body">
            <p style="margin-bottom:20px; color:#666; font-size:13px;">{{ __('admin.appearance.theme_description') }}</p>

            <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:16px; margin-bottom:24px;">
                @foreach($installedThemes as $slug => $themeInfo)
                <div class="theme-card {{ $themeInfo->isActive ? 'active' : '' }}">
                    @if($themeInfo->isActive)
                    <div class="theme-card__badge">{{ __('admin.appearance.active_badge') }}</div>
                    @elseif($themeInfo->isBuiltin)
                    <div class="theme-card__badge theme-card__badge--builtin">{{ __('admin.appearance.built_in_badge') }}</div>
                    @endif

                    <div class="theme-card__screenshot">
                        @if($themeInfo->screenshot)
                        <img src="{{ $themeInfo->screenshot }}" alt="{{ $themeInfo->name }}">
                        @else
                        <div class="theme-card__screenshot--placeholder"><i class="fas fa-image"></i></div>
                        @endif
                    </div>

                    <div class="theme-card__body">
                        <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">{{ $themeInfo->name }}</h3>
                        <p style="font-size:12px; color:#777; margin-bottom:4px;">v{{ $themeInfo->version }} by {{ $themeInfo->author }}</p>
                        <p style="font-size:12px; color:#999; margin-bottom:14px; line-height:1.5;">{{ Str::limit($themeInfo->description, 80) }}</p>

                        @if(!empty($themeInfo->colors))
                        <div style="display:flex; gap:4px; margin-bottom:14px;">
                            @foreach(array_slice($themeInfo->colors, 0, 6) as $colorKey => $colorVal)
                            <div title="{{ $colorKey }}: {{ $colorVal }}" style="width:24px; height:24px; border-radius:6px; background:{{ $colorVal }}; border:1px solid rgba(0,0,0,0.1);"></div>
                            @endforeach
                        </div>
                        @endif

                        @if($themeInfo->isActive)
                        <button class="btn btn-sm btn-default" style="width:100%;" disabled>{{ __('admin.appearance.currently_active') }}</button>
                        @else
                        <div style="display:flex; gap:8px;">
                            <form action="{{ route('admin.settings.appearance.theme.activate') }}" method="POST" style="flex:1;">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $slug }}">
                                <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">
                                    <i class="fas fa-check"></i>{{ __('common.actions.activate') }}</button>
                            </form>
                            <a href="{{ route('admin.settings.appearance.theme.download', $slug) }}" class="btn btn-sm btn-default" title="Download ZIP"><i class="fas fa-download"></i></a>
                            @if(!$themeInfo->isBuiltin)
                            <form action="{{ route('admin.settings.appearance.theme.delete', $slug) }}" method="POST" onsubmit="return confirm('Delete theme {{ $themeInfo->name }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach

                {{-- Upload new theme card --}}
                <div class="theme-card" style="border-style:dashed;">
                    <form action="{{ route('admin.settings.appearance.theme.install') }}" method="POST" enctype="multipart/form-data" id="themeUploadForm">
                        @csrf
                        <label class="theme-upload" for="themeZipInput" style="height:100%; min-height:280px; display:flex; flex-direction:column; align-items:center; justify-content:center; margin:0; border:none;">
                            <i class="fas fa-cloud-upload-alt" style="font-size:48px; color:#d1d5db; margin-bottom:16px;"></i>
                            <span style="font-size:15px; font-weight:700; color:#666; margin-bottom:4px;">{{ __('admin.appearance.upload_theme') }}</span>
                            <span style="font-size:12px; color:#999; margin-bottom:16px;">{{ __('admin.appearance.theme_zip_hint') }}</span>
                            <input type="file" name="theme_zip" id="themeZipInput" accept=".zip" style="display:none;" onchange="document.getElementById('themeUploadForm').submit();">
                            <span class="btn btn-sm btn-outline" style="pointer-events:none;"><i class="fas fa-folder-open"></i> {{ __('admin.appearance.choose_file') }}</span>
                        </label>
                    </form>
                </div>
            </div>

            @if(!empty($installedThemes))
            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:12px 16px; font-size:13px; color:#0369a1;">
                <i class="fas fa-info-circle"></i>
                <strong>{{ __('admin.appearance.themes_how_work') }}</strong> {{ __('admin.appearance.themes_how_work_desc') }}
            </div>
            @endif
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- TAB 2: COLOR PRESETS & CUSTOM TOKENS --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-colors">
    {{-- PRESET CARDS --}}
    <div class="card">
        <div class="card-header">{{ __('admin.appearance.color_presets') }}</div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#666; font-size:13px;">{{ __('admin.appearance.preset_description') }}</p>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                @foreach($presets as $key => $preset)
                <div style="border:2px solid {{ $activePreset === $key ? '#337ab7' : '#e5e7eb' }}; border-radius:8px; padding:20px; position:relative; transition:all 0.2s; {{ $activePreset === $key ? 'box-shadow:0 0 0 3px rgba(51,122,183,0.2);' : '' }}">
                    @if($activePreset === $key)
                    <span style="position:absolute; top:8px; right:8px; background:#337ab7; color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:700;">ACTIVE</span>
                    @endif
                    <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">{{ $preset['name'] }}</h3>
                    <p style="font-size:12px; color:#777; margin-bottom:14px;">{{ $preset['description'] }}</p>
                    <div style="display:flex; gap:4px; margin-bottom:16px; flex-wrap:wrap;">
                        @foreach(['primary','accent','nav_bg','sidebar_bg','welcome_accent','table_header_bg'] as $swatch)
                        <div title="{{ $swatch }}: {{ $preset['colors'][$swatch] }}" style="width:28px; height:28px; border-radius:6px; background:{{ $preset['colors'][$swatch] }}; border:1px solid rgba(0,0,0,0.1);"></div>
                        @endforeach
                    </div>
                    @if($activePreset !== $key)
                    <form action="{{ route('admin.settings.appearance.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="preset" value="{{ $key }}">
                        <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">{{ __('admin.appearance.activate_preset', ['name' => $preset['name']]) }}</button>
                    </form>
                    @else
                    <button class="btn btn-sm btn-default" style="width:100%;" disabled>{{ __('admin.appearance.currently_active') }}</button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- CUSTOM COLORS (Collapsible Groups) --}}
    <div class="card">
        <div class="card-header">{{ __('admin.appearance.custom_colors_tokens') }} ({{ count($tokenLabels) }} tokens)</div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#666; font-size:13px;">{{ __('admin.appearance.custom_tokens_description') }}</p>
            <form action="{{ route('admin.settings.appearance.update') }}" method="POST" id="customColorForm">
                @csrf
                <input type="hidden" name="preset" value="custom">

                @foreach($tokenGroups as $groupName => $keys)
                <details style="margin-bottom:12px; border:1px solid #e5e7eb; border-radius:8px;" {{ $groupName === 'Colors' ? 'open' : '' }}>
                    <summary style="padding:12px 16px; font-weight:700; font-size:14px; cursor:pointer; background:#f9fafb; border-radius:8px;">
                        {{ $groupName }} ({{ count($keys) }} tokens)
                    </summary>
                    <div style="padding:16px; display:grid; grid-template-columns:repeat(4,1fr); gap:12px;">
                        @foreach($keys as $key)
                        <div style="margin-bottom:4px;">
                            <label style="display:block; font-size:11px; font-weight:600; color:#555; margin-bottom:3px;">{{ $tokenLabels[$key] ?? $key }}</label>
                            <div style="display:flex; align-items:center; gap:6px;">
                                @if(in_array($key, $colorKeys))
                                <input type="color" name="colors[{{ $key }}]" value="{{ $activeColors[$key] ?? '#000000' }}"
                                       style="width:36px; height:28px; border:1px solid #ccc; border-radius:4px; padding:0; cursor:pointer;"
                                       onchange="document.getElementById('hex_{{ $key }}').value = this.value">
                                <input type="text" id="hex_{{ $key }}" value="{{ $activeColors[$key] ?? '#000000' }}"
                                       style="width:80px; padding:4px 6px; font-size:11px; border:1px solid #ccc; border-radius:3px; font-family:monospace;"
                                       onchange="let v=this.value; if(/^#[0-9a-fA-F]{6}$/.test(v)){this.previousElementSibling.value=v; document.querySelector('input[name=\'colors[{{ $key }}]\']').value=v}">
                                @else
                                <input type="text" name="colors[{{ $key }}]" value="{{ $activeColors[$key] ?? '' }}"
                                       style="width:100%; padding:4px 8px; font-size:12px; border:1px solid #ccc; border-radius:3px; font-family:monospace;">
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endforeach

                <div style="margin-top:16px; display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('admin.appearance.save_custom_tokens') }}</button>
                    <button type="button" class="btn btn-default" onclick="window.location.reload()"><i class="fas fa-undo"></i> {{ __('admin.appearance.reset_to_preset') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- LIVE PREVIEW --}}
    <div class="card">
        <div class="card-header">{{ __('admin.appearance.live_preview') }}</div>
        <div class="card-body" id="themePreview">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <p style="font-size:11px; font-weight:700; color:#999; text-transform:uppercase; margin-bottom:8px;">{{ __('admin.appearance.nav_sidebar_preview') }}</p>
                    <div style="border-radius:6px; overflow:hidden; border:1px solid #ddd;">
                        <div id="prev-nav" style="background:{{ $activeColors['nav_bg'] }}; padding:10px 16px; color:#fff; font-weight:700; font-size:13px;">
                            PNLCS <span style="font-weight:400; opacity:0.7; margin-left:12px;">{{ __('admin.appearance.preview_dashboard') }}</span>
                        </div>
                        <div style="display:flex;">
                            <div id="prev-sidebar" style="background:{{ $activeColors['sidebar_bg'] }}; width:120px; padding:10px;">
                                <div style="font-size:11px; color:{{ $activeColors['sidebar_text'] }};">{{ __("admin.appearance.preview_menu_item_1") }}</div>
                                <div style="font-size:11px; color:{{ $activeColors['sidebar_text'] }}; margin-top:4px;">{{ __("admin.appearance.preview_menu_item_2") }}</div>
                            </div>
                            <div style="flex:1; padding:10px; background:#fff;">
                                <div id="prev-btn" style="display:inline-block; padding:4px 12px; background:{{ $activeColors['primary'] }}; color:#fff; border-radius:3px; font-size:11px; font-weight:600;">{{ __("admin.appearance.preview_button") }}</div>
                            </div>
                        </div>
                        <div id="prev-footer" style="background:{{ $activeColors['footer_bg'] }}; padding:6px 16px; color:#fff; font-size:10px; opacity:0.8;">{{ __("admin.appearance.preview_footer") }}</div>
                    </div>
                </div>
                <div>
                    <p style="font-size:11px; font-weight:700; color:#999; text-transform:uppercase; margin-bottom:8px;">{{ __('admin.appearance.welcome_hero_preview') }}</p>
                    <div style="border-radius:6px; overflow:hidden; border:1px solid #ddd;">
                        <div id="prev-hero" style="background:linear-gradient(135deg, {{ $activeColors['hero_bg_start'] }}, {{ $activeColors['hero_bg_mid'] }}, {{ $activeColors['hero_bg_end'] }}); padding:28px; text-align:center;">
                            <div style="color:#fff; font-size:18px; font-weight:800; margin-bottom:8px;">{{ __("admin.appearance.preview_your_website") }}</div>
                            <div id="prev-cta" style="display:inline-block; padding:6px 18px; background:{{ $activeColors['welcome_accent'] }}; color:#fff; border-radius:6px; font-size:11px; font-weight:700;">Get Started</div>
                        </div>
                        <div style="padding:12px; background:#fff;">
                            <div id="prev-table-header" style="background:{{ $activeColors['table_header_bg'] }}; color:#fff; padding:4px 10px; font-size:11px; font-weight:600; border-radius:3px;">{{ __("admin.appearance.preview_table_header") }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- TAB 3: HOMEPAGE BUILDER --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-builder">
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <span>{{ __('admin.appearance.homepage_sections') }}</span>
            <a href="/" target="_blank" class="btn btn-sm btn-default"><i class="fas fa-external-link-alt"></i> {{ __('common.actions.preview') }}</a>
        </div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#666; font-size:13px;">{{ __('admin.appearance.sections_description') }}</p>
            <div id="sectionList" style="display:flex; flex-direction:column; gap:8px;">
                @foreach($sections as $section)
                <div class="section-row" data-id="{{ $section->id }}" data-slug="{{ $section->slug }}" style="display:flex; align-items:center; gap:12px; padding:14px 16px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; transition:box-shadow 0.2s;">
                    <div class="drag-handle" style="cursor:grab; color:#999; font-size:18px; padding:0 4px;"><i class="fas fa-grip-vertical"></i></div>
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:14px;">{{ $section->title }}</div>
                        <div style="font-size:12px; color:#999;">{{ $section->slug }}</div>
                    </div>
                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:13px; font-weight:600; color:{{ $section->is_enabled ? '#10b981' : '#ef4444' }};">
                        <input type="checkbox" class="section-toggle" data-id="{{ $section->id }}" {{ $section->is_enabled ? 'checked' : '' }}
                               onchange="toggleSection({{ $section->id }}, this.checked)"
                               style="width:16px; height:16px; cursor:pointer;">
                        {{ $section->is_enabled ? 'ON' : 'OFF' }}
                    </label>
                    <button class="btn btn-sm btn-primary" onclick="editSection('{{ $section->slug }}', '{{ $section->title }}')">
                        <i class="fas fa-edit"></i>{{ __('common.actions.edit') }}</button>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Section Edit Modal --}}
    <div id="sectionEditModal" style="display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; max-width:700px; width:90%; max-height:80vh; overflow-y:auto; padding:24px; box-shadow:0 25px 80px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="modalTitle" style="font-size:18px; font-weight:700;">{{ __('admin.appearance.edit_section') }}</h3>
                <button onclick="closeSectionModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#999;">&times;</button>
            </div>
            <div id="modalContent">
                <p style="color:#999;">{{ __("admin.appearance.preview_loading") }}</p>
            </div>
            <div style="margin-top:20px; display:flex; gap:8px; justify-content:flex-end;">
                <button class="btn btn-default" onclick="closeSectionModal()">{{ __('common.actions.cancel') }}</button>
                <button class="btn btn-primary" onclick="saveSectionContent()"><i class="fas fa-save"></i>{{ __('common.actions.save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- TAB 4: WHITE-LABEL --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-whitelabel">
    <div class="card">
        <div class="card-header">{{ __('admin.appearance.whitelabel_settings') }}</div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#666; font-size:13px;">{{ __('admin.appearance.whitelabel_description') }}</p>
            <form action="{{ route('admin.settings.appearance.whitelabel') }}" method="POST">
                @csrf
                {{-- Tells the handler this is the whole form, so the unticked
                     "remove branding" box is honoured rather than ignored. --}}
                <input type="hidden" name="whitelabel_full_form" value="1">
                <input type="hidden" name="return_tab" value="whitelabel">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">{{ __('common.form.company_name') }}</label>
                        <input type="text" name="company_name" value="{{ $whitelabel['company_name'] }}" class="form-control" placeholder="e.g. MyHosting">
                        <span style="font-size:11px; color:#999;">{{ __('admin.appearance.replaces_hint') }}</span>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">{{ __('admin.appearance.company_url') }}</label>
                        <input type="url" name="company_url" value="{{ $whitelabel['company_url'] }}" class="form-control" placeholder="https://myhosting.com">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">{{ __('admin.appearance.support_email') }}</label>
                        <input type="email" name="support_email" value="{{ $whitelabel['support_email'] }}" class="form-control" placeholder="support@myhosting.com">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:4px;">{{ __('admin.appearance.copyright_text') }}</label>
                        <input type="text" name="copyright" value="{{ $whitelabel['copyright'] }}" class="form-control" placeholder="e.g. MyHosting LLC">
                        <span style="font-size:11px; color:#999;">{{ __('admin.appearance.copyright_hint') }}</span>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="remove_branding" value="1" {{ $whitelabel['remove_branding'] === '1' ? 'checked' : '' }}>
                        <span style="font-size:14px; font-weight:600;">{{ __('admin.appearance.remove_branding') }}</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('admin.appearance.save_whitelabel') }}</button>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- TAB 5: DARK MODE --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane" id="tab-darkmode">
    <div class="card">
        <div class="card-header">{{ __('admin.appearance.dark_mode') }}</div>
        <div class="card-body">
            <p style="margin-bottom:16px; color:#666; font-size:13px;">{{ __('admin.appearance.darkmode_description') }}</p>
            <form action="{{ route('admin.settings.appearance.darkmode') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="dark_mode_enabled" value="1" {{ $darkModeEnabled === '1' ? 'checked' : '' }}>
                        <span style="font-size:14px; font-weight:600;">{{ __('admin.appearance.enable_darkmode') }}</span>
                    </label>
                    <p style="font-size:12px; color:#999; margin-top:4px;">{{ __('admin.appearance.darkmode_hint') }}</p>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <p style="font-size:13px; font-weight:600; margin-bottom:8px;">{{ __('admin.appearance.how_it_works') }}</p>
                    <ul style="font-size:12px; color:#666; padding-left:20px; margin:0;">
                        <li>A moon/sun icon toggle appears in the navigation bar</li>
                        <li>{{ __("admin.appearance.darkmode_bullet_1") }}</li>
                        <li>{{ __("admin.appearance.darkmode_bullet_2") }}</li>
                        <li>{{ __("admin.appearance.darkmode_bullet_3") }}</li>
                        <li>{{ __("admin.appearance.darkmode_bullet_4") }}</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('admin.appearance.save_darkmode') }}</button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- SortableJS for drag-drop --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
// Tab switching
function switchTab(tabName, btn) {
    const pane = document.getElementById('tab-' + tabName);
    if (!pane) { return; }
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.appearance-tab').forEach(t => t.classList.remove('active'));
    pane.classList.add('active');
    (btn || document.querySelector('.appearance-tab[data-tab="' + tabName + '"]')).classList.add('active');

    // Put the tab in the address bar so the screen can be linked to and so a
    // reload does not throw the operator back to the first tab.
    if (history.replaceState) {
        history.replaceState(null, '', '#' + tabName);
    }
}

// Open the tab the link, the redirect or a reload asked for. The setup
// checklist links straight at the field that is still missing; landing on the
// default tab instead is how someone ends up hunting for "company name".
document.addEventListener('DOMContentLoaded', function () {
    const wanted = (window.location.hash || '').replace('#', '')
        || @json(session('appearance_tab', ''));
    if (wanted) { switchTab(wanted, null); }
});

// SortableJS for section reorder
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('sectionList');
    if (el) {
        Sortable.create(el, {
            handle: '.drag-handle',
            animation: 200,
            onEnd: function() {
                const order = [];
                el.querySelectorAll('.section-row').forEach(row => order.push(parseInt(row.dataset.id)));
                fetch('{{ route("admin.settings.appearance.sections.reorder") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ order }),
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        el.style.outline = '2px solid #10b981';
                        setTimeout(() => el.style.outline = 'none', 500);
                    }
                });
            }
        });
    }
});

// Toggle section on/off
function toggleSection(id, enabled) {
    fetch('/admin/settings/appearance/sections/' + id, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ is_enabled: enabled ? 1 : 0 }),
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const row = document.querySelector(`.section-row[data-id="${id}"]`);
            const label = row.querySelector('label');
            label.style.color = enabled ? '#10b981' : '#ef4444';
        }
    });
}

// Edit section content modal
let currentEditSlug = null;

function editSection(slug, title) {
    currentEditSlug = slug;
    document.getElementById('modalTitle').textContent = 'Edit: ' + title;
    document.getElementById('modalContent').innerHTML = '<p style="color:#999;">{{ __("admin.appearance.preview_loading") }}</p>';
    document.getElementById('sectionEditModal').style.display = 'flex';

    fetch('/admin/settings/appearance/sections/' + slug + '/content')
        .then(r => r.json())
        .then(data => {
            let html = '';
            if (data.content && data.content.length > 0) {
                data.content.forEach(item => {
                    const isJson = item.content_type === 'json';
                    const isHtml = item.content_type === 'html';
                    const rows = isJson ? 8 : (isHtml ? 3 : 1);
                    html += `<div style="margin-bottom:12px;">
                        <label style="display:block; font-size:12px; font-weight:700; color:#555; margin-bottom:3px;">
                            ${item.content_key}
                            <span style="font-size:10px; color:#999; font-weight:400;">(${item.content_type})</span>
                        </label>`;
                    if (rows > 1) {
                        html += `<textarea class="form-control content-field" data-key="${item.content_key}" data-type="${item.content_type}"
                                    rows="${rows}" style="font-family:monospace; font-size:12px;">${escapeHtml(item.content_value || '')}</textarea>`;
                    } else {
                        html += `<input type="text" class="form-control content-field" data-key="${item.content_key}" data-type="${item.content_type}"
                                    value="${escapeAttr(item.content_value || '')}" style="font-size:13px;">`;
                    }
                    html += '</div>';
                });
            } else {
                html = '<p style="color:#999;">No editable content for this section.</p>';
            }
            document.getElementById('modalContent').innerHTML = html;
        });
}

function closeSectionModal() {
    document.getElementById('sectionEditModal').style.display = 'none';
    currentEditSlug = null;
}

function saveSectionContent() {
    if (!currentEditSlug) return;
    const fields = document.querySelectorAll('#modalContent .content-field');
    const content = [];
    fields.forEach(f => {
        content.push({
            key: f.dataset.key,
            value: f.value,
            type: f.dataset.type || 'text',
        });
    });

    fetch('/admin/settings/appearance/sections/' + currentEditSlug + '/content', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ content }),
    }).then(r => r.json()).then(data => {
        if (data.success) {
            closeSectionModal();
            const flash = document.createElement('div');
            flash.style.cssText = 'position:fixed;top:20px;right:20px;background:#10b981;color:#fff;padding:12px 20px;border-radius:8px;font-weight:600;z-index:9999;';
            flash.textContent = 'Content saved successfully!';
            document.body.appendChild(flash);
            setTimeout(() => flash.remove(), 2000);
        }
    });
}

function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeAttr(str) {
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>
@endpush
