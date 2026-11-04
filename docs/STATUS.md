# Implementation status

Development release dated 4 November 2026. This inventory is intentionally explicit: a working local application and tested adapter are not the same as a verified production integration.

| Area | Delivered behavior | Remaining release work |
|---|---|---|
| Visual storefront | PHP-rendered homepage, navigation, catalog, product pages and consistent customer/business/admin layouts; real source imagery and self-hosted fonts. | Merchant visual/content acceptance and full cross-browser/device accessibility review. |
| Catalog | SQL persistence; 93 source snapshots; brands and seven categories; variants; search, category/brand/price/stock/sort/pagination; existing product slugs. Protected product/category/variant changes. | Full source import, complete subcategory tree, configurable technical facets, advanced typo tolerance, image upload/gallery management and dealer-only catalog visibility. Initial specifications retain source title details rather than a fully normalized technical dictionary. |
| Accounts | Password hashing, login/register/reset-token flow, CSRF, rate limits, server roles, profile name and saved addresses. | Verified email change/verification delivery, optional MFA and complete preference management. Reset emails queue locally until delivery worker is connected. |
| Dealers | Application, pending status, administrative decision/Fincon account field, server-selected dealer variant price and EFT-only enforcement. | Document uploads/review, approved tier policy, actual Fincon debtor validation, dealer-only catalog rules and proforma workflow. |
| Cart | Database cart, guest/account isolation, quantities, server prices, coupon validity, stock checks and 24-hour expiry. | Production reservation expiry/reconciliation policy, shipping estimates and cross-device merge policy acceptance (own unexpired carts merge under locks). |
| Checkout | Transactional test EFT orders, conditional stock decrement, idempotency, immutable line snapshots, private order access and controlled administrative transitions. | **Production checkout is closed in code.** Complete fresh Fincon validation, exact VAT/shipping totals, verified EFT instructions, production stock reservations, provider handoff, proof-of-payment uploads, refunds and invoice/fulfilment orchestration before opening it. |
| PayFast | Hosted-form adapter and verified ITN handler with merchant/signature/source/amount/provider checks; payment event dedupe and exact-money fixtures. | Actual sandbox account test and a fully connected checkout-to-provider journey. No successful live payment is claimed. |
| PC builder | Eight groups, live totals, purpose/budget, rule-based compatibility notices, saved/shareable builds, cart conversion and PDF quote. | Verified technical attributes for real sellable parts, manufacturer/BIOS review, alternative suggestions, optional extras and assembly-service rules. The initial selectable fixture set is explicitly synthetic. |
| Wishlist/comparison | Saved products and up to four compared products. | Complete multi-device comparison persistence and configurable difference-highlighting preferences. |
| Quotations | Database snapshots, owner-protected paginated PDF, build/cart source, promotion discount and expiry. | Merchant quote review/edit/approve/send workflow, accepted-quote conversion, verified VAT/company detail fields and final proforma/invoice templates. |
| Support | Private tickets, category, replies, staff/admin API boundaries, status and priority controls. | Private attachments, assignment UI, full SLA reporting and delivery notifications. |
| Administration | Database-derived metrics, product/variant/category edits, dealer review, order transitions, settings, campaign drafts and audited order CSV export. | Pagination for every large management view, full customer management, shipping rules, technical facet settings, refund reconciliation and fine-grained configurable permission matrix. |
| Fincon | Injected, version-specific normalization boundary with exact money, duplicate/invalid-row rejection and unavailable/empty-sync safeguards. | Actual version/contract/credentials, persistence jobs, warehouse/tax/SKU/debtor mapping, order export/reconciliation and monitoring. |
| Shipping | Bob Go rate/booking/tracking/waybill HTTP adapter and explicit unavailable boundaries for pending providers. | Real account verification; checkout quote binding, dimensions/addresses, booking dedupe orchestration, tracking webhooks; Internet Express contract and Pargo map/pickup flow. |
| Email/marketing | Consent storage, transactional outbox, campaign drafts, Brevo single-recipient adapter, expiry cleanup cron. | Delivery dispatcher/retry/reconciliation, sender verification, suppression/bounce/double-opt-in workflows, scheduled campaigns and abandoned-cart recovery. No external messages have been sent. |
| Assistant | Catalog retrieval with real recorded product fields; explicit catalog-helper status; optional bounded ranking callback. | Chosen AI provider, secret setup, budget/rate policy, knowledge-base approval and model evaluation. No live AI service is claimed. |
| SEO | Server-rendered public catalog/product routes, titles/descriptions/canonical, robots/sitemap, factual Organization/Product/Breadcrumb structured data without unsupported offers/reviews, preview noindex; current product slugs preserved. | Complete migration redirect inventory, merchant-approved policies, Search Console and consented analytics. |
| Operations | Apache routing, environment example, cron, private backup script, local SQLite/MariaDB checks, deployment/recovery guide. | Actual Afrihost verification, external monitoring/alerting, encrypted offsite backup schedule and independent recovery drill. |
| iOS | Separate SwiftUI Xcode project foundation: catalog API, native navigation, persisted saved list, native share and configuration documentation. | Full SDK build/device QA, secure authenticated account/orders, keychain sessions, push/deep links, signing, App Store Connect and Apple review. |

## What prevents live trading

Some work needs external access: Afrihost account capabilities; real Fincon API documentation and mappings; payment/courier/email accounts; verified business contact, banking, tax and policies; iOS signing. Other items above are remaining engineering, not credential-only gaps. The existing live CRM website was not changed, and this application has not been deployed to Afrihost or submitted to Apple.

The initial catalog was read from public endpoints; no private customer/order/accounting records were accessed. Product images remain traceable to source URLs. No fabricated reviews, certifications, shipping guarantees or live courier rates were added.

## Next implementation sequence

1. Merchant catalog and tax/stock contract, full technical attributes and product import validation.
2. Live commerce state machine with reservations, delivery quote binding, final totals, payment initiation/reconciliation and verified EFT handling.
3. Fincon sync/export jobs and shipping orchestration with account-specific acceptance fixtures.
4. Email dispatcher, secure attachments, quote approval/conversion and complete administrative modules.
5. Afrihost staging deployment and actual multi-browser/device/performance acceptance before opening checkout.
6. Authenticated iOS client and signed device build after web API release gates pass.
