---
name: Analytics tracker load timing
description: The proxy-injected analytics script loads async, so page-load custom events can be lost.
---

The published page receives the analytics script from the deployment proxy as an `async` script placed after the app's own deferred script. The tracker may not exist yet when DOMContentLoaded fires.

**Why:** Page-load result events were far fewer than form-submission events. Interaction-time events, such as clicks and submits, fire after the tracker has usually loaded.

**How to apply:** Do not treat page-load event counts as complete. Queue page-load events until the tracker is available, or keep that undercount in mind when reading analytics.
