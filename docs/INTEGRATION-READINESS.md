# Hosting and integration research

Checked 4 November 2026. Public documentation establishes provider capabilities; it does **not** establish that CRM Technology has an enabled account, the right API version, a working network route, or production credentials. No private business systems were accessed and no external service was purchased.

## Architecture decision

Use a server-rendered PHP application with progressively enhanced JavaScript, PDO, a single MySQL/InnoDB production database, and bounded cron jobs. PHP sessions and indexed SQL are suitable for the initial workload; avoid mandatory Node services, Redis, queues that require a permanent worker, local AI inference, and server-side asset builds on shared hosting. Build assets before upload. SQLite may be used for local development only; production MySQL needs its own migration/integration validation.

The official [Afrihost shared hosting listing](https://www.afrihost.com/shared-hosting) advertises Linux Silver with 2 GB storage, one MySQL database and PHP, at R84/month. This is the current public offer, **not verification of the user's subscribed package or invoice**. Afrihost confirms [PHP/Perl scripts and cron jobs](https://help.afrihost.com/entry/can-i-run-my-own-software-on-my-site/), while processor-intensive software may be disallowed. Those constraints support the architecture above.

Before deployment, inspect the real hosting panel for current PHP version, PDO MySQL/cURL/mbstring/fileinfo/OpenSSL/GD availability, MySQL version, document-root control, storage usage, HTTPS, outbound HTTPS permissions, per-process execution and memory limits, and minimum cron interval. Public pages do not establish these account-specific values. Use a supported, patched PHP release; Afrihost publishes [current service notices](https://www.afrihost.com/status).

Keep only optimized published catalog images on Silver. Store source images and private backups offsite; media storage/CDN is optional until measured storage requires it. Retain private files outside the document root. Database jobs need locks, retries/backoff, run limits, resumable cursors and logged outcomes. Alert at 70% disk use; investigate an upgrade if sustained peak p95 page time exceeds 1 second after query tuning, cron backlog exceeds two scheduling windows, or CPU/resource throttling persists. These are proposed operating thresholds, not provider guarantees.

## Integration readiness matrix

| Service | Public evidence / proposed contract | What still prevents a live verification |
|---|---|---|
| PayFast | Hosted checkout and ITN flow documented. Server signs checkout; notification acceptance requires signature, trusted source, amount and server validation. | Merchant ID/key/passphrase, confirmed sandbox and live account, public HTTPS ITN URL, sandbox end-to-end test and reconciliation. |
| Fincon | Vendor-authored REST API manual describes version 5.280.001; older WebAPI installations may differ materially. Use a version-specific adapter behind an internal inventory/order contract. | CRM's actual installed version, API licence, credentials, approved functions, debtor/stock-location/tax mappings, reachable secured gateway and sample payloads. |
| Bob Go | Official v2 API provides rate, shipment, waybill and tracking operations, with sandbox and production endpoints. | API-enabled plan/account, token, collection address, measured parcels, services configured, sandbox tests. |
| Internet Express | Shiplogic officially names Internet Express as a client. Direct integration must use its account-specific documented API. | Correct tenant/base URL, bearer/API credentials, contract, actual rate/booking examples. Do not use another courier's hostname. |
| Pargo | Simba API documents authentication, pickup point searches, quotes/orders and webhooks. | Issued credentials, approved warehouse/address codes, map token, webhook agreement, end-to-end staging test. |
| Email | An external transactional API avoids relying on shared-hosting bulk delivery. | Verified sending domain, SPF/DKIM/DMARC, credentials, bounce/unsubscribe verification, recipient consent and approved templates. |
| AI | Server-only provider adapter can retrieve catalog records and return bounded answers with product links. | Selected provider/model, key, approved data processing arrangement, usage budget and evaluation. Disabled provider must be disclosed and human support available. |

This matrix describes readiness requirements, not completed implementation. Inspect the delivered application status document for code-level implementation status.

## Provider-specific implementation notes

### PayFast

Use the [official PayFast documentation](https://developers.payfast.co.za/) for the exact supported form fields and signature encoding. A return/success redirect is never proof of payment. Validate ITN signature using the configured passphrase, source validity, merchant identity, server-to-server response and exact stored amount before accepting a completed event. Resolve provider validation hosts from controlled configuration; do not trust arbitrary client hosts. Persist unique provider event/payment identifiers and update payment/order status inside a transaction. Duplicate delivery must be harmless. Approved-dealer EFT-only restrictions belong in checkout authorization, not just the UI. Test rejected signature, amount mismatch, wrong order, duplicate events, cancelled payment and delayed valid payment. Current merchant fees require the actual merchant agreement; no payment rate is invented here.

### Fincon

The [Fincon-authored REST API Server manual hosted by Syntech](https://www.syntech.co.za/redragon/FinconV1.pdf) identifies version **5.280.001**, requiring Fincon 5.280.001 or newer. It documents connection-based REST methods and stock, debtor, quotation and sales-order operations. It is a vendor document distributed by a retailer; it does not confirm CRM's installed product or connectivity. An older WebAPI adapter must not be treated as interchangeable without fixtures from the real system. Obtain the deployed version's documentation, representative responses, service account permissions and SKU/tax/warehouse/debtor mapping. Prefer outbound synchronization from a restricted on-premises gateway when inbound access is unsuitable. Never expose the accounting database. Failed sync retains the last valid stock and price, records age, and prevents unverified inventory from becoming purchasable. Use stable website order references and reconcile before retrying an ambiguous order export.

### Bob Go

[Introduction](https://developer.bobgo.co.za/introduction/) documents `https://api.sandbox.bobgo.co.za/v2` and `https://api.bobgo.co.za/v2`, rates and shipment operations. Its amounts are denominated in ZAR, parcel dimensions in centimetres and weight in kilograms; confirm per-field VAT treatment. [Authentication](https://developer.bobgo.co.za/authentication/) documents account-scoped API keys. Read exact request schemas for enabled endpoints. Store request IDs, provider shipment IDs and quote expiry. A timeout after booking is an ambiguous result, not permission to create another shipment; reconcile by the external reference before retrying. Carry one selected provider per fulfilment so an Internet Express shipment is not booked both directly and through Bob Go.

### Internet Express and Pargo

[Shiplogic's own history](https://www.shiplogic.com/our-story) confirms its Internet Express relationship. The [official API page](https://www.shiplogic.com/api-docs) was reachable but its dynamically rendered request contract was not available through text retrieval; tenant-specific requests still require account documentation.

[Pargo's official Simba documentation](https://docs.pargo.co.za/) describes access/refresh tokens, a staging API, pickup-point searches and webhook references. Cache tokens until expiry rather than authenticating for each request. Pickup point IDs must be validated server-side; a browser map selection alone is not authoritative. Whitelist the map's origin when handling postMessage. Keep webhook delivery IDs unique and require the provider's agreed verification mechanism. Do not invent a signature scheme if one has not been supplied. Tracking is informative; a browser-provided status cannot change fulfilment state.

## Recurring cost plan

| Capability | Initial approach | Cost basis / free alternative |
|---|---|---|
| Hosting | Existing Silver account | Public listing R84/month; actual contract takes precedence. |
| Database / search / queue | Included MySQL, indexed queries and cron | No mandatory added subscription. |
| Email | External provider only when sending is enabled | [Brevo's official Free plan](https://help.brevo.com/hc/en-us/articles/208589409-About-Brevo-s-pricing-plans) currently lists 300 sends/day. Domain verification and service terms still apply. |
| Media / backups | Optimize local assets; retain offsite backup copies | No mandatory media subscription initially. Select storage based on actual GB, requests and egress. |
| Payment / courier / Fincon | Merchant/provider contracts | Usage/contract pricing; obtain CRM's actual fees. |
| AI | Disabled until selected | No AI cost while disabled. Set a hard monthly usage ceiling before enabling; costs depend on model and volume. A catalog search helper is a free fallback but must not be presented as a live AI integration. |
| iOS distribution | Organization Apple Developer membership | [Apple](https://developer.apple.com/programs/whats-included/) lists USD99/year, or local currency where available; taxes/region may vary. |

## Deployment gates

1. Verify actual Afrihost capabilities; import the MySQL schema and run transactional tests there.
2. Import a verified catalog with SKU-level prices, tax treatment, stock age, technical specs and licensed image provenance.
3. Confirm company/banking/shipping/returns/legal content. Review POPIA workflows with an appropriately qualified reviewer; do not declare legal compliance from technical controls alone.
4. Configure public HTTPS, secure session cookies, secrets outside public paths, least-privilege database user, logs and recovery backups.
5. Exercise full retail/dealer authorization and payment/stock race tests; test provider sandboxes from the hosting account.
6. Restore a backup into a separate database and verify orders, payment events and audit history before production.
7. Monitor measured performance; do not quote Lighthouse or Core Web Vitals targets as achieved without measurement.

## Implemented adapter inventory (local fixture verification)

`src/Integrations.php` contains:

- `PayFast`: official hosted form/signature construction and fail-closed ITN verification through injected HTTP. It checks notification source, merchant/order, exact amount, payment method/dealer restriction, completed status and provider confirmation. The returned stable provider ID must be persisted atomically by the commerce service; the adapter does not itself mark orders paid.
- `BobGo`: official sandbox/production v2 rate, booking, tracking and waybill requests with measured-parcel/address validation. Booking is never automatically retried. Provider JSON decimal amounts are preserved as strings. The service using it must implement a shipment lock, quote expiry/revalidation, unique external references, webhook verification and reconciliation.
- `PendingShippingProvider`: explicit unavailable states for Internet Express/Pargo until tenant/account contracts are verified; it never returns invented rates.
- `FinconMappedSource`: injected installed-version connector and strict configured field mapping. Empty or invalid snapshots fail without replacing valid stock. No invented live Fincon endpoint is used.
- `BrevoEmail`: one-recipient transactional API request with provider acceptance reference, using the [official endpoint](https://developers.brevo.com/reference/send-transac-email). The durable outbox still owns consent, suppression, dedupe, retries and bounce handling.
- `CatalogAssistant`: verified-catalog search with optional injected ID-only AI ranking. AI cannot introduce nonexistent products, prices or availability. It returns a catalog-search label when no model is connected. No model provider is configured or tested.

`php tests/integrations.php` passes 34 fixture checks, including invalid/duplicate ITN fields, amount mismatch, cancelled payment, invalid source, dealer/EFT card rejection, provider failure, decimal precision, empty Fincon response, unavailable courier and email failure. These use injected in-memory transports and do not contact any provider. Actual credentials, sandbox round-trips, provider-webhook authentication and production acceptance are still pending.
