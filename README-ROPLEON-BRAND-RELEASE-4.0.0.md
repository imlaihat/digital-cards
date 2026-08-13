# Ropleon Drupal 10 Brand Integration 4.0.0

This extract-ready overlay integrates the approved production handoff dated
2026-08-10 with the existing Drupal 10 implementation.

## Scope

- Uses the supplied Ropleon Technologies and Ropleon Cards production SVG/PNG
  assets instead of temporary artwork.
- Applies the approved navy, blue, cyan, typography, spacing, radius, shadow,
  gradient, accessibility, responsive, and RTL tokens.
- Updates public corporate, product, login, Platform Portal, Organization
  Portal, and Merchant Portal branding while retaining their separate
  navigation and permissions.
- Adds approved browser icons, Apple touch icon, PWA icons, manifest, social
  sharing images, and metadata.
- Updates the approved company tagline to `Technology. Connected.` while
  retaining administrator-configurable brand settings.
- Includes optional Ropleon Cards static-card theme presets without changing
  or auto-overwriting existing organization themes.

## Preserved platform contracts

- Existing Drupal machine names are unchanged.
- `/c/{nfc_id}` and the scanner API remain intact.
- Digital-card approval, delivery, QR/NFC, social links, offers, points,
  redemptions, subscriptions, groups, and portal permissions are not replaced.
- No menu or block configuration is forcibly re-created.
- Existing organization-specific card themes remain active.

## Package contents

- `themes/custom/digital_platform`
- `modules/custom/ropleon_brand`
- `modules/custom/digital_card_admin`
- `modules/custom/digital_card_delivery/samples/ropleon-brand-presets`
- `scripts/assert-brand-contract.php`
- `scripts/validate-brand-release.php`
- Release documentation in the Drupal root

See `DEPLOYMENT-CHECKLIST-BRAND-4.0.0.md` before extracting over a site.

