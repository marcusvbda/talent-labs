# Public landing — concept notes (owner-approved direction, 2026-09-30)

Companion to `../spec.md`. If this file disagrees with the spec, the spec wins.

## Look and feel

- Same system as the logged-in app: warm gray canvas, white cards, orange accent, black primary, dark contrast card, large radii, pills, Geist. Tokens come from `resources/css/app.css`; nothing is invented.
- Distribution/animation/content inspiration only (never copy brands): slite.com, reflect.app, humblytics.com, more-nutrition.webflow.io, customer.io. Palette and style come from the logged-in product.
- Imagery is real product UI. No stock photos, no generic AI art, no fake logos, no fake testimonials, no invented numbers.

## Hero

Real components animated in a loop (not video/GIF), fed by local demo data. Fictional jobs and companies. Respect `prefers-reduced-motion`, pause when the tab is hidden.

## What we never reveal

Job sources, how contacts are verified, funnel numbers, internal limits, where AI is used.

## What we must state truthfully

Gmail is used only to send the applications the client chooses or allows; the inbox is never read. Google compares the homepage with the requested OAuth scope.

## Owner decisions taken in this round

- "Ask for access" buttons are not interactive: they read **Close beta** and do nothing (non-clickable label). Sign in stays a normal link.
- Brand name, logo and domain will change later: everything brand-related must come from the single brand source (`config('talent.brand')`, `Logo` pattern).
