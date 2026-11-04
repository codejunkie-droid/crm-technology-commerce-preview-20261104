# Architecture decision — Precision Commerce

Decision date: 4 November 2026.

## Product and visual direction

CRM Technology serves South African retail customers and approved dealers across seven departments. Preserve its existing cyan CRM logo, product names and current product URL slugs. Pair the logo with cobalt actions (`#3456FF`), ink (`#0B1020`), off-white (`#F7F9FC`) and understated borders (`#E5EAF1`). Self-host Space Grotesk for editorial headings and use a system sans for dense shopping controls. Keep product imagery dominant and controls predictable.

The homepage sequence is search/navigation → laptop editorial → seven categories → catalog-backed featured selection → dark PC-builder feature → business purchasing → support/footer. The signature is the contrast between a spacious photographic storefront and a precise, information-rich component builder. Product specifications, unknown data and purchase restrictions remain visible.

## Hosting decision

PHP 8.2+ server-rendered pages and same-origin JSON endpoints; PDO; one MySQL/InnoDB production database; ordinary Apache hosting; short cron invocations. There is no Node server, mandatory frontend build, Redis instance, resident worker or on-server AI model. SQLite is a convenience for local preview and automated tests. It does not substitute for MySQL staging validation.

Afrihost's public [Silver listing](https://www.afrihost.com/shared-hosting) and [script/cron guidance](https://help.afrihost.com/entry/can-i-run-my-own-software-on-my-site/) support this choice. The actual account's PHP release, extensions, database version, limits, outbound connectivity and document root must still be checked.

The available Sites hosting runtime uses Cloudflare Workers, whereas this brief explicitly targets Afrihost PHP/MySQL. This deliverable therefore uses an Afrihost-compatible local project and preview. No substitute Worker deployment is presented as an Afrihost release.

## Boundaries

- `public/`: the only web document root; PHP front controller and optimized assets.
- `templates/`: escaped server-rendered pages with progressively enhanced JavaScript.
- `src/`: persistence, authorization, commerce rules, compatibility, PDFs, JSON routing and provider adapters.
- `database/`: relational schema and dated source catalog snapshot; seed data is imported into SQL, not read as the final runtime catalog.
- `bin/`: explicit maintenance, migration and administrative command-line operations.
- `storage/`: private local database/log state, never publicly served.
- `ios/`: a separate SwiftUI foundation consuming the same product API.

## Data and transaction model

Products and variants have separate identities. Orders and quote line items capture immutable prices and product descriptions; catalog edits do not rewrite history. Retail and dealer price selection belongs to the server. Financial values use integer minor units (ZAR cents), stored as exact `BIGINT`, avoiding binary floating-point calculations. Convert provider decimal strings at the boundary. The API exposes `*_cents` explicitly. This is an exact-money implementation rather than decimal arithmetic scattered through templates.

Normalized tables cover users/roles, categories/brands, products/variants/images, addresses, dealer applications, carts/items, orders/items, payments/events, shipments/events, quotes/items, saved builds/components, wishlists, tickets/messages, consent, campaign drafts/outbox, resets/rate limits, settings, inventory snapshots, integration jobs and audit logs. Foreign keys and uniqueness constraints protect identity. Query indexes support catalog categories/brands, ownership, cart expiry and outbox processing.

Checkout must re-price and validate stock in a transaction, apply a conditional inventory decrement and use a unique idempotency key. No browser amount or role is authoritative. Guest ownership uses session tokens; authenticated resources are user-bound. Dealer card checkout is denied on the server. Missing live provider or stock verification prevents a real order from being represented as payable.

## Source data policy

The initial 93 products are dated public CRM catalog snapshots. Their names, descriptions, published prices, available variants and imagery are sourced from the existing store. Category mappings are editorial migration work and need merchant review. Public availability does not reveal stock quantities. Preview stock is explicitly demonstration data. Unprovided SKUs, VAT treatment, dimensions, warranties and compatibility specifications must not be invented. Separate named demo components exercise the compatibility engine.

Each retained product preserves its existing `/products/{slug}` route. An entire-catalog migration still needs a complete source export, redirect inventory, SKU validation, price/tax checks and Fincon mapping. The snapshot is not represented as the complete business catalog. Asset provenance is in `ASSET-SOURCES.json`.

## Integration and operational strategy

Use provider interfaces with timeouts and explicit unavailable states. Secrets remain server-side. Fincon is the inventory authority only after its actual API version and mapping are established. A failed sync retains prior data and timestamps. Payment return URLs never mark orders paid; only authenticated, amount-matched, reconciled provider events may do so. Shipment creation must retain external references and reconcile uncertain outcomes before retrying.

Cron work should use a lock and bounded batches. Expire only inactive carts after 24 hours; retain order/payment/audit records. Marketing must use opt-in eligibility, suppression and delivery-event processing before sending is enabled. No campaign is sent by this build operation. No third-party paid account is opened.

## Working milestones

1. Research and platform contract: verified public catalog/hosting evidence; design tokens; SQL and API contract.
2. Storefront: responsive homepage, category/search, product details and SSR navigation.
3. Commerce: database persistence, authentication, cart, test checkout, quotes, dealer controls and customer dashboard.
4. Advanced journeys: builder validation, saved/shared builds, comparisons, wishlist, support and administrative actions.
5. Provider boundary: adapters and negative-path tests; real sandbox verification remains separately gated.
6. Delivery: automated checks, desktop/mobile browser QA, deployment and backup instructions, source archive.
7. iOS: native catalog, local saved list and sharing foundation; signing/review are separate release activities.

See `STATUS.md` for the final implementation and verification inventory. This architecture is a foundation; production readiness is a tested release condition, not an architectural claim.
