# Ropleon public experience 3.0.0 — deployment checklist

This package is staged only. It does not deploy itself.

## 1. Back up

From the local Drupal root
`C:\Users\mohammad.imlaihat\Documents\UniServerZ\www\drupal-10.6.9`:

- export the database;
- copy `modules/custom/ropleon_brand`;
- copy `themes/custom/digital_platform`;
- preserve `sites/default/settings.php`;
- preserve generated `/cards`, `/c`, and public files.

## 2. Extract

Extract the ZIP into the Drupal root and allow it to merge the provided
`modules`, `themes`, and `scripts` directories. Do not remove unrelated custom
modules, public files, or generated cards.

## 3. Apply

Run from the Drupal root:

```powershell
$php = 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\core\php83\php.exe'
& $php .\vendor\drush\drush\drush.php php:script .\scripts\apply-ropleon-public-v3.0.0.php
```

This Windows-safe script avoids the `sh` dependency previously encountered by
`drush updb`.

## 4. Functional verification

- Open `/`, `/en`, and `/ar`.
- Open `/products`, `/solutions`, `/products/cards`, `/about`, `/contact`,
  `/privacy`, and `/terms` in English and Arabic.
- Verify the mobile drawer with pointer, keyboard, Escape, and RTL layouts.
- Submit one contact enquiry and verify the configured recipient receives the
  organization, phone, area of interest, and message.
- Sign in as Platform Admin, Organization Admin, and Merchant; confirm their
  authenticated headers and dashboards are unchanged.
- Open one approved card at `/c/{nfc_id}` and confirm QR/NFC and static card
  behavior remain intact.
- Confirm existing workflows, subscriptions, offers, redemptions, loyalty, and
  organization card themes.

## 5. Quality and cache checks

- Confirm no browser console errors on public pages.
- Confirm the official Ropleon SVG appears in the corporate header/footer and
  the Ropleon Cards SVG appears on product pages.
- Confirm the favicon and Apple touch icon are the supplied brand assets.
- Verify 360 px, 768 px, 1,024 px, and desktop widths.
- Check focus visibility and reduced-motion behavior.
- Verify the public contact recipient at
  `/admin/structure/contact/manage/feedback`.

## 6. Rollback

1. Enable maintenance mode.
2. Restore the database export.
3. Restore the backed-up `ropleon_brand` module and `digital_platform` theme.
4. Restore generated `/cards`, `/c`, and public files only if they changed
   during separate operations.
5. Rebuild Drupal caches.
6. Verify `/`, one dashboard, and one `/c/{nfc_id}` URL before reopening.

