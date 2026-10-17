---
name: Input handling and messaging decisions
description: User decisions (2026-10-17) on which input auto-fixes and message changes to make or skip.
---

- Missing commas between fields are auto-repaired (the site's copy-by-selection format). Further auto-fixes (missing braces, curly quotes) are deferred until analytics show a need.
  **Why:** the user wants to look at the `invalid_json` hint data first (`starts_with_brace`, `ends_with_brace`, `has_smart_quotes`).
  **How to apply:** check analytics for those hints before proposing more input tolerance.
- The "seed not revealed" warning stays as is: no reveal instructions, no "save the public hash" advice.
  **Why:** the user declined it.
- Production error display (`display_errors`) is left unchanged.
  **Why:** the user declined, together with not touching vanilla-checker.php.
