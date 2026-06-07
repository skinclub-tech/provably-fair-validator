---
name: llms.txt host gating
description: How and why /llms.txt is served only from the roll.skin.club host via router.php.
---

# llms.txt host gating

`router.php` is the PHP built-in server router (passed as the final arg to `php -S`,
in both the dev workflow and the deployment run command). Its only job: return 404
for `/llms.txt` unless `HTTP_HOST` (port stripped, lowercased) is exactly
`roll.skin.club`. Every other request `return false`s to the default static handler
(which still executes index.php as the directory index).

**Why:** the user explicitly required llms.txt to be served only from the canonical
roll.skin.club host, not the other deployment domains (roll.cs2.club,
provably-fair-validator.replit.app).

**How to apply:** any change to a run command (dev workflow or deployment) must keep
`router.php` as the final `php -S` argument, or the restriction silently stops
working. Validate with `curl -H "Host: roll.skin.club" .../llms.txt` (expect 200)
vs another host (expect 404).
