# Deployment and rollback checklist

Target environment used for the commands below:

`C:\Users\mohammad.imlaihat\Documents\UniServerZ\www\drupal-10.6.9`

The release is an overlay. It does not delete modules or content.

## 1. Back up before extraction

From PowerShell:

```powershell
Set-Location 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\www\drupal-10.6.9'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "C:\Users\mohammad.imlaihat\Documents\UniServerZ\backups\ropleon-brand-$stamp"
New-Item -ItemType Directory -Path $backup -Force | Out-Null

Compress-Archive -Path '.\themes\custom\digital_platform' -DestinationPath "$backup\digital_platform-before.zip" -Force
Compress-Archive -Path '.\modules\custom\ropleon_brand' -DestinationPath "$backup\ropleon_brand-before.zip" -Force
Compress-Archive -Path '.\modules\custom\digital_card_admin' -DestinationPath "$backup\digital_card_admin-before.zip" -Force
Compress-Archive -Path '.\modules\custom\digital_card_delivery' -DestinationPath "$backup\digital_card_delivery-before.zip" -Force
```

Export the database using phpMyAdmin, or use Drush if its Windows shell setup is
working:

```powershell
& $php .\vendor\drush\drush\drush.php sql:dump --result-file="$backup\database-before.sql"
```

Also back up `sites/default/files` if it is not already protected by your normal
backup process.

## 2. Deploy the overlay

1. Enable maintenance mode.
2. Extract the ZIP into the Drupal root, preserving its folder structure.
3. Allow files with the same names to be overwritten.
4. Do not delete any custom module folder first.

Run database updates and rebuild caches:

```powershell
& $php .\vendor\drush\drush\drush.php updb -y
& $php .\vendor\drush\drush\drush.php cr
```

If Drush still reports that `sh` is missing on Windows, run the update through
`/update.php` as an authorized administrator, or run Drush from Git Bash after
backing up. Do not skip the database update: it applies the new configurable
tagline default safely.

## 3. Validate

```powershell
& $php .\scripts\validate-brand-release.php
& $php .\scripts\assert-brand-contract.php
```

Check both languages and anonymous/authenticated states:

- `/en` and `/ar`
- `/en/products/cards` and `/ar/products/cards`
- `/en/user/login` and `/ar/user/login`
- Platform, Organization, and Merchant portal headers on desktop and mobile
- One existing public card at `/c/{nfc_id}`
- QR/NFC entry, social links, scanner context, offers, and redemption
- Browser favicon, Apple icon, page title, and social metadata

Disable maintenance mode only after these checks pass.

## 4. Manual actions

- No old logo file or menu needs to be removed manually.
- Existing organization themes remain assigned; the sample presets are not
  activated automatically.
- If you want self-hosted Inter, add approved `.woff2` files as described in
  `modules/custom/ropleon_brand/resources/fonts/README_FONT_INSTALLATION.md`.
  Noto Sans Arabic remains available from the existing local font assets.
- Review editable text at `/admin/config/system/ropleon-brand` after deployment.

## 5. Roll back

1. Enable maintenance mode.
2. Restore the four module/theme ZIP backups over their original paths.
3. Restore the database dump using phpMyAdmin or your normal MySQL restore
   procedure.
4. Rebuild caches.
5. Verify `/en`, `/ar`, login, portals, and one `/c/{nfc_id}` card.
6. Disable maintenance mode.

