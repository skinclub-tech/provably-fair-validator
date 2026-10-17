---
name: Rewriting unpushed git history on Replit
description: Which refs to rewrite (and which to leave alone) when scrubbing data from local, unpushed commits.
---

- Rewrite only `main`: `git reset --soft <last clean commit>`, then recommit as `Replit Agent <agent@replit.com>` with a `Replit-Commit-Author: Agent` trailer. A push from `main` stays a fast-forward when the clean commit descends from `origin/main`.
- Leave `replit-agent`, `refs/replit/agent-ledger` and `gitsafe-backup/main` alone. They are Replit's checkpoint records (each ledger commit merges a checkpoint) and are never pushed to `origin`.
  **Why:** only `main` tracks `origin/main`; editing the internal refs risks breaking rollback.
  **How to apply:** run `git for-each-ref --contains <sha>` to see what holds a commit, and `git log main -S<value>` to confirm nothing remains.
- Rolling back to a checkpoint made before the rewrite brings the old history back into `main`.
