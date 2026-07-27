# Deployment checklist — do not skip backup

## 1. Back up the local environment

From:

`C:\Users\mohammad.imlaihat\Documents\UniServerZ\www\drupal-10.6.9`

Back up at minimum:

- `modules/custom`
- `themes/custom/digital_platform`
- `sites/default/settings.php`
- the Drupal database
- generated `/cards` and `/c` folders

Stop Apache before copying generated/static files to avoid locked files.

## 2. Extract the release

Extract the package into the Drupal root so `modules/custom` and
`themes/custom` merge with the existing folders. Do not delete unrelated
custom modules or generated card folders.

The approved corporate logo, product logo, favicon, and mobile icon are already
included. Optional 1200 × 630 Open Graph images can be added later as
`og-ropleon.jpg` and `og-ropleon-cards.jpg`.

## 3. Apply database updates and enable the corporate module

PowerShell:

```powershell
$php = 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\core\php83\php.exe'
& $php .\vendor\drush\drush\drush.php updb -y
& $php .\vendor\drush\drush\drush.php en ropleon_brand -y
& $php .\vendor\drush\drush\drush.php cr
```

If Windows Drush again reports that `sh` is unavailable, run database updates
through `/update.php` while logged in as the maintenance administrator, then
run only the `en ropleon_brand -y` and `cr` commands above.

If `cr` stops because a generated CSS/JS file is locked or unwritable, rebuild
the Drupal container and router without deleting generated assets:

```powershell
$code = '$kernel=\Drupal::service(''kernel''); $kernel->invalidateContainer(); $kernel->rebuildContainer(); \Drupal::service(''router.builder'')->rebuild();'
& $php .\vendor\drush\drush\drush.php php:eval $code
```

Then clear generated CSS/JS from the Drupal performance page while Apache has
normal write access to `sites/default/files`.

Enabling the module changes only the site name, slogan, and front-page target.
It stores their former values for uninstall rollback.

The update migrates only known legacy organization links (`/my/cards`,
`/my-cards`, and `/my/subscription`) to their current working portal routes.

## 4. Verify

- `/`, `/en`, and `/ar` show Ropleon Technologies.
- `/products/cards`, `/en/products/cards`, and `/ar/products/cards` load.
- `/about`, `/contact`, and `/user/login` use the public/product identity.
- `/platform/dashboard`, `/organization/dashboard`, and `/merchant/offers`
  remain authenticated and retain their own navigation.
- One approved test card still loads immediately at `/c/{nfc_id}`.
- QR/NFC, approval transitions, static regeneration, social links, offers,
  loyalty, and redemption continue to work.
- Send test organization-admin and merchant welcome emails.
- Confirm the real production contact email in `/admin/config/system/site-information`.
- At a mobile viewport, open and close the Platform Admin and Organization
  Portal menus, follow each visible destination, and press Escape to close.
- Run the included navigation audit:

```powershell
& $php .\vendor\drush\drush\drush.php php:script `
  C:\path\to\release\scripts\audit-navigation.php
```

## 5. Regenerate static cards when ready

Existing files remain valid. Regenerate approved cards only when you want the
new “Powered by Ropleon Cards” footer applied to every static card.

## Rollback

1. Put the site into maintenance mode.
2. Uninstall `ropleon_brand` while its front-page target is still `/ropleon`;
   this restores the saved site name, slogan, and former front page.
3. Restore the backed-up custom theme/modules and database.
4. Restore `/cards` and `/c` if static cards were regenerated.
5. Rebuild caches and test one public NFC URL before reopening the site.
