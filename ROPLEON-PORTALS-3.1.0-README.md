# Ropleon portal shell 3.1.0

This update completes the visual separation between the public corporate site
and the three authenticated business workspaces.

## Corrections

- The public header now renders exactly one `Explore Ropleon Cards` action.
- The supplied SVG favicon and PNG fallback receive a versioned URL so browsers
  refresh the official Ropleon mark.
- Browser titles clearly identify the page, Ropleon company/product, and portal.

## Authenticated workspace architecture

The active business role selects a theme-owned portal shell:

- **Platform Portal** — Ropleon Technologies corporate identity, corporate logo,
  Ropleon blue/cyan details, platform navigation, and corporate footer.
- **Organization Portal** — the organization card-theme palette and logo inside
  a consistent Ropleon Cards application shell and product footer.
- **Merchant Portal** — Ropleon Cards identity, corporate navy header, compact
  verification/account navigation, and product footer.

The shell is selected from permissions rather than a manually placed block.
This means the correct header and footer appear across dashboard, list, form,
entity, verification, and redemption pages that use the `digital_platform`
theme. Existing manually placed portal-header blocks may remain configured;
the portal shell renders one validated header and does not output the old
primary-menu region on authenticated workspace pages.

Organization navigation remains a single line on desktop. Platform navigation
uses one non-wrapping, horizontally scrollable operational line when necessary.
Below 900 px both use the existing accessible disclosure menu. Merchant pages
receive their own keyboard-accessible mobile disclosure.

## Apply

Extract the release into the Drupal root, then run:

```powershell
$php = 'C:\Users\mohammad.imlaihat\Documents\UniServerZ\core\php83\php.exe'
& $php .\vendor\drush\drush\drush.php php:script .\scripts\apply-ropleon-portals-v3.1.0.php
```

No manual menu or block removal is required. After cache rebuild, refresh the
browser once with `Ctrl+F5` so its favicon cache reads the versioned official
asset.

