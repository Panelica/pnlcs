# Contributing to PNLCS

Thanks for considering a contribution. PNLCS is MIT-licensed and we welcome:

- Bug fixes
- New features (with discussion first — see below)
- Documentation improvements
- New translations or translation fixes
- Theme contributions
- Server / registrar / payment-gateway adapters

This guide explains how to set up a development environment, the conventions we follow, and how to submit a good pull request.

---

## Before you start

1. **Check existing issues and PRs.** Someone may already be working on it.
2. **Open an issue first for non-trivial changes.** Big features should start with a discussion so we can agree on scope before code is written. Drive-by 5,000-line PRs are very hard to review.
3. **Read the [Code of Conduct](./CODE_OF_CONDUCT.md).** Be the kind of contributor you'd want to receive PRs from.

---

## Project layout

PNLCS is a Laravel 13 application using Blade, TailwindCSS 4, and Alpine.js.

```
pnlcs/
├── app/                  # Application code (Models, Controllers, Services, Jobs)
├── bootstrap/            # Framework bootstrap
├── config/               # Configuration files
├── database/             # Migrations, seeders, factories
├── docs/                 # Project documentation
├── lang/                 # Translations (30 locales)
├── modules/              # Plug-in modules (gateways, registrars, server adapters)
├── public/               # Web entry point
├── resources/            # Views (Blade), CSS, JS sources
├── routes/               # web.php, api.php, console.php
├── tests/                # PHPUnit tests
├── themes/               # 15 shipped themes
└── ...
```

A high-level architecture overview lives in `docs/`. Read those before adding new core concepts.

---

## Development setup

### Prerequisites

- PHP 8.4+ (CLI)
- MySQL 8 (or MariaDB 10.11+)
- Redis 7 (sessions / cache / queues)
- Node.js 22+ and npm 10+
- Composer 2.7+

### Clone and install

```bash
git clone https://github.com/Panelica/pnlcs.git
cd pnlcs
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your DB / mail / Redis credentials, then:

```bash
php artisan migrate --seed
npm run dev      # in one shell — Vite dev server
php artisan serve  # in another shell — http://localhost:8000
```

### Logging in

Default seeded credentials are printed at the end of `php artisan migrate:fresh --seed`. **Change them before exposing the instance to the network.**

---

## Coding conventions

### PHP

We use [Laravel Pint](https://laravel.com/docs/pint) (PSR-12 + Laravel preset).

```bash
./vendor/bin/pint           # auto-format your changes
./vendor/bin/pint --test    # check without writing
```

Static analysis with [PHPStan](https://phpstan.org/):

```bash
./vendor/bin/phpstan analyse
```

Both are CI-enforced — please run them locally before pushing.

### JavaScript / CSS

- Tailwind utility classes preferred over custom CSS where possible
- Alpine.js for interactivity — keep components small and inline-able
- Don't add jQuery, Bootstrap JS, or large UI libraries without prior discussion

### Blade

- One Blade view per page / partial — extract reusable bits into components in `resources/views/components/`
- Localise everything: `__('key')` not raw strings
- Don't put business logic in Blade — put it in a service or view-model

### Database

- Every schema change is a **migration**
- Migrations must be reversible (`up()` and `down()`), additive, and safe to run twice
- A migration never changes data the operator changed: seeded rows are updated only while they are still the shipped version (see [RELEASING.md](./RELEASING.md#migrations))
- New tables: snake_case, plural (`invoices`, `payment_methods`)
- Foreign keys explicit, on-delete behaviour explicit
- Don't write raw SQL in models — use Eloquent or query builder

### Tests

- New features → at least one feature test
- Bug fixes → a regression test that fails before your fix and passes after
- Run the full suite locally before pushing:

```bash
php artisan test
```

CI runs the same suite plus Pint and PHPStan.

---

## Translations

PNLCS ships 30 locales. To add or fix translations:

- Locale files live under `lang/{code}/`
- Add the key in `lang/en/...` first (English is canonical)
- Every new key must also exist in `tr`, `de`, `pl` and `zh`, in the same key order as English; the parity tests fail otherwise. If you cannot write one of them, say so in the PR and a maintainer will add it
- The other 25 languages are community languages: a key they do not have shows in English, so a new key does not have to be written in them
- Every language must keep the English placeholders (`:name`) and exactly its HTML tags; `php artisan test --filter=Translation` checks it
- Provide the corresponding entry in your target locale
- The full guide, including texts that stay the same as English: [docs/developer/translations.md](docs/developer/translations.md)
- For new locales, open an issue first so we can coordinate flag, RTL handling, and seed scripts

Do not hand-edit machine-translated files unless you're a fluent speaker.

---

## Modules (gateways, registrars, server adapters)

PNLCS supports plug-in modules in `modules/`. New ones welcome — reference the existing ones for the expected interface:

- **Payment gateways:** Stripe, PayPal, BankTransfer, Authorize.Net
- **Domain registrars:** Enom, Manual
- **Server adapters:** Panelica, cPanel, Plesk, DirectAdmin, Proxmox, Custom

If you're contributing a new gateway / registrar / server, please:

- Implement the full interface (no half-finished modules)
- Include feature tests using the sandbox / test mode of the upstream service
- Document required env variables in `.env.example`

---

## Branch & commit conventions

- Branch off `main`. Name: `fix/short-description`, `feat/short-description`, `docs/short-description`.
- Commit messages: short imperative subject, optional body. We loosely follow Conventional Commits but don't reject PRs over format alone.
- Keep a pull request to one subject. Unrelated fixes go in their own pull requests, even when you found them along the way: a question about one part should not hold back the others.

Example:

```
feat(billing): add VAT exemption for B2B invoices

EU customers with a valid VAT ID can now self-mark their account
as B2B from the customer portal. Invoices generated for those
accounts skip the VAT line.

Closes #142
```

---

## Pull request checklist

Before opening a PR, please confirm:

- [ ] My branch is up to date with `main`
- [ ] `./vendor/bin/pint --test` passes
- [ ] `./vendor/bin/phpstan analyse` passes
- [ ] `php artisan test` passes
- [ ] I've added or updated tests for my change
- [ ] I've updated documentation (`docs/`, `README.md`, `.env.example`) where relevant
- [ ] I've added translation keys in `lang/en/` and in `tr`, `de`, `pl`, `zh` (or said in the PR which ones I could not write)
- [ ] No text is written straight into a view; every string goes through `__()`
- [ ] My test fails on `main` without my change and passes with it
- [ ] Nothing in my change writes to the operator's space (`.env`, `storage/`, their own themes, modules and hooks) — see [RELEASING.md](./RELEASING.md#the-user-space-contract)
- [ ] My PR description explains **what changed and why** (not just "fixes bug")
- [ ] I've linked the issue this PR addresses (e.g. `Closes #123`)

Smaller, focused PRs get reviewed faster than large ones.

---

## Review process

- A maintainer will review within ~5 business days
- Expect feedback — even good PRs usually need small iterations
- A maintainer may add a small follow-up commit on top (a test, a translation, an edge case) instead of sending the PR back, and will say so in the review
- Once approved, a maintainer merges it as it is, so your commits keep your name
- Merging does not ship it: your change reaches installations with the next release, through the steps in [RELEASING.md](./RELEASING.md)
- Your contribution will be credited in the next changelog entry

---

## License

By contributing, you agree that your contribution will be licensed under the MIT License (the same license as PNLCS).

You retain copyright on your contributions; you grant the project the right to distribute them under MIT.

---

## Questions?

- General "how do I…?" → [Discussions Q&A](../../discussions/new?category=q-a)
- Idea / RFC-level proposal → [Discussions](../../discussions)
- Found a bug → [Bug report issue](../../issues/new?template=bug_report.yml)
- Security finding → [SECURITY.md](./SECURITY.md) (do **not** open a public issue)
- Anything else → open a draft issue and we'll redirect

Thanks for helping make PNLCS better.
