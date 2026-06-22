---
name: php-builtin-server is single-threaded — never block a request on outbound I/O
description: Why the autoscale deployment had intermittent outages and the rule that prevents it
---

The deployment runs the app on PHP's built-in web server (`php -S ... router.php`). That server is **single-threaded — it serves exactly one request at a time**. While one request is blocked, every other request (including the platform health check on `/`) is queued and will time out.

**Incident:** A `/replit-badge.svg` route in `router.php` did a synchronous server-side `curl` to replit.com (up to ~5s timeout) on every page load, because the browser requests the badge `<img>`. When the upstream was slow, each badge fetch tied up the single worker, health checks got `connection refused` / `status 500`, and the platform marked the instance down → many short "outage" periods over a day.

**Why:** single-threaded `php -S` + per-request blocking outbound I/O = self-inflicted DoS under any concurrency.

**How to apply:**
- Never perform blocking outbound network calls (curl/file_get_contents to remote hosts) inside the request path on this server.
- Serve third-party assets (like the Run on Replit badge) as **local static files** committed to `assets/`, referenced same-origin. Same-origin also sidesteps the COEP/CORP cross-origin image blocking that originally motivated the proxy — without any runtime fetch.
- Keep router.php branches cheap and CPU-only (host checks, static text like robots.txt). If a future feature truly needs outbound I/O, do it out-of-band (build step / cron / cached file), not in the request handler.
