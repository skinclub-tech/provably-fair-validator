---
name: preview/screenshot port quirk
description: Why app_preview screenshots fail in this repl and how to validate the running app instead.
---

# Preview port quirk

The `app_preview` screenshot tool navigates to `localhost:5000`, but this project's
PHP dev server runs on **port 8080** (workflow `Start application` runs
`php -S 0.0.0.0:8080 ...`). So screenshots fail with `ERR_CONNECTION_REFUSED`.

**How to apply:** To validate rendered output, `curl http://localhost:8080/`
(GET for empty state, `POST --data-urlencode 'roll_data=...'` for results), or use
an `external_url` screenshot of `$REPLIT_DEV_DOMAIN` (append a throwaway query param
like `?cb=991` to bust the screenshot service's per-URL cache).

**Note:** the `configureWorkflow` tool rejects webview on any port but 5000, but the
committed `.replit` runs webview on 8080 directly — edit `.replit` via the rebase
config flow (`verifyAndReplaceDotReplit`) rather than `configureWorkflow` if you need
to keep webview on 8080. Deployment also binds 8080 with a single `[[ports]]` 8080→80
mapping; never add a second `[[ports]]` entry mapping to `externalPort = 80`.
