#!/bin/bash
set -e

# Provably Fair Validator is a plain PHP app with no package manager,
# build step, or database migrations. Nothing to install or compile —
# verify the entrypoint parses so a broken merge is caught early.
php -l index.php
