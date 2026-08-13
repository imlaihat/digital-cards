# Ropleon Cards — Professional Card Experience 4.1.0

This release upgrades the public static-card experience without changing card
machine names, workflow state IDs, permissions, or the permanent NFC address.

## Included modules

- `digital_card_delivery`
- `digital_card_offers`
- `digital_card_social`
- `digital_card_i18n`

## What changed

- Keeps the NFC destination fast and permanent: `/c/{nfc_id}/`.
- Keeps organization-scoped source output under `/cards/{organization}/{nfc_id}/`.
- Generates dedicated English and Arabic card pages under `/en/` and `/ar/`.
- Adds an accessible language switcher, visible mobile action labels, native
  sharing, copy fallback, responsive names, larger touch targets, metadata,
  and a clearer visual hierarchy.
- Adds optional organization-name, cover-watermark, and verified-employee
  presentation controls.
- Preserves every customer-facing card field: name, title, department,
  organization, photo, mobile, email, social links, QR code, and vCard.
- Expands vCards with department, website/social profiles, and a reasonably
  sized embedded profile photo.
- Uses one combined session-aware endpoint for scanner context, scan logging,
  offers, and loyalty. Public card rendering remains static and immediate.
- Removes technical role labels from the public card.
- Canonicalizes WhatsApp contacts to `https://wa.me/{number}` and rejects
  invalid profile-name URLs. Existing invalid Paragraphs are backed up in
  State API before the update hook removes them.
- Keeps Arabic messages editable through Drupal's Translation interface.

## Installation

1. Back up the database and these four existing module directories.
2. Extract the release at the Drupal root so files land in
   `modules/custom/{module_name}`.
3. Run database updates. On this Windows installation, use the browser at
   `/update.php` if Drush reports that `sh` is unavailable.
4. Rebuild caches:

   ```powershell
   & $php .\vendor\drush\drush\drush.php cr
   ```

5. Regenerate approved static cards:

   ```powershell
   & $php .\vendor\drush\drush\drush.php php:script modules/custom/digital_card_delivery/scripts/regenerate-static-cards.php
   ```

6. Test the sample address, plus its language variants:

   - `/c/ropleon-technologies-524912/`
   - `/c/ropleon-technologies-524912/en/`
   - `/c/ropleon-technologies-524912/ar/`

## Ropleon organization configuration

1. Edit the Ropleon organization and upload the approved master logo from:
   `themes/custom/digital_platform/assets/brand/corporate/png/Ropleon_Approved_Master.png`.
2. Open `/platform/organization-card-themes` and configure Ropleon.
3. Use the approved primary, secondary, and page-background colors.
4. Disable **Show organization name beside the logo** because the approved
   Ropleon master logo already contains its wordmark.
5. Enable **Show organization logo watermark in the card cover**.
6. Enable **Show verified employee badge** only for verified employee cards.
7. Select the default card language. Every generated card still receives
   direct English and Arabic subpages.
8. Additional CSS remains optional; start without it. Use it only for a
   deliberate organization-specific exception.

## Content correction required

The old sample WhatsApp value
`https://www.whatsapp.com/mohammad.imlaihat` is not a valid click-to-chat URL.
Replace it with an international number such as `+970...` or with the matching
`https://wa.me/...` URL. The update hook removes invalid legacy Paragraphs, so
add the corrected WhatsApp link to the card if needed.

## Rollback

1. Restore the four module directories from the backup.
2. Restore the database backup. Database rollback is required because this
   release adds organization fields and imports translations.
3. Rebuild caches and regenerate static cards from the restored code.

Do not write an organization-scoped `/cards/...` URL to an NFC tag. Production
tags and QR codes must use `https://ropleon.com/c/{nfc_id}/`. The
`/drupal-10.6.9` prefix belongs only to the current local development site.
