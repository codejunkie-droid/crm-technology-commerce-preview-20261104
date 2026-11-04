# CRM Technology — Precision Commerce

A runnable PHP commerce development release for CRM Technology (Pty) Ltd. Built for Apache/PHP/MySQL hosting, with SQLite for local preview. **This is not approved for live trading.** Preview checkout records test orders; live checkout is deliberately closed.

## Run

```sh
php bin/migrate.php --seed-demo
php -S 127.0.0.1:8080 -t public router.php
```

Open [the local store](http://127.0.0.1:8080). PHP 8.2+, PDO SQLite and mbstring are needed locally; production requires PDO MySQL. No Node service or frontend build is required. The PHP dependencies are listed in `composer.json`, but the current application has no third-party Composer packages to install.

The source catalog contains 93 real CRM product snapshots retrieved on 4 November 2026, plus nine explicitly synthetic components for compatibility testing. Published prices are snapshots. Stock quantities are demonstration fixtures. The runtime catalog lives in the database and is editable through protected administrative actions. Nothing here claims current Fincon inventory or verified provider connectivity.

## Explore

- `/`: storefront; `/catalog`: searchable, filtered catalog; `/products/{existing-slug}`: product pages.
- `/cart`, `/checkout`: persisted basket, server totals and test orders.
- `/builder`, `/compare`, `/wishlist`: component checks, saved/shared builds, product comparison and saved products.
- `/register`, `/login`, `/account`, `/dealer`: customer and business accounts.
- `/support`, `/business`: private support tickets and business enquiries.
- `/admin`: protected administration. No shared default admin login exists. Use `bin/create-admin.php` with a securely supplied unique credential.

## Verification

```sh
php tests/backend.php
php tests/integrations.php
php tests/security.php
php tests/extensions.php
python3 tests/http_flows.py http://127.0.0.1:8080
```

HTTP tests create synthetic data on a local demo server. Never run tests against a production database. `TEST_DB_DSN` and `TEST_DB_USER` let the PHP test suites use a separate, disposable MySQL/MariaDB database. See the test report for the actual versions and results checked in this build.

## Project guide

- [Architecture and visual direction](docs/ARCHITECTURE.md)
- [Reference-site research](docs/DESIGN-RESEARCH.md)
- [Integration readiness and source links](docs/INTEGRATION-READINESS.md)
- [API contract](docs/API-CONTRACT.md)
- [Deployment, cron, backup and restore](docs/DEPLOYMENT.md)
- [Temporary Render review host](docs/TEST-HOSTING.md)
- [Implemented features and outstanding work](docs/STATUS.md)
- [Verification report](docs/TEST-RESULTS.md)
- [Native iOS foundation](docs/IOS.md)
- [Image provenance](docs/ASSET-SOURCES.json)

Use `public/` as the only web document root. Keep `.env`, database files, private storage and backups outside it. Production release requires the actual merchant catalog/tax/stock mapping, payment and courier acceptance tests, completed operational workflows and approved business policies. The detailed status document distinguishes working code from adapters and remaining implementation; entering credentials alone does not complete those workflows.
