# Developer Guide

PNLCS is a Laravel 13 application (PHP 8.4, MySQL or MariaDB, Blade, Tailwind
CSS 4, Alpine.js), released under the MIT licence. You can extend it without
changing its code, or change the code and send the change back.

| To | Read |
|---|---|
| Drive PNLCS from another system | [API reference](../api/index.md) |
| Connect an AI assistant | [MCP server](../mcp/index.md) |
| Add a server, payment gateway, registrar or SSL provider | [Writing a module](modules.md) |
| React to events (an invoice paid, an account created...) | [Hooks](hooks.md) |
| Change how the site and client area look | [Themes](themes.md) |
| Translate PNLCS, or improve a translation | [Translations](translations.md) |
| Contribute code or documentation | [Contributing](contributing.md) |

## What lives where

```
pnlcs/
├── app/            application code: models, controllers, services, contracts
├── config/         configuration, including config/api_docs.php (the API reference)
├── database/       migrations, seeders, factories
├── docs/           this documentation (MkDocs)
├── lang/           translations, one folder per language
├── mcp/            the MCP server (pnlcs-mcp on npm)
├── modules/        servers, gateways, registrars, SSL providers, addons
├── resources/      Blade views, CSS and JavaScript sources
├── routes/         web, admin, client, API and scheduled commands
├── tests/          the test suite (Pest)
└── themes/         the 16 built-in themes, and yours
```

## Running the tests

```bash
composer install
php artisan test
```

The suite needs a MySQL or MariaDB database of its own, `pnlcs_test`, with the
tables in place: each test runs inside a transaction that is rolled back, so
it never creates tables itself. Once, create the database (for example
`CREATE DATABASE pnlcs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
in your database console), then run the migrations against it:

```bash
DB_DATABASE=pnlcs_test php artisan migrate --force
```

The `DB_*` lines in `phpunit.xml` tell the tests how to reach it; change the
host, socket, user and password there to match your machine. Every change
should come with a test that fails without it.
