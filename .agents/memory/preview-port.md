---
name: preview / screenshot port quirk
description: Why app_preview screenshots fail in this repl and how to validate the running app instead.
---

# Preview port quirk

The `app_preview` screenshot tool navigates to `localhost:5000`, but this project's
PHP dev server runs on **port 8080** (devcontainer `forwardPorts: [8080]`, workflow
runs `php -S 0.0.0.0:8080`). So screenshots fail with `ERR_CONNECTION_REFUSED`.

**How to apply:** To validate rendered output, `curl http://localhost:8080/`
(GET for empty state, `POST --data-urlencode 'roll_data=...'` for results) instead
of relying on the screenshot tool.

**Note:** Deployment (`.replit [deployment]`) runs on a different port mapping
(8000 → external 80) — separate from the dev workflow's 8080.

**Validating visuals via `external_url` screenshot:** You can screenshot the
running app through `$REPLIT_DEV_DOMAIN` (proxies to 8080). But the screenshot
service caches by URL — repeated captures of the same URL return the identical
image even after you change files. Append a throwaway query param (e.g.
`?cb=991`) to force a fresh capture. Renaming an asset file alone is not enough
to bust this page-level cache.
