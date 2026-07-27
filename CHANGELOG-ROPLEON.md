# Change log

## 2.0.3

- Changed the thin public brand controller to a parameterless Drupal class
  controller for reliable resolution under the local Uniform Server web SAPI.
- Documented the targeted container rebuild used when generated CSS/JS files
  are locked or unwritable during a full Windows cache rebuild.
- Confirmed English and Arabic corporate/product pages, all portal navigation
  routes, `/c/{nfc_id}`, and the scanner-context API after live deployment.

## 2.0.1

- Stabilized the initial corporate-controller implementation and routing.
- Added the supplied official Ropleon corporate/product SVGs, favicon, and
  mobile icon to all intended brand surfaces.
- Added accessible collapsible mobile navigation for Platform Admin and
  Organization Portal headers.
- Replaced platform-header text/emoji marks with the official product logo and
  consistent Bootstrap icons.
- Changed platform-header destinations to route-name based URLs and added a
  live navigation audit for header, custom-module, and manual menu links.
- Added an update hook that migrates known broken legacy organization menu
  destinations to the current portal routes.

## 2.0.0

- Added Ropleon Technologies corporate homepage and navigation.
- Added the `/products/cards` Ropleon Cards landing page.
- Added About and Contact integration.
- Added configurable, translatable corporate/product brand settings.
- Added shared design tokens, responsive navigation, RTL, accessibility, and
  SEO metadata.
- Rebranded login, platform header/dashboard, custom-module labels, and custom
  email messages as Ropleon Cards.
- Added “Powered by Ropleon Cards” to newly generated static cards.
- Preserved all internal module names, dashboard routes, permissions, scanner
  API paths, static organization directories, and `/c/{nfc_id}`.
