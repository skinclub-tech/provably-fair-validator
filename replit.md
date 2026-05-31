# Provably Fair Validator

## Overview
A single-file PHP application that lets skin.club platform users independently verify that their rolls were generated fairly.
Users paste the JSON roll data from skin.club's "Check Roll" page and the app re-computes the roll (and, for regular rolls, the public hash) to confirm it matches.

Runs on PHP's built-in server (`php -S 0.0.0.0:8080 -t .`) with `index.php` as the entrypoint.

## Environment
- PHP 8.4 is installed as a Replit (Nix) module so it is available in both the
  development shell and the production deployment container.
- Deployment target: autoscale.

## User preferences
- Never edit or delete the `vanilla-checker.php` file.
