# Provably Fair Validator

A single-file PHP application that lets skin.club platform users independently verify that their rolls were generated fairly.
Users paste the JSON roll data from skin.club's "Check Roll" page and the app re-computes the roll (and, for regular rolls, the public hash) to confirm it matches.

## Overview

- **Entry point:** `index.php` — contains both the verification logic and the
  page UI/styles.
- **Runtime:** PHP built-in server (`php -S 0.0.0.0:8080 -t .`), configured as
  the `Start application` workflow.
- **Verification logic:**
  - Roll = `HMAC-SHA512(server_seed/beacon, "client_seed-nonce")`, first 15 hex
    chars → decimal → `mod 100000 + 1`.
  - Public hash = `HMAC-SHA256(server_seed, secret_salt)`.
  - Supports both `regular` and `battle` roll types.
  - Input formats from the site: the copy button gives compact JSON with numeric
    `nonce`/`roll`; selecting the roll details gives one `"key": "value"` pair per
    line with no commas (values are strings, unrevealed seeds are `****`). Both
    must keep working; the parser restores missing commas.
- **Deployment:** Autoscale, port 8080 mapped to external 80.
- **GitHub repo:** https://github.com/skinclub-tech/provably-fair-validator (public).

## Analytics
- Custom events: `sample_selected`, `verification_submitted`, `verification_completed`; the last carries `roll_type`, `outcome` and a fixed `reason` code plus formatting hints.
- Never send pasted JSON values (seeds, salts, hashes, rolls) to analytics — only fixed codes, booleans and schema field names.
- The analytics script is injected asynchronously, so page-load events are queued in `assets/app.js` until it is ready.

## Environment
- PHP 8.4 is installed as a Replit (Nix) module so it is available in both the development shell and the production deployment container.
- Deployment target: autoscale.

## User preferences
- Never edit or delete the `vanilla-checker.php` file.
- Republish (deploy) the app after each iteration, without needing to be asked each time.
- Never use real roll data in samples, docs, or tests — use mock values only.
