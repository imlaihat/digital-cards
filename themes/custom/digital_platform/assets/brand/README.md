# Ropleon production brand assets

Runtime source: `Ropleon_Drupal10_Production_Brand_Handoff_v1.0.0_2026-08-10`.
The supplied manifest was verified before integration; all 128 declared files
were present and matched their SHA-256 checksums.

Primary runtime assets:

- `ropleon-technologies.svg` — approved corporate signature for Ropleon
  Technologies, public corporate pages, and company-level portal surfaces.
- `ropleon-cards.svg` — approved Ropleon Cards product signature for product,
  login, platform, organization, merchant, and digital-card surfaces.
- `ropleon.svg` — compatibility alias of `ropleon-technologies.svg`; retained so
  older cached templates do not break.
- `favicon.svg`, `favicon.ico`, `ropleon-icon-*.png` — browser icons.
- `apple-touch-icon.png`, `ropleon-app-icon-*.png`, and
  `ropleon-maskable-*.png` — mobile/PWA icons.
- `site.webmanifest` — web-app identity metadata.

Approved variants are retained under:

- `corporate/svg` and `corporate/png`
- `cards/svg` and `cards/png`

Do not edit, stretch, recolor, shadow, rotate, or reconstruct these files.
Use SVG for web UI whenever possible. The page attachment hook adds a file
timestamp query string so deployed updates bypass stale browser/CDN caches.
