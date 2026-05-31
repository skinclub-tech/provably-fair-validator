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
- **Deployment:** Autoscale, port 8080 mapped to external 80.
- **GitHub repo:** https://github.com/skinclub-tech/provably-fair-validator (public).

## Environment
- PHP 8.4 is installed as a Replit (Nix) module so it is available in both the development shell and the production deployment container.
- Deployment target: autoscale.

## User preferences
- Never edit or delete the `vanilla-checker.php` file.
- Republish (deploy) the app after each iteration, without needing to be asked each time.
