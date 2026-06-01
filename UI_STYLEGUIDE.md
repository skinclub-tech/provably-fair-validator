# UI Styleguide — Provably Fair Validator

> **Read this before making any UI change.** This file is the single authoritative
> reference for the look and feel of the app. Every color, font, spacing rule, and
> component pattern in `index.php` is documented here. When you make a new visual
> decision, **update this file in the same task** so it never drifts from the code.
> If a value here disagrees with `index.php`, the code wins — fix this file.

All styles live in the inline `<style>` block inside `index.php` (single-file PHP app).

---

## Design Principles

- **Dark, premium gaming aesthetic.** Deep near-black purple backgrounds with a
  soft purple radial glow, glassy gradient cards, and angular clip-path buttons.
- **Single-column, centered.** Content is capped at **760px** and centered; the
  layout is one vertical column on all screen sizes.
- **Lean and focused.** No clutter, no decorative badges, no emoji in headings.
  Every element earns its place.
- **No emoji** in headings or UI copy. Use HTML entity icons (checkmarks, crosses,
  warning/info glyphs) only where they carry meaning.
- **Plain-language, reassuring copy.** Verification results explain what happened
  in human terms; the calculation steps walk users through the math.
- **Accessibility-aware contrast.** White text only sits on the darker purple
  gradient stops (`--brand-grad-1/2`), never on the lighter brand purples, to keep
  contrast AA-safe.

---

## Color Palette

Defined as CSS custom properties on `:root`.

### Backgrounds & surfaces
| Variable | Value | Purpose |
|---|---|---|
| `--bg` | `#0d0b1a` | Page background base; also inputs, cmp-cards, spoiler bodies |
| `--bg-2` | `#14112a` | Lower stop of card gradient |
| `--panel` | `#1a1633` | Upper stop of card gradient |
| `--panel-2` | `#221c40` | Inline `code` chips, ghost button background |

### Borders
| Variable | Value | Purpose |
|---|---|---|
| `--border` | `#322a5c` | Default border for cards, inputs, chips, rows |
| `--border-soft` | `#272150` | Subtler divider (header bottom border) |

### Brand purples
| Variable | Value | Purpose |
|---|---|---|
| `--brand` | `#7B4FFF` | Primary brand purple; focus rings, hover accents |
| `--brand-2` | `#9B6FFF` | Lighter brand purple; highlighted hash slice |
| `--brand-link` | `#b29bff` | Link text, spoiler summaries, emphasized labels |
| `--brand-grad-1` | `#6A3DEB` | Darker gradient stop — **carries white text (AA-safe)** |
| `--brand-grad-2` | `#7548F5` | Darker gradient stop — **carries white text (AA-safe)** |

Additional brand values used directly (not as variables):
- Primary button background: `#5c49d0`
- Sample button background: `#282546`; ghost button hover: `#2c2656`
- Header `h1` color: `#f5f5fa`
- Monospace value text: `#cdd6ff`
- Sample-data label: `#6b6a8a`

### Status colors (each has a base + 12% translucent background)
| Status | Base | Background var | Use |
|---|---|---|---|
| OK / success | `--ok` `#34d399` | `--ok-bg` `rgba(52,211,153,.12)` | Verified banners, matching verdicts |
| Fail / error | `--fail` `#f87171` | `--fail-bg` `rgba(248,113,113,.12)` | Failed verification, error callouts |
| Warn | `--warn` `#fbbf24` | `--warn-bg` `rgba(251,191,36,.12)` | Seed-not-revealed warnings |
| Info | `--info` `#60a5fa` | `--info-bg` `rgba(96,165,250,.12)` | Informational callouts |

Status icon chips use a slightly stronger `.18` alpha of the same hue.

### Text
| Variable | Value | Purpose |
|---|---|---|
| `--text` | `#e7eaf6` | Primary body text |
| `--muted` | `#a7afd1` | Secondary/explanatory text, labels |

---

## Typography

- **Family:** `--font: "Montserrat", sans-serif`, loaded from Google Fonts
  (weights 400, 500, 600, 700, 800). Montserrat/Inter is preferred over system
  fonts for the brand feel.
- **Base size:** `html { font-size: 15px }`; `body` line-height `1.5`.
- **Font smoothing:** antialiased / grayscale enabled on `body`.
- **Weights in use:** 400 base, 600 for labels/links, 700 for headings & strong
  values, 800 for the `h1` and step numbers / highlighted hash.
- **Headings:** `h1` 1.5rem / weight 800; card `h2` 1.05rem / weight 700.
- **Monospace** (for hashes, code values, formulas): `ui-monospace,
  SFMono-Regular, "SF Mono", Menlo, Consolas, monospace`. Applied via `.mono` and
  to `textarea`, `code`, calc formulas/values. Mono code values use color `#cdd6ff`.

---

## Spacing & Layout

- **Body padding:** `32px 18px 64px`.
- **Content wrapper:** `.wrap { max-width: 760px; margin: 0 auto }`.
- **Card gap:** cards use `margin-bottom: 28px`, padding `28px`.
- **Header:** `gap: 32px`, `margin: 8px 0 34px`, `padding-bottom: 20px`, with a
  `--border-soft` bottom border.
- **Border radius:** `--radius: 16px` for cards; smaller elements use 9–12px;
  pills/chips use `999px`.
- **Shadow:** `--shadow: 0 18px 50px rgba(0,0,0,.45)` on cards.
- **Body background glow:** two purple radial gradients over `--bg`:
  - `radial-gradient(1100px 600px at 12% -10%, rgba(123,79,255,.26), transparent 60%)`
  - `radial-gradient(900px 500px at 100% 0%, rgba(155,111,255,.20), transparent 55%)`
- **Responsive:** at `max-width: 520px`, `h1` shrinks to 1.25rem and comparison
  rows stack vertically (left-aligned values).

---

## Component Patterns

### Cards (`.card`)
- Background `linear-gradient(180deg, var(--panel), var(--bg-2))`, `1px` `--border`,
  `--radius` (16px) corners, `--shadow`, `28px` padding.
- `h2`: 1.05rem / 700. `p`: muted, 14px.

### Primary button (`.btn.btn-primary`)
- **Angular clip-path shape:** `polygon(14px 0, calc(100% - 14px) 0, 100% 50%,
  calc(100% - 14px) 100%, 14px 100%, 0 50%)` (hexagonal arrow edges).
- Background `#5c49d0`, **white text** (`#ffffff`) — never dark text on purple.
- Uppercase, weight 700, letter-spacing `.04em`, padding `11px 50px`,
  `margin-left: auto` (right-aligned in `.actions`).
- Hover: `filter: brightness(1.08)`; active: `translateY(1px)`.

### Sample buttons (`.btn-sample`)
- Subdued and smaller than the primary CTA, right-aligned in the label row.
- Same angular clip-path (12px insets), background `#282546`, uppercase 11px / 700.
- Preceded by a muted `.sample-label` ("Sample data:", `#6b6a8a`, 12px).
- Hover: `filter: brightness(1.2)`.

### Ghost button (`.btn-ghost`)
- Background `--panel-2`, `--border`, normal text; hover swaps to `#2c2656` with a
  `--brand` border. (Defined for reuse; not currently placed in markup.)

### Textarea
- Full-width, auto-sizing (JS grows height to content), min-height 80px.
- Background `--bg`, `--border`, 12px radius, monospace, 14px-ish.
- Focus: `--brand` border + `0 0 0 4px rgba(123,79,255,.18)` glow ring.

### Detected-type pill (`.detected-type`)
- Inline pill, `999px` radius, translucent purple bg, `--border`, muted text;
  the roll-type word is emphasized in `--brand-link`.

### Result banners / summary (`.summary`)
- Flex row with a 40px rounded icon chip + title/subtitle stack.
- **Color-coded by status:** `.summary-ok` uses OK bg + green icon/title;
  `.summary-fail` uses fail bg + red icon/title. Checkmark `&#10003;` / cross
  `&#10007;` glyphs as icons.

### Comparison card (`.cmp-card`)
- `--bg` background, `--border`, 12px radius. Uppercase muted title.
- Two rows ("Provided by site" / "Recalculated here") with right-aligned bold
  values (mono for hashes). Closing **verdict** line tinted OK-green or fail-red.

### Calculation spoiler (`.calc-spoiler` / `<details>`)
- Collapsible `<details>` with a `--brand-link` summary; custom `▸` marker that
  rotates 90° when open (no default disclosure triangle).
- Numbered steps: gradient circle badge (`--brand-grad-1`→`--brand-grad-2`, white
  800-weight number) + label, muted sublabel, italic mono formula, mono value.
- Highlighted hash slice uses `--brand-2`, weight 800.
- **Spoiler text is ~1px smaller** than the equivalent element outside a
  `<details>` (a deliberate set of `details ...` font-size overrides).

### Callouts (`.callout`)
- Flex row: glyph icon + title/body stack, `--border`, 12px radius.
- Variants `error` (fail), `warning` (warn), `info` — each tints background, icon,
  and title with its status color. Warning glyph `&#9888;`, info `&#8505;`.

### Field glossary (`.field-glossary`)
- A `<details>` reusing `.calc-spoiler` styling; `dl/dt/dd` list explaining each
  JSON field. `dt` bold `--text`, `dd` muted; inline `code` chips for field names.

### Header (`.site-header`)
- Flex row: linked skin.club mark (32px SVG) + `h1` "Verify your Rolls".
- 32px gap, bottom border (`--border-soft`).

### Footer (`.footer`)
- Centered, muted, 14px. Vertical stack (`.footer-links`, 16px gap) holding the
  "Run on Replit" badge and a "Source Code" link. Links `--brand-link`,
  underline on hover only.

---

## Motion
- Results fade in: `@keyframes fade` (opacity 0→1, `translateY(6px)`→none, .25s).
- Buttons: subtle `brightness` filter on hover, `translateY(1px)` on active.
- Spoiler marker rotates on open. Keep transitions short (.08–.25s) and restrained.

---

## How to use this guide
1. **Before any UI change**, read this file and prefer existing variables and
   patterns over inventing new ones.
2. **Reuse CSS variables** (`--brand`, `--ok`, `--radius`, etc.) instead of
   hard-coding colors; if you must add a new token, add it to `:root` and document
   it here.
3. **Keep white text on the darker `--brand-grad-*` stops** for contrast; don't put
   white text on the lighter brand purples.
4. **No emoji in headings, no decorative badges** — stay lean and focused.
5. **When you make a new visual decision, update this file in the same task** so it
   stays the single source of truth.
