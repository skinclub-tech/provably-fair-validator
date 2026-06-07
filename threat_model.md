# Threat Model

## Project Overview

This project is a small public PHP web app that lets users paste skin.club roll JSON and independently recompute the expected roll and, for regular rolls, the public hash. The production deployment is an autoscaled public web app with no database and no outbound API calls; the only server-side dynamic logic lives in `index.php` and the legacy public route `vanilla-checker.php`.

## Assets

- **Verification integrity** — users rely on the application to recompute the roll correctly and present trustworthy results. If attackers can alter the rendered result or script the page in the victim's browser, they can mislead users about fairness outcomes.
- **User-supplied roll payloads** — pasted JSON can include server seeds, salts, beacons, and other roll data that users may reasonably expect to stay confined to the page they submitted.
- **Deployment reputation and origin trust** — the app is hosted on public `roll.skin.club` / `roll.cs2.club` origins. Script execution on these origins would let an attacker present arbitrary trusted content to victims.

## Trust Boundaries

- **Browser to PHP application** — all request data (`$_POST`, headers, host) is untrusted and must not be rendered into HTML unsafely.
- **Public internet to public routes** — both `index.php` and `vanilla-checker.php` are unauthenticated, production-reachable surfaces.
- **Server-side verification logic to rendered HTML** — computed results and echoed request values cross from data handling into browser-executed markup; output encoding must be context-appropriate.
- **Request host to generated external link** — the current request host influences the computed logo destination in `index.php`, so host-derived values must be treated as attacker-controlled unless proven otherwise.

## Scan Anchors

- **Production entry points:** `index.php`, `vanilla-checker.php`
- **Highest-risk code areas:** POST handling, `json_decode`, HTML generation, any direct interpolation into response markup
- **Public surface:** all application routes are public; there are no authenticated or admin-only areas
- **Usually dev-only:** `tests/`, `scripts/`, `.devcontainer/` unless deployment wiring changes

## Threat Categories

### Tampering

The application must treat all pasted JSON as untrusted and ensure user-controlled values cannot alter the structure of the rendered page beyond intended text output. Verification results must be derived only from the cryptographic calculations in server-side code, not from attacker-controlled markup or client-side script execution.

### Information Disclosure

The app should only reveal the values the user intentionally submitted and the derived verification output. User-provided data must not become accessible to injected scripts, reflected markup, or verbose error paths that let a third party read pasted roll contents from a victim's browser session.

### Denial of Service

Because the service is a public unauthenticated form endpoint, it must avoid expensive attacker-triggerable processing and should rely on bounded request sizes and simple computation. Inputs should remain size-limited enough that a single request cannot materially degrade service availability.

### Elevation of Privilege / Injection

There is no account system, but browser-side code execution on the trusted deployment origin is still a meaningful security boundary failure. No user-controlled request field should be inserted into HTML without escaping, and no route should allow attacker input to become executable markup or script.
