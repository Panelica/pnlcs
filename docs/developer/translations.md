# Translations

PNLCS ships in 30 languages. English, Turkish, Polish, Chinese and German are
complete, and the test suite keeps them complete. The other 25 are community
languages: they are translated as people contribute, and any text a community
language does not have yet is shown in English - never as a raw key, and never
as a broken page. Every language you improve reaches every PNLCS install.

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

Every text the product shows lives in these files. Texts an operator edits on
**Setup → Languages** are stored in the database and take priority over the
files, so they survive updates; nothing the project ships is kept in the
database.

## Rules every translation follows

- Keep every **placeholder** exactly: `:name`, `:count`, `:amount` and the like
  are filled in by the code.
- Keep **HTML** the English text has (`<strong>`, `<code>`, links) in the same
  order.
- Keep the **form of address** the language already uses everywhere: German
  and Turkish address the reader formally (`Sie`, `siz`), Polish informally.
- Product and protocol names (PNLCS, SMTP, DNS, IBAN) stay as they are.

## What the tests check

Run them before you send anything: `php artisan test --filter=Translation`.

**Every language**, complete or community:

- each file loads and returns its texts;
- every placeholder the English text has is in the translation;
- the translation has exactly the tags the English has - no new tags, no new
  attributes;
- no text is blank (a missing text falls back to English, a blank one shows
  nothing).

**The complete languages** (Turkish, German, Polish, Chinese) also must have
every English key, and may leave a text identical to English only when it is
listed (below). A pull request that adds an English text writes it in these
four too, or says in the description which ones it could not.

**Community languages** are not required to have every key. The tests print
how many texts each one still shows in English; that number is a to-do list,
not a reason to fail.

## Texts that stay the same as English

Some texts are the same in a language on purpose: product names (Stripe,
PayPal, phpMyAdmin), protocols and units (Port, TTL, `:value vCPU`), and words
the language spells the same (German *Status*, *Domain*, *Name*). The
translation progress on **Setup → Languages** counts a text that is identical
to English as not yet translated - that is how it tells a translated language
from a copy of the English - so these are listed one by one in
`database/data/same_as_english.php`:

```php
'de' => [
    'admin.clients.status',     // Status
    'client.hosting.dns.zone',  // Zone
],
```

A text with no words in it (`SSL`, `:count`, `%`) needs no line. For a
complete language the list must be exact: a text identical to English that is
not listed fails the tests, and so does a listed text that has since been
translated. A community language may add its own section; its lines are only
checked for still being true.

## When a language becomes complete

When a community language has every English key and every text identical to
English is listed, open a pull request that says so. A maintainer moves it
from the community list to the complete list in
`tests/Feature/TranslationParityTest.php`, after which new English texts have
to be written in it as well.

## Send a translation

Either way works:

- **A pull request** changing `lang/<code>/*.php`. Your name stays on it in
  the project history, and you are listed under **Translators** in the
  [README](https://github.com/Panelica/pnlcs#translators). The translation tests must pass:
  `php artisan test --filter=Translation`.
- **An export from your own install.** On **Setup → Languages → Translate →
  Export JSON**, translate the file, and send it to
  [info@panelica.com](mailto:info@panelica.com) or attach it to a
  [GitHub issue](https://github.com/Panelica/pnlcs/issues). We review it,
  merge it and credit you by name, in the changelog and under
  **Translators** in the README.

## From an install into the files

Texts saved in the editor or produced by **Translate with AI** live in that
install's database. To turn them into a contribution, run on a checkout of the
repository:

```bash
php artisan pnlcs:lang-write tr --dry-run   # what would change
php artisan pnlcs:lang-write tr             # write into lang/tr/*.php
php artisan pnlcs:lang-write tr --clear     # and drop the rows now in the files
```

Only the values that differ from the files are written, in place: comments,
order and layout stay as they are, so the diff shows just the changed texts.
A key the file does not have yet is added at its end. Use `--group=admin` for
one file. Then run the translation tests, commit and open a pull request.

## A new language

Open an issue first: a language is added to the list the installer seeds and
to the tests that measure coverage, and a partial translation needs a plan to
become complete.
