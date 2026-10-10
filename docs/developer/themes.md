# Themes

A theme changes how the public site and the client area look. PNLCS ships 16
(**Setup → Appearance**); you can install your own.

## What a theme is

A folder under `themes/`, named by its slug:

```
themes/
└── acme/
    ├── theme.json        required
    ├── screenshot.svg    shown on the Appearance screen
    ├── views/            optional: Blade views that replace the core ones
    └── assets/           optional: CSS, images, fonts
```

## theme.json

```json
{
    "name": "Acme",
    "slug": "acme",
    "version": "1.0.0",
    "author": "Your Company",
    "description": "Our house style.",
    "screenshot": "screenshot.svg",
    "supports": ["dark-mode", "white-label", "section-builder"],
    "colors": {
        "primary": "#1a4d80",
        "accent": "#337ab7"
    }
}
```

- `slug`: lower-case letters, digits, `-` and `_`, starting with a letter or
  digit, at most 50 characters. It is the folder name.
- `requires` (optional): `{"pnlcs": ">=1.3 <2"}`, the PNLCS versions the
  theme works with. An update outside it does not start until the operator
  updates the theme.
- `colors`: applied as the active colour set when the theme is activated.
  Colours you leave out come from the Starter theme, so a theme can set only
  the few it changes. Look at `themes/starter/theme.json` for the full list of
  colour names.

## Replacing views

Files in `views/` take priority over the core views with the same path. To
change the client area's service page, copy
`resources/views/client/services/show.blade.php` to
`themes/acme/views/client/services/show.blade.php` and edit the copy.

Replace as few views as you can: a replaced view no longer receives the
improvements and fixes that updates bring to the original. Colours and the
homepage sections builder (**Setup → Appearance**) cover most changes without
copying a view.

## Assets

Files in `assets/` are served at `/themes/<slug>/assets/`, for example
`/themes/acme/assets/site.css`.

## Starting from an existing theme

Every installed theme can be downloaded as a ZIP from **Setup → Appearance**.
Download the closest one, change the `slug` and `name` in `theme.json`, rename
the folder to the new slug, and build from there.

## Installing

- Copy the folder into `themes/` on the server, or
- upload it as a ZIP on **Setup → Appearance** (the ZIP must contain
  `theme.json`; the files may sit inside one top-level folder).

Then activate it on **Setup → Appearance**. A theme with the slug of a
built-in theme cannot be installed over it, and the active theme cannot be
deleted.

## Page descriptions and link previews

The client layout prints a page's description, its Open Graph and X (Twitter)
card tags (`partials.seo-meta`), and, with the language in the address on, its
address in each language (`partials.locale-alternates`). A page tunes them with
sections:

- `@section('title', ...)`: the title shared links show.
- `@section('meta_description', ...)`: its own description, instead of the
  shop's one from **Setup → General**.
- `@section('og_type', 'article')`: an article rather than a website.
- `@section('seo_own', '1')`: the page writes all of these tags itself (a
  blog with its own image, dates and language versions, for example) and the
  layout prints none of them, so a shared link is not described twice.

A theme that replaces the client layout keeps these two includes in its
`<head>`.

## Keeping your theme through updates

Built-in themes are part of PNLCS and are updated with it; a theme with its
own slug is never touched. Build yours under your own slug rather than editing
a built-in one. (Edits to a built-in theme are not lost either: the updater
merges them with the new version or asks you, like any change to a core file.)

When an update changes a view your theme replaces, **Setup → Updates** lists it
before you update: your copy keeps showing, without the change. New built-in
themes take the `pnlcs-` prefix, so they never take a slug of yours.
