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
