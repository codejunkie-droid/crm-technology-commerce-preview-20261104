# Native iOS foundation

`ios/CRMTechnology.xcodeproj` is a separate SwiftUI iOS application scaffold, targeting iOS 17+. It has native tab/navigation views, live catalog search and pagination, pull-to-refresh, device-persistent saved products with offline metadata, native product sharing and a `crmtechnology://product/<slug>` deep-link handler. It reads the same public `/api/products` and `/api/products/{slug}` endpoints as the website. The website runs independently of this app.

## Run

1. Install a current supported full Xcode on macOS, with an iOS Simulator runtime. Command Line Tools alone cannot build an iOS application.
2. Open `ios/CRMTechnology.xcodeproj` and select the `CRMTechnology` scheme.
3. Under the target's Build Settings set `COMMERCE_BASE_URL` to the verified **HTTPS** staging origin. The shipped `.invalid` URL deliberately prevents accidental production calls. Set the value through Xcode's editor, preserving the full `https://` URL. No broad App Transport Security exceptions are included.
4. Choose an iPhone simulator and Run. For a physical device, select the correct development team and signing identity. Confirm that `za.co.crmtechnology.store` is available and owned by CRM before registering the bundle identifier.
5. Verify catalog search, pagination, error/retry handling, VoiceOver, larger text, rotation, offline saved items, share sheet and product links.

The code uses integer cents and Decimal for currency display; it does not perform price arithmetic using floating point. Saved item prices are explicitly described as potentially out of date. Checkout opens the authoritative web product page and does not calculate or confirm payments on the device.

## Authentication and order tracking: remaining work

The web API currently uses first-party session cookies and CSRF tokens. The iOS foundation does **not** claim a native authenticated account or native checkout implementation. Do not put a dealer role, provider secret or administrative token into the app bundle.

Before adding native accounts, choose either the same HTTPS session with correctly managed cookie storage plus `/api/session` CSRF or a dedicated, audited mobile token flow. For a token flow implement short-lived access tokens, rotating refresh tokens, server-side token hashing/revocation, device logout, rate limits and ownership checks. Store device secrets in Keychain with a suitable accessibility class; never UserDefaults. Native order detail/tracking must use an authenticated, owner-scoped API; a guessed order ID cannot grant access. Shared saved lists should sync only after authentication and merge explicit user changes.

Push notifications are not implemented: they require an APNs entitlement, signing, server-side device-token lifecycle and explicit opt-in. Keep payment/order personal details out of notification payloads. Universal links are also pending: configure the Associated Domains entitlement and the site's `apple-app-site-association` only after the production domain and Apple team are verified. The supplied custom URL scheme grants no authority and only opens a fetched product.

## App Store preparation

CRM needs its own Apple Developer organization membership, signing credentials, App Store Connect access and an App Store listing. Apple's published membership price is USD99/year or local currency where offered; enrollment requirements and regional taxes apply. See [Apple membership](https://developer.apple.com/programs/whats-included/) and [organization enrollment](https://developer.apple.com/programs/enroll/).

Before submission, add approved app icons and launch assets, complete privacy disclosures and any required privacy manifest, host verified privacy/support/account-deletion URLs, finish native account/order functionality, test on physical iPhone/iPad devices and supply reviewer access to a working backend. Inspect all third-party code for its privacy obligations. No third-party SDKs are currently bundled.

Apple evaluates apps individually. A website wrapper does not guarantee approval; [review guideline 4.2](https://developer.apple.com/app-store/review/guidelines/#minimum-functionality) requires adequate functionality. Native saved lists, native navigation, deep links and sharing establish a foundation, but this scaffold has **not** been submitted or approved.

## Verification completed

- `swiftc -parse` succeeds for all four Swift source files.
- `plutil -lint` succeeds for `Info.plist` and `project.pbxproj`.
- A full build, simulator run and device test were **not possible** in this environment: `xcodebuild -version` reported that the active developer directory contains Command Line Tools rather than full Xcode. Syntax parsing does not prove successful iOS SDK compilation.
