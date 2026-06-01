---
name: clip-path focus rings
description: Why focus rings (outline/box-shadow/drop-shadow) disappear on clip-path buttons and how to fix it
---

# clip-path clips focus indicators

The hexagonal buttons in this app (`.btn-primary`, `.btn-sample`) use `clip-path`.
A focus ring placed directly on such an element is invisible, no matter the method:
`outline`, `box-shadow`, and even a `filter: drop-shadow()` glow all get clipped away.

**Why:** in the CSS element render pipeline `filter` is applied *before* `clip-path`,
and `clip-path` clips the element's entire rendered output — including outlines, box
shadows, and the drop-shadow glow. So nothing painted outside the clipped shape survives.

**How to apply:** wrap the clipped button in an unclipped inline-flex element
(`.btn-focus`) and put the ring on the wrapper via `:has(:focus-visible)`:
`.btn-focus:has(:focus-visible){ box-shadow: 0 0 0 4px rgba(123,79,255,.35); }`.
A rectangular ring around the hexagon is fine and clearly visible. Keep the keyboard-only
behavior by matching `:focus-visible` (not `:focus-within`). For the right-aligned submit
button, move the `margin-left:auto` onto the wrapper (`.btn-focus-end`).
