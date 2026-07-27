# Ropleon public experience 3.0.0

This release rebuilds the unauthenticated Ropleon website around the approved
corporate and Ropleon Cards specification. It does not recolor Bootstrap
components. Public routes use a dedicated visual system, content hierarchy,
header, footer, editorial sections, product interface compositions, and
responsive interaction behavior.

## Public routes

- `/` and `/ropleon` — Ropleon Technologies corporate home
- `/solutions` — enterprise capabilities and solution areas
- `/products` — focused Ropleon product overview
- `/products/cards` — Ropleon Cards product landing page
- `/about` — company, vision, mission, and principles
- `/contact` — Drupal Contact with the approved public form experience
- `/privacy` — public privacy information
- `/terms` — public terms of use
- `/user/login` — separate Ropleon Cards authenticated entry point

Language negotiation continues to provide `/en/...` and `/ar/...` variants.
All public copy introduced by this release has an administrator-editable Arabic
translation in `ropleon_brand.ar.po`. Existing Configuration Translation
remains available for editable company and product identity values.

## Design system

- Exact approved navy, blue, cyan, and neutral palette
- Manrope/Inter-compatible Latin stack and IBM Plex Sans Arabic/Noto Sans
  Arabic-compatible RTL stack, with no remote font dependency
- 1,240 px primary content container and 840 px reading container
- 112/80/56 px desktop/tablet/mobile section rhythm
- Reusable buttons, cards, icon family, proof rows, editorial splits, dark
  calls to action, product interface compositions, and public card previews
- Accessible sticky desktop header and keyboard-safe mobile drawer
- Responsive, reduced-motion-aware, RTL-ready behavior

No font files were supplied with the approved assets, so this release uses the
approved fallback stacks and does not contact a third-party font CDN. Locally
licensed WOFF2 fonts can be self-hosted later without changing the component
architecture.

## Official assets

The supplied files are used directly:

- `themes/custom/digital_platform/assets/brand/ropleon.svg`
- `themes/custom/digital_platform/assets/brand/ropleon-cards.svg`
- `themes/custom/digital_platform/assets/brand/favicon.svg`
- `themes/custom/digital_platform/assets/brand/apple-touch-icon.png`

The corporate logo appears in the public corporate header and footer. The
Ropleon Cards logo appears within product content, not as a competing corporate
header logo. The approved favicon is the only favicon source.

## Preserved product behavior

Authenticated Platform Admin, Organization Portal, and Merchant Portal routes,
permissions, workflows, dashboards, QR/NFC behavior, card generation, offers,
loyalty, redemptions, and organization-specific static card themes are not
renamed or replaced by this release. Stable public card paths remain
`/c/{nfc_id}`.

## Apply after extracting

From the Drupal root on Windows:

```powershell
$php = 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\core\php83\php.exe'
& $php .\vendor\drush\drush\drush.php php:script .\scripts\apply-ropleon-public-v3.0.0.php
```

The script imports Arabic translations, rebuilds public routes, maps the front
page to `/ropleon`, and rebuilds caches. It does not deploy, alter card content,
regenerate static cards, or change product permissions.

## Manual configuration

1. Confirm the public contact recipient at
   `/admin/structure/contact/manage/feedback`.
2. Review translatable brand values at
   `/admin/config/system/ropleon-branding` and Configuration Translation.
3. Optional: supply approved 1,200 × 630 Open Graph images as
   `og-ropleon.jpg` and `og-ropleon-cards.jpg` in the brand asset directory.
4. Test the English and Arabic routes listed above before production release.

