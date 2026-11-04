# Reference audit and design rationale

Inspected public pages on 4 November 2026. This is an evidence-led interface review, not a claim to have purchased products, entered accounts or completed every site's mobile checkout. Public HTML and CRM browser screenshots were used; dynamic controls with inaccessible state remain unverified.

| Reference | Observed principle | CRM application |
|---|---|---|
| [Apple Store](https://www.apple.com/store) | Product-led cards, short editorial headings, category discovery and substantial whitespace. | Give the hero one focal product and keep the category strip easy to scan. Avoid carrying Apple's branding or compositions across. |
| [Newegg](https://www.newegg.com/) and [PC Builder](https://www.newegg.com/tools/custom-pc-builder) | Search by keywords/model, prominent tools, category/brand paths and explicit component groups; builder explains compatibility limits. | Keep search large, expose the builder as primary navigation, show technical details and uncertainty in context. |
| [Micro Center](https://www.microcenter.com/) | Rich technical department navigation and explicit store/shippability context. | Separate availability and fulfilment context from promotional copy; use purpose-led component discovery. No CRM collection promise is inferred. |
| [B&H](https://www.bhphotovideo.com/) and [computers](https://www.bhphotovideo.com/c/browse/Computers-Solutions/ci/9581) | Broad taxonomy with readily visible account/help/returns routes. | Keep technical navigation readable, and surface human support alongside purchasing. Dynamic filter/checkout behaviour was not independently verified. |
| [Scan](https://www.scan.co.uk/) and [3XS](https://www.scan.co.uk/3xs) | Business login and VAT context; custom systems organized by workflow. | Make the dealer journey explicit, label price treatment only when known, and give the builder a useful purpose selector. |
| [Evetech](https://www.evetech.co.za/) | South African gaming/laptop categories organized by CPU, GPU and budget. | ZAR formatting and purposeful filters; avoid unsupported discount/ranking claims. |
| [CRM Technology](https://www.crmtechnology.co.za/) | Cyan striped logo; Laptops, PC, Components, Accessories, Surveillance, Networking, POS; public Shopify catalog and product images. | Retain identity, category intent, legitimate source data and existing product slugs while introducing a consistent design system. |

## Journey decisions

The header prioritizes product search, account, saved products, comparison and cart. Category navigation is usable by keyboard as well as pointer. Product discovery keeps URLs shareable and uses server-rendered results so JavaScript is an enhancement rather than a prerequisite for catalog visibility. Product cards share a consistent image area and action placement. The product page separates photography from purchase details and exposes missing information honestly.

Cart edits show server totals and actionable failures. Checkout separates customer/delivery/payment information, distinguishes preview orders, and refuses unsupported live payment assumptions. Comparison is capped at four products and shows the same source data used in the catalog. The builder groups eight core components and treats incomplete hardware specifications as unresolved evidence, not compatibility approval.

Mobile uses the same hierarchy with reduced columns, accessible controls, horizontal category navigation and stacked purchase content. Admin screens use the same type/spacing system with denser tables and forms. Trust comes from visible provenance, usable support and accurate states; review counts, delivery guarantees, certifications and affiliations are not manufactured.

## Limitations and launch review

The initial source import contains inconsistent original descriptions and missing vendor SKUs. Some original images may represent product families rather than the exact configuration; merchant review must confirm each image/specification pairing. Existing source photography is retained for this requested rebuild, but the merchant must confirm continuing commercial rights before public launch. Current shipping/returns/company details require verified merchant content; generic policy placeholders must not be treated as legal terms.
