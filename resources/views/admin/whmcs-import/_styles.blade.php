{{-- The import screens' own look: wi-* classes, so the panel's forced .card
     and .data-table rules do not reach them. Colours follow the theme. --}}
<style>
    .wi{--wi-primary:var(--theme-primary,#1a4d80);--wi-accent:var(--theme-accent,#337ab7);--wi-border:#e6e9ef;--wi-muted:#6b7280;--wi-soft:#f6f8fb;--wi-radius:12px;color:#1f2937}
    .wi *{box-sizing:border-box}
    .wi-hero{position:relative;overflow:hidden;border-radius:var(--wi-radius);padding:26px 28px;margin-bottom:20px;color:#fff;background:linear-gradient(120deg,var(--wi-primary) 0%,var(--wi-accent) 100%);box-shadow:0 10px 30px -18px rgba(15,23,42,.55)}
    .wi-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.08)}
    .wi-hero__row{position:relative;z-index:1;display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap}
    .wi-hero__icon{flex:0 0 auto;width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25)}
    .wi-hero__body{flex:1 1 320px;min-width:0}
    .wi-hero h1{border:0 !important;padding:0 !important;margin:0 0 6px;font-size:22px;font-weight:700;color:#fff;letter-spacing:-.01em}
    .wi-hero p{margin:0;font-size:14px;line-height:1.55;color:rgba(255,255,255,.88);max-width:760px}
    .wi-hero__back{position:relative;z-index:1;margin-inline-start:auto;align-self:center}
    .wi-hero__back a{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;font-size:13px;font-weight:600;color:#fff;text-decoration:none;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25)}
    .wi-hero__back a:hover{background:rgba(255,255,255,.24)}
    .wi-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
    .wi-chip{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:#fff}

    .wi-steps{display:flex;align-items:center;gap:0;margin:0 0 20px;padding:0;list-style:none;background:#fff;border:1px solid var(--wi-border);border-radius:var(--wi-radius);padding:14px 18px;flex-wrap:wrap;row-gap:10px}
    .wi-step{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:var(--wi-muted)}
    .wi-step__dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid #d5dbe5;background:#fff;color:var(--wi-muted)}
    .wi-step--current{color:#111827}.wi-step--current .wi-step__dot{border-color:var(--wi-primary);color:var(--wi-primary);box-shadow:0 0 0 4px color-mix(in srgb,var(--wi-primary) 14%,transparent)}
    .wi-step--done{color:#15803d}.wi-step--done .wi-step__dot{border-color:#16a34a;background:#16a34a;color:#fff}
    .wi-steps__line{flex:1 1 40px;min-width:24px;height:2px;margin:0 14px;background:#e5e9f0}
    .wi-steps__line--done{background:#16a34a}

    .wi-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:20px;align-items:start}
    .wi-card{background:#fff;border:1px solid var(--wi-border);border-radius:var(--wi-radius);box-shadow:0 1px 2px rgba(15,23,42,.04);margin-bottom:20px;overflow:hidden}
    .wi-card__head{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--wi-border)}
    .wi-card__head h2{margin:0;font-size:15px;font-weight:700;color:#111827}
    .wi-card__head .wi-card__icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--wi-primary) 10%,#fff);color:var(--wi-primary);font-size:15px;flex:0 0 auto}
    .wi-card__head .wi-card__aside{margin-inline-start:auto;font-size:12px;color:var(--wi-muted);font-weight:600}
    .wi-card__body{padding:18px 20px}
    .wi-section-label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--wi-muted);margin:0 0 10px}
    .wi-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}
    .wi-field label{display:block;font-size:12px;font-weight:600;color:#374151;margin:0 0 6px}
    .wi-field input,.wi-field select{width:100%;height:40px;padding:8px 12px;font-size:14px;border:1px solid #d5dbe5;border-radius:8px;background:#fff;color:#111827;transition:border-color .15s,box-shadow .15s}
    .wi-field input:focus,.wi-field select:focus{outline:none;border-color:var(--wi-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--wi-primary) 15%,transparent)}
    .wi-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:16px;border-top:1px dashed var(--wi-border)}
    .wi .btn{border-radius:8px;padding:9px 16px;font-size:13px}
    .wi .btn-sm{padding:6px 12px;font-size:12px}
    .wi-btn-outline{background:#fff !important;color:var(--wi-primary) !important;border:1px solid color-mix(in srgb,var(--wi-primary) 35%,#fff) !important}
    .wi-btn-outline:hover{background:color-mix(in srgb,var(--wi-primary) 6%,#fff) !important}
    .wi-btn-danger-ghost{background:transparent !important;color:#b91c1c !important;border:1px solid transparent !important}
    .wi-btn-danger-ghost:hover{background:#fef2f2 !important;border-color:#fecaca !important}

    .wi-conn{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--wi-border);border-radius:10px;margin-bottom:10px;background:#fff;transition:border-color .15s,box-shadow .15s}
    .wi-conn:hover{border-color:color-mix(in srgb,var(--wi-primary) 40%,#fff);box-shadow:0 4px 14px -10px rgba(15,23,42,.4)}
    .wi-conn__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--wi-soft);color:var(--wi-primary);flex:0 0 auto}
    .wi-conn__body{flex:1;min-width:0}
    .wi-conn__name{font-weight:700;font-size:14px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .wi-conn__meta{font-size:12px;color:var(--wi-muted);overflow-wrap:anywhere}
    .wi-empty{text-align:center;padding:22px 12px;color:var(--wi-muted);font-size:13px}
    .wi-empty i{display:block;font-size:24px;margin-bottom:8px;color:#c4cbd6}

    .wi-table{width:100%;border-collapse:collapse;font-size:13px}
    .wi-table th{text-align:left;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--wi-muted);background:var(--wi-soft);padding:10px 16px;border-bottom:1px solid var(--wi-border)}
    .wi-table td{padding:12px 16px;border-bottom:1px solid #eef1f5;vertical-align:middle}
    .wi-table tr:last-child td{border-bottom:none}
    .wi-table tbody tr:hover td{background:#fafbfd}
    .wi-table .wi-num{text-align:right;font-variant-numeric:tabular-nums}
    [dir=rtl] .wi-table th{text-align:right}[dir=rtl] .wi-table .wi-num{text-align:left}
    .wi-pill{display:inline-flex;align-items:center;justify-content:center;min-width:30px;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums}
    .wi-pill--green{background:#dcfce7;color:#166534}.wi-pill--blue{background:#dbeafe;color:#1e40af}.wi-pill--amber{background:#fef3c7;color:#92400e}.wi-pill--red{background:#fee2e2;color:#991b1b}.wi-pill--gray{background:#f1f5f9;color:#475569}
    .wi-tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:var(--wi-soft);border:1px solid var(--wi-border);color:#334155}

    .wi-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
    .wi-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:1px solid var(--wi-border);background:#fff;font-size:13px;font-weight:600;color:#374151;text-decoration:none;transition:all .15s}
    .wi-tab:hover{border-color:color-mix(in srgb,var(--wi-primary) 40%,#fff);color:var(--wi-primary)}
    .wi-tab--active{background:var(--wi-primary);border-color:var(--wi-primary);color:#fff !important;box-shadow:0 6px 16px -10px var(--wi-primary)}
    .wi-meter{display:flex;align-items:center;gap:10px}
    .wi-meter__bar{display:block;width:120px;height:6px;border-radius:999px;background:#e9edf3;overflow:hidden}
    .wi-meter__fill{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--wi-primary),var(--wi-accent))}

    .wi-map td{padding:10px 16px}
    .wi-map tr.is-skipped td{opacity:.6}
    .wi-map tr.is-mapped td:first-child{box-shadow:inset 3px 0 0 var(--wi-primary)}
    [dir=rtl] .wi-map tr.is-mapped td:first-child{box-shadow:inset -3px 0 0 var(--wi-primary)}
    .wi-map select{height:38px;border:1px solid #d5dbe5;border-radius:8px;padding:6px 10px;font-size:13px;width:100%;background:#fff}
    .wi-map select:focus{outline:none;border-color:var(--wi-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--wi-primary) 15%,transparent)}
    .wi-arrow{color:#9aa4b2;text-align:center}
    [dir=rtl] .wi-arrow i{transform:scaleX(-1)}
    .wi-type{font-size:11px;color:var(--wi-muted);margin-left:6px}
    .wi-badge-suggest{display:inline-flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;font-weight:600;color:#7c3aed}

    .wi-fold{border:1px solid var(--wi-border);border-radius:var(--wi-radius);background:#fff;margin-bottom:20px;overflow:hidden}
    .wi-fold>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:12px;padding:16px 20px;font-weight:700;font-size:15px;color:#111827}
    .wi-fold>summary::-webkit-details-marker{display:none}
    .wi-fold>summary .wi-chev{margin-inline-start:auto;color:var(--wi-muted);transition:transform .2s}
    .wi-fold[open]>summary .wi-chev{transform:rotate(180deg)}
    .wi-fold__body{padding:0 20px 18px}
    .wi-fold__hint{font-size:12px;color:var(--wi-muted);margin:0 0 12px;line-height:1.5}
    .wi-fold input{width:100%;height:36px;border:1px solid #d5dbe5;border-radius:8px;padding:6px 10px;font-size:13px}

    .wi-bar{position:sticky;bottom:12px;z-index:20;display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;margin:0 0 20px;background:rgba(255,255,255,.96);backdrop-filter:blur(6px);border:1px solid var(--wi-border);border-radius:var(--wi-radius);box-shadow:0 12px 30px -16px rgba(15,23,42,.45)}
    .wi-bar__spacer{flex:1}
    .wi-bar input{height:36px;width:220px;max-width:100%;border:1px solid #d5dbe5;border-radius:8px;padding:6px 10px;font-size:13px}

    .wi-problems{border:1px solid #fecaca;background:#fff7f7;border-radius:var(--wi-radius);padding:16px 20px;margin-bottom:20px;color:#991b1b}
    .wi-problems h2{margin:0 0 8px;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px}
    .wi-problems ul{margin:0;padding-left:20px;font-size:13px;line-height:1.7}
    .wi-note{display:flex;gap:10px;align-items:flex-start;border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:18px}

    .wi-record{padding:18px 20px;border-bottom:1px solid var(--wi-border)}
    .wi-record:last-child{border-bottom:none}
    .wi-record__title{display:flex;align-items:center;gap:10px;font-weight:700;font-size:14px;margin-bottom:12px;color:#111827}
    .wi-record__num{width:24px;height:24px;border-radius:50%;background:var(--wi-soft);color:var(--wi-muted);font-size:11px;display:flex;align-items:center;justify-content:center}
    .wi-pair{display:grid;grid-template-columns:minmax(0,1fr) 36px minmax(0,1fr);gap:12px;align-items:stretch}
    .wi-pair__side{border-radius:10px;padding:12px 14px;font-size:13px;line-height:1.7}
    .wi-pair__side--src{background:var(--wi-soft);border:1px solid var(--wi-border)}
    .wi-pair__side--dst{background:color-mix(in srgb,var(--wi-primary) 6%,#fff);border:1px solid color-mix(in srgb,var(--wi-primary) 22%,#fff)}
    .wi-pair__label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--wi-muted);margin-bottom:6px}
    .wi-pair__mid{display:flex;align-items:center;justify-content:center;color:#9aa4b2}
    [dir=rtl] .wi-pair__mid i{transform:scaleX(-1)}
    .wi-kv{display:flex;gap:8px;overflow-wrap:anywhere}.wi-kv b{font-weight:600;color:#475569;flex:0 0 auto}

    .wi-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:20px}
    .wi-stat{background:#fff;border:1px solid var(--wi-border);border-radius:var(--wi-radius);padding:16px 18px;position:relative;overflow:hidden}
    .wi-stat::before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:var(--c,#94a3b8)}
    [dir=rtl] .wi-stat::before{inset:0 0 0 auto}
    .wi-stat__label{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;color:var(--wi-muted)}
    .wi-stat__label i{color:var(--c,#94a3b8)}
    .wi-stat__value{font-size:28px;font-weight:800;margin-top:6px;color:#111827;font-variant-numeric:tabular-nums;line-height:1.1}
    .wi-meta{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:13px;color:var(--wi-muted);margin:-6px 0 18px}
    .wi-meta span{display:inline-flex;align-items:center;gap:6px}

    @media (max-width:1100px){.wi-grid{grid-template-columns:minmax(0,1fr)}.wi-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media (max-width:720px){.wi-bar{position:static}.wi-card__head .wi-card__aside{margin-inline-start:0;width:100%}.wi-fields{grid-template-columns:minmax(0,1fr)}.wi-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.wi-pair{grid-template-columns:minmax(0,1fr)}.wi-pair__mid{transform:rotate(90deg)}.wi-hero{padding:20px}.wi-steps__line{display:none}.wi-steps{gap:14px}.wi-bar input{width:100%}}
</style>
