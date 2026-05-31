# Provably Fair Validator

## Overview
A PHP web application for validating provably fair results. Runs on PHP's built-in
server (`php -S 0.0.0.0:8080 -t .`) with `index.php` as the entrypoint.

## Environment
- PHP 8.4 is installed as a Replit (Nix) module so it is available in both the
  development shell and the production deployment container.
- Deployment target: autoscale.

## User preferences
- Never edit or delete the `vanilla-checker.php` file.
