# Verification report

Development release, verified on **4 November 2026**. The application passes the local checks below. It is **not approved for live trading**: production checkout remains closed, and the [implementation status](STATUS.md) lists both remaining engineering and external acceptance work.

## Environment and evidence

The local checks used PHP 8.5.12 CLI, SQLite 3.54.1, MariaDB 13.0.2 and Python 3.13.2 on macOS/Apple silicon. The Swift toolchain was Swift 6.2. No claim is made that this matches CRM's Afrihost account; that stack must be verified separately. The application declares PHP 8.2+ support, but this report does not establish tests across every supported PHP version.

Raw suite results are included in [test-evidence](test-evidence/). SQLite and integration log files were saved during the earlier checks on the same date; the resumed verification independently reran those suites and confirmed the same results. MariaDB evidence was refreshed during resumed verification using a new, disposable database for each suite. No test targeted the existing live CRM store or a production database. Provider tests use injected responses and do not contact real provider accounts.

## Automated application checks

| Check | SQLite | Local MariaDB | Evidence / scope |
|---|---:|---:|---|
| `php tests/backend.php` | 24 passed, 0 failed | 24 passed, 0 failed | [SQLite](test-evidence/backend-sqlite.txt), [MariaDB](test-evidence/backend-mariadb.txt). Catalog, CSRF, authorization, retail/dealer pricing, immutable/idempotent test orders, stock conflict, builder, quotes/PDF, support, expiry and payment event persistence. |
| `php tests/security.php` | 13 passed, 0 failed | 13 passed, 0 failed | [SQLite](test-evidence/security-sqlite.txt), [MariaDB](test-evidence/security-mariadb.txt). Account/guest isolation, resource ownership, exact-money bounds, cart merge/expiry, password reset revocation, event reuse and rate limits. |
| `php tests/extensions.php` | 16 passed | 16 passed | [SQLite](test-evidence/extensions-sqlite.txt), [MariaDB](test-evidence/extensions-mariadb.txt). Profile/address persistence, variant identity and stock, integer coupon rounding/expiry, quote totals, dealer price/filter behavior. |
| `php tests/integrations.php` | 35 passed | Database-independent | [Fixture results](test-evidence/integrations-sqlite.txt). PayFast signature/source/amount/provider validation, cancellation/replay, shipping and Fincon failure boundaries, exact cents, email failure and assistant product restrictions. **No live/sandbox provider round trip.** |
| `php tests/concurrency.php` | Not applicable | 3 passed, 0 failed | [Results](test-evidence/concurrency-mariadb.txt). Four simultaneous processes: one recorded payment with idempotent duplicates; atomic rate-limit admission; one buyer succeeds for the last stock unit. |

All 31 PHP files passed syntax checking. After removing duplicated unreachable exception handlers, `src/Api.php` passed syntax again; isolated API smoke checks returned the expected 404 for an unknown route and 503 for disabled PayFast notifications.

These tests demonstrate the implemented development behavior; they do not establish the unimplemented production workflows. In particular, a test EFT order is not a payment, fixture ITN verification is not provider acceptance, and a synthetic PC component is not a verified sellable component.

## HTTP and browser verification

`python3 tests/http_flows.py http://127.0.0.1:8080` passed **29 checks** against the local demo application during resumed verification. The suite covers category results, missing CSRF, forged browser prices, invalid quantity, guest isolation, denied admin actions, test EFT order creation/server totals/idempotency, order privacy, registration/role boundaries, private support replies, builder groups/missing specifications/save/share, quote PDF ownership, pending dealer restrictions, injection-as-input, and disabled card/webhook behavior. It creates synthetic records only.

Browser checks used Chrome 156 through agent-browser 0.27, with a desktop viewport of 1440 × 1000 and a mobile viewport of 390 × 844:

| Journey | Observed result |
|---|---|
| Homepage and basket | Desktop page visually inspected; keyboard activation added a product and the basket count updated. |
| Checkout | A synthetic customer completed demo checkout and received a clearly marked test order in pending-payment status. No payment was collected. |
| Mobile navigation/catalog | Mobile layout visually inspected; navigation expanded and closed with Escape; catalog filters expanded; no horizontal document overflow in these checked states. |
| Comparison | Two saved choices survived navigation; the table scrolled inside its own container without widening the page. A missing style for the differences-only control was identified and corrected; equal rows then hid and the label reported two hidden rows. Unchecking the control restored every equal row; the desktop comparison screenshot was visually inspected. |
| PC builder | Selecting incompatible AM5 CPU and AM4 motherboard fixtures updated the total to R5,300 and displayed socket/chipset mismatch plus missing BIOS evidence. No horizontal document overflow in the checked state. |
| Browser errors | No JavaScript errors were reported in the checked shopping flow. |

[HTTP/browser verification summary](test-evidence/browser-http-summary.txt). These are sampled local flows and viewport checks, not a complete accessibility audit, every-route interaction test or real-device certification. Browser/mobile emulation does not substitute for Safari, Firefox, Edge, Android or iPhone testing.

## Backup and recovery checks

A fresh isolated SQLite demo database was exported through `bin/backup.php` and restored into a new database. Five checks passed: row counts matched across all 35 application tables, explicit indexes were preserved, `integrity_check` returned `ok`, `foreign_key_check` returned no violations, and the compressed backup had `0600` permissions. [SQLite evidence](test-evidence/sqlite-restore.txt).

A separate fresh MariaDB fixture database was exported and restored into a new database. All 35 table row counts, `SHOW CREATE TABLE` definitions (including indexes/constraints) and `CHECKSUM TABLE` values matched. The restored Store read the expected 102-product catalog, foreign-key checks were enabled, and the compressed file had `0600` permissions. The fixture also contained user, order, order-item, quote and quote-item records. Both test databases and their dump were removed after verification. [MariaDB evidence](test-evidence/restore-mariadb.json).

Local restoration does not establish encrypted offsite backups, a complete recovery drill, retention approval or recovery-time guarantees. Follow the [deployment and recovery guide](DEPLOYMENT.md).

## iOS foundation

The four Swift files passed `xcrun swiftc -parse`; `Info.plist` and `project.pbxproj` passed `plutil -lint`; the shared Xcode scheme parsed as XML. A macOS Foundation smoke executable using `Product.swift` and `APIClient.swift` passed six checks: decoding a real local catalog response, saved-product Codable round trip, canonical purchase/share URL, same-origin HTTPS image URL, integer-cents ZAR formatting and rejecting the unconfigured `.invalid` host before a network call. The corrected `/products/{slug}` purchase/share route returned HTTP 200 from the local web server.

A full iOS SDK build, simulator run, physical-device test and signing were **not performed**. `xcodebuild -version` failed because the active developer directory contains Command Line Tools, not full Xcode. macOS Foundation compilation and syntax parsing do not prove an iOS application build. The smoke harness is verification work, not a shipped iOS test target. See [iOS setup and remaining work](IOS.md).

## Required release acceptance still outstanding

| Required scenario | Current evidence | Remaining validation |
|---|---|---|
| Search/filter/variants/cart/order-once/stock | Automated local SQL and HTTP behavior; immutable order and concurrency fixtures | Full merchant catalog, real inventory freshness, production reservations and Afrihost load. |
| Retail card, retail EFT, dealer EFT-only | Fixture PayFast checks, test EFT orders, dealer server enforcement | Real sandbox checkout-to-provider journey, bank details, reconciliation, refund and fulfilment workflow. |
| Dealer approval and access | Protected approval, pending role, dealer prices and denied escalation tests | Fincon debtor mapping, document review and accepted dealer policy. |
| PC builder | Eight synthetic component groups, mismatch/missing-data rules, save/share and PDF | Complete real technical specifications, BIOS/manufacturer validation and actual build acceptance. |
| Fincon/shipping/email failures | Injected unavailable/empty/invalid responses and adapter behavior | Account-specific contracts, job orchestration, address/parcel/quote binding, actual send/delivery/retry outcomes. |
| Upload security | Upload features remain unavailable | Implement private attachment/proof-of-payment handling and test malformed/MIME/size/ownership cases before enabling. |
| Browsers/devices | Local automated browser checks are a limited development review | Safari, Firefox, Edge, physical Android/iPhone/tablet, assistive technology and full device acceptance. |
| Performance and operations | No production benchmark or hosting acceptance in this report | Realistic mobile Core Web Vitals, concurrent load, public HTTPS, independent security review, monitoring and offsite restoration. |

The store was not deployed to Afrihost, no payment was collected, and the app was not submitted to Apple. The release checklist is in [DEPLOYMENT.md](DEPLOYMENT.md); the feature-by-feature engineering inventory is in [STATUS.md](STATUS.md).

## Repeat the checks

Run the four PHP suites above from the application directory. They default to disposable in-memory SQLite where relevant. To check MySQL/MariaDB, set `TEST_DB_DSN` and `TEST_DB_USER` to a **dedicated disposable database**; use a separate fresh database per suite. `tests/concurrency.php` requires MySQL/MariaDB and launches independent PHP processes.

For HTTP checks, start a separate local demo server with its own disposable SQLite database and run:

```sh
python3 tests/http_flows.py http://127.0.0.1:8080
```

The HTTP script is restricted to a loopback origin and checks that the target reports demo mode. It creates synthetic accounts/orders/tickets/builds and changes fixture inventory. Do not point test scripts at production or reuse a live database. Repeating a browser flow against a shared preview can also consume demonstration stock; a fresh isolated database makes results reproducible.
