# Translations

PNLCS ships in 30 languages. English, Turkish, Polish, Chinese and German are
complete, and the test suite keeps them complete; the other 25 cover most of
the interface. Every language you improve reaches every PNLCS install.

## Where the texts live

```
lang/
├── en/            the source: every text exists here first
│   ├── admin.php
│   ├── client.php
│   ├── common.php
│   └── ...
├── de/            one folder per language, the same file names
└── ...
```

Each file returns an array of `key => text`. Keys are either nested or written
flat with dots; both are read the same way:

```php
return [
    'dashboard' => ['welcome' => 'Welcome back, :name'],   // nested
    'dashboard.welcome_back' => 'Welcome back',            // flat
];
```

!!! warning "When the same key exists both ways, the flat one wins"
    If a file has `'dashboard.welcome' => '...'` and also
    `'dashboard' => ['welcome' => '...']`, the flat line is the one shown. Edit
    the line that is actually used, or remove the duplicate.

Texts an operator edits on **Setup → Languages** are stored in the database
and take priority over the files, so they survive updates.

## Rules every translation follows

- Keep every **placeholder** exactly: `:name`, `:count`, `:amount` and the like
  are filled in by the code.
- Keep **HTML** the English text has (`<strong>`, `<code>`, links) in the same
  order.
- Keep the **form of address** the language already uses everywhere: German
  and Turkish address the reader formally (`Sie`, `siz`), Polish informally.
- Product and protocol names (PNLCS, SMTP, DNS, IBAN) stay as they are.

## Send a translation

Either way works:

- **A pull request** changing `lang/<code>/*.php`. Your name stays on it in
  the project history. The translation tests must pass:
  `php artisan test --filter=Translation`.
- **An export from your own install.** On **Setup → Languages → Translate →
  Export JSON**, translate the file, and send it to
  [info@panelica.com](mailto:info@panelica.com) or attach it to a
  [GitHub issue](https://github.com/Panelica/pnlcs/issues). We review it,
  merge it and credit you by name.

## A new language

Open an issue first: a language is added to the list the installer seeds and
to the tests that measure coverage, and a partial translation needs a plan to
become complete.
