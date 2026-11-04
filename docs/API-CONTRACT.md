# Working API contract

Application APIs are same-origin `/api/...` routes and return JSON unless a PDF or CSV response is explicitly noted. JSON success returns the object directly (not a data envelope). Errors use `{ "error": "Readable message" }` with a non-2xx status. Browser POST mutations require `X-CSRF-Token` obtained from GET `/api/session` or form field `_csrf`. The provider-only PayFast ITN endpoint is exempt from browser CSRF and uses the verification contract below instead. The session uses a HttpOnly SameSite=Lax cookie; no client role or price is trusted.

## Storefront reads
- GET `/api/session`: `{user, csrf, demo}`. user: `{id,name,email,role}` or null.
- GET `/api/products`: `{products,total,categories,brands,page,pages}`. Query: `q,category,brand,min,max,stock,sort,page`. `min/max` whole ZAR; sort: `featured,price-asc,price-desc,name,newest`.
- GET `/api/products/{slug}`: product with variants. Product fields: `id,variant_id,slug,name,brand,category,category_slug,price_cents,old_price_cents,stock,sku,image,specs,description,is_demo,source_url`. Image is a local URL.
- GET `/api/categories`: `{categories}`.
- GET `/api/cart`: `{items,subtotal_cents,total_cents,count,expires_at,demo}`. Each item: product fields + `quantity,line_total_cents`.
- GET `/api/wishlist`: `{products}`.
- GET `/api/build/options`: `{groups}`; group keys `cpu,motherboard,gpu,ram,storage,psu,case,cooling`, arrays of products.
- GET `/api/build/{share_token}`: saved build, validated parts, summary (no customer data).
- GET `/api/account`: authenticated `{user,orders,quotes,builds,tickets,dealer_application,marketing_consent}`.
- GET `/api/quotes/{id}/pdf`: owner/session protected PDF.
- GET `/api/orders/{id}`: owner/session protected order and items.
- GET `/api/support/{id}`: ticket owner or authorized support/administrator only.

## Mutations
- POST `/api/auth/register`: `name,email,password`; password min 12. Returns session.
- POST `/api/auth/login`: `email,password`; returns session.
- POST `/api/auth/logout`: empty; returns session.
- POST `/api/auth/forgot`: `email`; always generic accepted message; queues reset email to outbox.
- POST `/api/auth/reset`: `token,password`; returns message.
- POST `/api/cart/items`: `variant_id,quantity` (quantity is target; 0 removes). Returns cart. `/api/cart/add`: quantity increment.
- POST `/api/checkout`: `idempotency_key` (16–100 chars), `name,email,phone,address,city,postal_code,payment_method` (`eft` or `payfast`), `delivery` (`quote` or `collection`). Returns `{order,redirect?}`. Demo EFT checkout creates explicitly labeled test orders. **Production checkout currently returns 503 in code**, even with credentials configured; stock reservations, final tax/delivery totals, payment initiation and reconciliation still require implementation and acceptance. Server revalidates demo prices/quantities. Demo card checkout is unavailable.
- POST `/api/dealers/apply`: `business_name,registration_number,vat_number,contact_name,phone,address`; authenticated, enters pending_dealer.
- POST `/api/wishlist`: `product_id`; toggles, returns products.
- POST `/api/build/validate`: `components` object group=>variant_id; returns `{components,total_cents,estimated_watts,status,warnings,missing}`.
- POST `/api/build/save`: same + `name,purpose,budget_cents`; requires sign-in, returns build + share_token.
- POST `/api/build/cart`: same components; validates stock, refuses known incompatibility, adds selected items.
- POST `/api/quotes`: `source` (`cart` or `build`), optional `components,name,email,notes`; returns quote with id and PDF URL. Guest quotes remain session-bound.
- POST `/api/support`: authenticated `subject,category,message`; returns ticket.
- POST `/api/support/{id}/reply`: `message`; owner or support/admin only.
- POST `/api/consent`: `email,consent` bool; logged-in user's email cannot be overridden.
- POST `/api/assistant`: `message`; root integration handler / fallback catalog help.

## Payment notifications

- POST `/api/payments/payfast/itn`: provider form-encoded notification. Unavailable with 503 unless `PAYFAST_ENABLED=1`. It verifies source address, merchant, signature, exact amount, payment method, completion status and provider confirmation before recording an event. Repeated verified events are idempotent. It does not create checkout sessions or make the production checkout route available. Do not invoke it as a browser payment-success shortcut.

## Admin
GET `/api/admin` returns database-derived summary + products, orders, dealer applications, tickets, campaigns, settings and integration status. No fabricated metrics. Admin only (support role has ticket access separately).
- POST `/api/admin/products`: `id? ,name,slug,brand,category_slug,description,image,specs,sku,price_cents,stock,active` creates/updates product and its default variant.
- POST `/api/admin/dealers`: `id,status` (`approved,rejected,information_requested`), optional `fincon_account`.
- POST `/api/admin/orders`: `id,status,reason`; audited state transitions. Paid verification requires explicit `payment_reference` and administrator.
- POST `/api/admin/settings`: allowlisted `key,value`.
- POST `/api/admin/campaigns`: `title,subject,body`; creates draft only. No dispatcher is implemented; delivery still needs retry/reconciliation, verified consent and suppression handling, and an accepted provider setup.
- POST `/api/admin/tickets`: `id,status,priority`.
- GET `/api/admin/export/orders`: capped CSV, audited and formula-escaped.

All HTML pages may use these same Store methods for server-rendered first response. Money is integer ZAR cents everywhere, including database BIGINT; no float financial arithmetic. Demo stock/pricing fixtures are explicitly marked. No seeded admin credential.

## Additional persisted workflows
- POST `/api/account/profile`: `name,email` (email changes require future verification; name editable).
- POST `/api/account/addresses`: optional `id`, `label,address,city,postal_code`; own records only, max 20.
- POST `/api/account/addresses/remove`: `id`; own records only.
- POST `/api/cart/coupon`: `code` (empty removes); cart returns `discount_cents,coupon,total_cents`. Active, unexpired retail codes only; no dealer stacking.
- POST `/api/admin/variants`: optional `id`, `product_id,sku,label,price_cents,dealer_price_cents?,stock,attributes` map. Product details return every variant.
- POST `/api/admin/categories`: optional `id`, `name,slug,description,position`.
- POST `/api/admin/coupons`: `code,percent,expires_at,active`.
