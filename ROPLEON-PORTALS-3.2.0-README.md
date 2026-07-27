# Ropleon portal shell 3.2.0

This update unifies language selection and responsive navigation across the
Platform, Organization, and Merchant portals.

## Language behavior

- All portals use the same compact switcher and visual styling as the public
  Ropleon website.
- The switcher preserves the current page and query string.
- URLs are built from Drupal's configured language prefixes, preventing the
  previous Arabic-to-English link from pointing back to the Arabic page.
- The obsolete placed language block is removed from portal rendering.
- The active language is exposed accessibly with `lang`, `dir`, `hreflang`,
  and `aria-current`.

## Navigation behavior

- Platform links are fully visible in a wrapped operational row on desktop.
- Organization navigation remains a single row where space permits and moves
  to a fully visible second row at intermediate widths.
- Merchant navigation remains compact and aligned with Ropleon Cards.
- Below 900 px, every portal uses an accessible disclosure menu while the
  language switcher remains visible beside the brand.
- Logo widths and long organization names are constrained so they cannot cover
  navigation or language controls.

## Apply

Extract the release into the Drupal root, then run:

```powershell
$php = 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\core\php83\php.exe'
& $php .\vendor\drush\drush\drush.php php:script .\scripts\apply-ropleon-portals-v3.2.0.php
```

No manual menu or language-block removal is required.

