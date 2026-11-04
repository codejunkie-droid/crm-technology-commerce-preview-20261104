# Security and transaction verification

This is a local implementation review and fixture test record, not a penetration-test certification or a production-readiness claim.

## Controls verified

- Authentication creates server-side sessions and uses password hashing, CSRF tokens and per-IP/account rate limits. Sessions contain a fingerprint of the current password hash. Password reset changes that fingerprint and revokes every earlier authenticated session on its next request. Reset tokens are hashed and consumed under a database lock.
- Changing accounts directly through login or registration rotates guest state. An old guest token cannot bypass an account owner on an order or quotation. Address/ticket/order ownership is checked on the server. Dealer and administrator permissions are read from the database.
- Signed-in carts restore after logout/login. A login merges the current guest cart with that user's unexpired carts, under account/cart locks, capped at current stock and 100 per item. Expired carts do not reappear. Old promotion codes are not copied from merged carts and must be reapplied. No order, payment or accounting record is deleted during merging.
- Financial inputs use integer cents, bounded item quantities and server-derived totals. Provider decimal money is preserved as decimal text before converting to cents. The provider cannot override an order snapshot.
- MySQL connections use READ COMMITTED. Checkout locks its cart and selected inventory in stable order before reading prices and committing conditional stock decrements. A concurrent checkout for the last unit returns a stock conflict; it does not oversell.
- PayFast verification validates source, merchant, signature, exact amount, order/payment method, completed status and a provider confirmation response. It accepts only configured provider hosts. Payment persistence locks the order before checking the provider event and rejects reference reuse against a different order.
- Rate-limit admission is an atomic conditional update. Email adapters address one recipient at a time and never report a failed provider response as successful delivery. SQL reads/writes use bound parameters; sort orders and administrative settings are allowlisted.

## Local checks

- `php tests/integrations.php`: **35 passed**; all providers are injected fixtures, no external requests.
- `php tests/security.php`: **13 passed on SQLite and 13 on local MariaDB**.
- `tests/concurrency.php`: **3 passed on local MariaDB**, each with four independent PHP processes starting together. Tests cover one payment with three duplicate acknowledgements, rate-limit admission, and one successful buyer for a single inventory unit.
- `tests/backend.php`: **24 passed on SQLite and 24 on local MariaDB** in the saved evidence. See [the consolidated verification report](TEST-RESULTS.md) for the evidence dates, scope and later reruns.

To repeat the concurrency suite, set `TEST_DB_DSN` and `TEST_DB_USER` to a **disposable test database** and run `php tests/concurrency.php`. It creates test users/catalog/order records and does not contact payment or courier providers. Never target production with test scripts.

## Remaining release gates

- Production checkout is intentionally closed until actual inventory authority, merchant/bank details, delivery prices and operational acceptance are verified. No production money was collected.
- Provider credentials, sandbox round-trips, webhook retries/reconciliation, a real hosting test and an independent encrypted-offsite recovery drill remain separate production gates. Local SQLite and MariaDB restoration were checked as recorded in [the verification report](TEST-RESULTS.md). Passing a local MariaDB test does not verify the Afrihost account.
- Marketing preferences record requests; verified-email or double-opt-in eligibility, suppression/bounce handling and a consent-reviewed campaign sending workflow are not complete. Campaigns remain drafts and no marketing delivery worker is enabled. Do not treat an anonymous form submission as proof of control of an email address.
- File attachments and proof-of-payment uploads are not implemented and must remain unavailable until allowlisted MIME inspection, storage isolation, authorization, size limits and scanning are implemented. An upload must never mark an order paid.
- MFA, a full administrative session/device management screen, real payment refunds and automated reconciliation still need implementation/acceptance before wider operations. Technical controls alone do not establish POPIA or consumer-law compliance.
- This review did not measure resistance to distributed denial of service, conduct a third-party penetration test, or validate every browser/device. Follow the broader deployment/test checklist.
