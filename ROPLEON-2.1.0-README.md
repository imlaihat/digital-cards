# Ropleon 2.1.0

This release completes the Ropleon corporate/public identity pass and the
Arabic interface QA corrections for the existing Drupal platform.

## Install

1. Back up the database and `modules/custom` and `themes/custom` folders.
2. Extract this package in the Drupal root, preserving its folder structure.
3. From the Drupal root in PowerShell, run:

   ```powershell
   & $php .\vendor\drush\drush\drush.php php:script .\scripts\apply-ropleon-v2.1.0.php
   ```

4. Hard-refresh the browser once (`Ctrl+F5`) so the old favicon is discarded.

The script is provided because this local Windows environment previously
reported `sh is not recognized` when running `drush updb`.

## What changed

- Uses the supplied Ropleon and Ropleon Cards SVG assets.
- Removes the temporary white `R` hero tile.
- Removes Drupal's competing core favicon declaration.
- Adds a compact public language switcher and imports editable Arabic catalogs.
- Translates late-rendered menus, table labels, states, form options, messages,
  accessibility labels, and management-toolbar labels.
- Normalizes Arabic date fields to numeric, locale-neutral formats.
- Stops rendering the login block on every public page.
- Removes the global dashboard-card wrapper from system blocks.
- Reduces excessive vertical spacing while keeping responsive layouts.

## Translation maintenance

Imported translations use Drupal's customized/editable Locale status. Platform
administrators can change them at:

`/admin/config/regional/translate`

Content names entered by users (organization, plan, merchant, partner, and offer
names) are intentionally not rewritten as interface strings. Translate those
with the entity's **Translate** operation.

## Modified files

- `modules/custom/ropleon_brand/ropleon_brand.info.yml`
- `modules/custom/ropleon_brand/ropleon_brand.install`
- `modules/custom/ropleon_brand/templates/ropleon-corporate-home.html.twig`
- `modules/custom/ropleon_brand/translations/ropleon_brand.ar.po`
- `modules/custom/digital_card_i18n/digital_card_i18n.install`
- `modules/custom/digital_card_i18n/digital_card_i18n.module`
- `modules/custom/digital_card_i18n/js/platform-arabic-v3.js`
- `modules/custom/digital_card_i18n/translations/digital_card_runtime_qa.ar.po`
- `modules/custom/digital_card_admin/src/Service/DashboardDataBuilder.php`
- `themes/custom/digital_platform/digital_platform.theme`
- `themes/custom/digital_platform/css/dashboard.css`
- `themes/custom/digital_platform/css/ropleon-public.css`
- `themes/custom/digital_platform/templates/block/block.html.twig`
- `themes/custom/digital_platform/templates/page.html.twig`
- `themes/custom/digital_platform/templates/page/page--ropleon-public.html.twig`

## Manual cleanup

No menu or block deletion is required. The update disables only the globally
placed core login block. The dedicated `/user/login` route remains available.

