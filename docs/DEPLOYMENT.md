# Afrihost deployment and recovery

This is a working development release. Production checkout deliberately fails closed. Do not open it for trading merely by entering credentials. Complete `STATUS.md` and the gates below first.

## Host a review preview first

Use a new staging subdomain or unused domain, such as `preview.example.co.za`, and keep the existing CRM Technology website on its current document root. Do not point the production domain at this build during review. Afrihost's shared plans support PHP and scheduled cron jobs, but confirm the actual account's PHP version, PDO MySQL, required extensions, document-root control, SSL and cron access in ClientZone before upload. Current published Silver limits are small (2 GB storage and one MySQL database); this application uses one database, and its local catalog images are optimized, but confirm available account space before copying files.

Create a dedicated staging database and user, and configure the stage with `APP_ENV=demo`, an HTTPS `APP_URL`, and the stage database credentials. This keeps the preview banner visible, search engines blocked, and every checkout marked as a test order. Run `bin/migrate.php --seed-demo` once on that empty stage database. Create a unique staging administrator using `bin/create-admin.php` and environment variables provided outside shell history. Never copy the local `storage/commerce.sqlite` or any real customer records to the host. A deployment archive should omit `.env`, all runtime databases, logs and temporary QA data.

Set the staging document root to this project's `public/` directory so source, configuration, tests, database files and backups remain outside the web root. Afrihost's generic upload instructions describe `public_html`; do not extract the whole project into that directory if the hosting account cannot point a subdomain's document root at `public/`. Ask Afrihost to configure a private application directory and the public entry point before proceeding. Keep the current website's files and DNS unchanged during staging.

The review preview is publicly reachable if its hostname resolves publicly. It is `noindex` and clearly labeled, but it is not an access-controlled private preview. Do not enter real customer information. Production mode is a later deployment and checkout remains closed until the release gates below pass.

## Local preview

Requires PHP 8.2+, PDO SQLite, mbstring and standard PHP modules. From the project directory:

```sh
php bin/migrate.php --seed-demo
php -S 127.0.0.1:8080 -t public router.php
```

Open `http://127.0.0.1:8080`. The preview uses a persistent private SQLite database and automatically imports the catalog once. There is no Node or Composer package install needed for the current source. `composer.json` records PHP requirements and convenient scripts, if Composer is available. Demo checkout creates test records and never asks for money.

Create your administrator with `bin/create-admin.php`, supplying `ADMIN_EMAIL`, `ADMIN_NAME`, and a unique 16–128 character `ADMIN_PASSWORD` through a secure environment mechanism. Do not type the password into a shell command or commit it. There is no shared default administrator account. The admin login is the normal `/login`, followed by `/admin`.

## Hosting preparation

1. Confirm the actual Linux Silver package and available disk/database resources. Verify PHP version, PDO MySQL, cURL, mbstring, OpenSSL, fileinfo, iconv, zlib, HTTPS, cron and outbound provider connectivity. Confirm document-root control and Apache rewrite support.
2. Create one dedicated database with `utf8mb4` and InnoDB. Use a restricted application user. Migration requires schema permissions; remove unnecessary schema permissions from the normal runtime user afterward.
3. Upload the application outside the public document root, for example `/home/ACCOUNT/crm-technology`. Point the domain document root at `/home/ACCOUNT/crm-technology/public` only. Never expose `src`, `database`, `storage`, `tests`, `.env`, or `ios`. If the hosting panel cannot change the document root, have Afrihost configure an equivalent public-only layout; do not upload the entire repository to `public_html` as a shortcut.
4. Copy `.env.example` to `.env` outside `public`. Set `APP_ENV=production`, the verified HTTPS `APP_URL`, a PDO MySQL `DB_DSN`, database user and password. The application reads `.env` server-side. Avoid world-readable secrets; owner/group permissions depend on Afrihost's PHP execution user.
5. Ensure `storage` is private and writable by PHP. Source files should not be broadly writable. The root `.htaccess` denies accidental root exposure; `public/.htaccess` supplies routing and headers. Verify the public directory explicitly permits access under the chosen Apache hierarchy.
6. Run `php bin/migrate.php` from the hosting shell or a controlled one-time CLI task. Do not use `--seed-demo` for production. The importer/seeding fixture is for review, not an authoritative inventory feed. Prepare and validate the full merchant catalog/variants/Fincon mapping before import.
7. Run `php bin/preflight.php`; investigate every failure. This checks prerequisites only. Confirm the configured database is the intended one before any migration or import.
8. Create the first administrator securely. Verify customer, pending-dealer, dealer, support and administrative boundaries on staging. The website has no public setup route.

## Scheduled maintenance

If Afrihost allows five-minute intervals, schedule one bounded invocation. Adapt the PHP binary and account path to the hosting panel:

```cron
*/5 * * * * /usr/local/bin/php /home/ACCOUNT/crm-technology/bin/cron.php >> /home/ACCOUNT/private-logs/crm-maintenance.log 2>&1
```

The implemented task locks against overlap, deletes up to 500 inactive carts older than 24 hours, and cleans expired reset/rate-limit records. It does not delete completed orders, payments or audit records. Rotate the log and use an offsite alert mechanism for errors. Fincon synchronization, email delivery and recovery campaigns are not silently scheduled: those workers require their remaining implementation and verified accounts.

## Provider activation gates

- PayFast: approve the merchant environment and callback URLs, test hosted checkout and signed ITN from Afrihost, validate source/address handling behind any proxy, verify amount/merchant/order, then reconcile duplicate and delayed notifications. `PAYFAST_ENABLED` only enables the notification handler; it does **not** complete live checkout orchestration. The current Store checkout still refuses production purchases.
- Fincon: obtain the installed version's actual contract, least-privilege service account, SKU/location/tax/debtor mappings and secure route. Implement import/export jobs around the supplied mapped adapter and test stale-data blocking, retries and order reconciliation.
- Couriers: validate addresses, parcel dimensions/weights, quotes/expiry and service selection; persist provider booking reference and reconcile ambiguous timeouts. Bob Go has an HTTP adapter; shipment orchestration, Pargo pickup map/booking and Internet Express tenant contracts remain release work.
- Email: verify sender DNS and credentials; finish an outbox dispatcher, bounce/suppression/unsubscribe processing and consent review before enabling sends. Campaigns currently save as drafts. No emails are sent automatically by this build.
- AI: select provider and budget, implement the optional ranking callback using server-only credentials, and evaluate results. The shipped assistant is an explicitly labeled catalog helper.

## Backup and restore

`bin/backup.php` creates a transactionally consistent SQL snapshot with schema and records. Choose a new private path outside the web root:

```sh
php bin/backup.php /home/ACCOUNT/private-backups/crm-YYYYMMDD.sql.gz
```

The file is created with restrictive permissions; it contains personal/accounting data and password hashes. It is **not encrypted** by the tool. Encrypt it with the organization's approved backup process, copy it offsite, retain the key separately, and remove temporary local copies after confirmed transfer. Keep a separate encrypted backup of configuration/secrets and catalog media. Do not store backups on Silver until disk exhaustion.

Restore to a **new, empty, isolated database**. The backup includes `DROP TABLE` statements and must never be imported blindly over an active store. MySQL/MariaDB example, using a protected option file or interactive password mechanism:

```sh
gzip -dc /private/backup.sql.gz | mysql --defaults-extra-file=/private/mysql-restore.cnf NEW_RESTORE_DATABASE
```

Before switching the application, compare table counts, foreign-key checks, current orders, payment references, admin audit records and catalog prices. Run application smoke tests against the restored database. Test restoration periodically and before major releases. SQLite and MariaDB restore smoke checks are recorded in `TEST-RESULTS.md`; they do not establish an offsite disaster-recovery service.

Proposed retention policy: 7 daily and 4 weekly encrypted backups, adjusted by the merchant's legal/accounting requirements and capacity. Cart state expires after 24 hours; reset tokens after one hour. Completed order/payment/audit retention requires an approved business policy and is not automatically deleted. Consent and support retention also require review.

## Release checklist

- [ ] Actual Afrihost staging account validates all runtime and database requirements.
- [ ] Full catalog and variants imported; valid merchant SKU, tax, stock-age, image rights, technical specs and redirects checked.
- [ ] Company contact, banking, collection, shipping, returns, warranty and privacy content approved.
- [ ] Remaining workflows in `STATUS.md` implemented and accepted.
- [ ] Production checkout orchestration completed, exact VAT/shipping totals frozen, fresh inventory verified, oversell/race cases exercised.
- [ ] Retail payment and dealer EFT-only journeys pass external sandbox tests.
- [ ] Invalid/duplicate/late webhook and reconciliation tests pass on the deployed stack.
- [ ] Shipping quote/book/tracking flow passes with actual provider accounts and parcel data.
- [ ] Email consent, unsubscribe, bounced delivery and password reset delivery pass.
- [ ] HTTPS, cookies, secrets, headers, access boundaries and backup restore independently checked.
- [ ] Chrome, Safari, Firefox, Edge, Android and iPhone tested; accessibility/performance measured.
- [ ] Demo fixtures and test records excluded from production catalog; no preview stock claimed as live.
- [ ] Rollback version and verified backup available; monitoring and incident ownership assigned.

Do not assert POPIA/consumer-law compliance from technical controls alone; merchant-specific legal and operational review is outstanding.
