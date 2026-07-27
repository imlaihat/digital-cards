# Modified and newly created files

This manifest covers the Ropleon Technologies / Ropleon Cards branding release
relative to the staged Drupal baseline commit `dbf08f5`.

## Release documentation and checks

- `CHANGELOG-ROPLEON.md` — release notes.
- `DEPLOYMENT-CHECKLIST.md` — backup, deployment, verification, and rollback.
- `README-ROPLEON-BRANDING.md` — public architecture and editable branding.
- `MODIFIED-FILES.md` — this manifest.
- `scripts/assert-brand-contract.php` — regression guard for public/product/NFC routes.
- `scripts/audit-navigation.php` — live audit for header and Drupal menu destinations.
- `scripts/validate-release.php` — YAML, Twig, and translation-catalog validation.

## New `ropleon_brand` module

- `modules/custom/ropleon_brand/ropleon_brand.info.yml`
- `modules/custom/ropleon_brand/ropleon_brand.routing.yml`
- `modules/custom/ropleon_brand/ropleon_brand.services.yml`
- `modules/custom/ropleon_brand/ropleon_brand.links.menu.yml`
- `modules/custom/ropleon_brand/ropleon_brand.module`
- `modules/custom/ropleon_brand/ropleon_brand.install`
- `modules/custom/ropleon_brand/ropleon_brand.config_translation.yml`
- `modules/custom/ropleon_brand/config/install/ropleon_brand.settings.yml`
- `modules/custom/ropleon_brand/config/schema/ropleon_brand.schema.yml`
- `modules/custom/ropleon_brand/src/Controller/CorporateController.php`
- `modules/custom/ropleon_brand/src/Form/BrandSettingsForm.php`
- `modules/custom/ropleon_brand/templates/ropleon-corporate-home.html.twig`
- `modules/custom/ropleon_brand/templates/ropleon-cards-landing.html.twig`
- `modules/custom/ropleon_brand/templates/ropleon-about.html.twig`
- `modules/custom/ropleon_brand/translations/ropleon_brand.ar.po`

## `digital_platform` theme

- `themes/custom/digital_platform/digital_platform.info.yml`
- `themes/custom/digital_platform/digital_platform.libraries.yml`
- `themes/custom/digital_platform/digital_platform.theme`
- `themes/custom/digital_platform/assets/brand/README.md`
- `themes/custom/digital_platform/assets/brand/ropleon.svg`
- `themes/custom/digital_platform/assets/brand/ropleon-cards.svg`
- `themes/custom/digital_platform/assets/brand/favicon.svg`
- `themes/custom/digital_platform/assets/brand/apple-touch-icon.png`
- `themes/custom/digital_platform/css/brand-tokens.css`
- `themes/custom/digital_platform/css/ropleon-public.css`
- `themes/custom/digital_platform/css/login.css`
- `themes/custom/digital_platform/js/ropleon-public.js`
- `themes/custom/digital_platform/templates/page/page--ropleon-public.html.twig`
- `themes/custom/digital_platform/templates/page/page--user--login.html.twig`
- `themes/custom/digital_platform/templates/page/page--login-gateway.html.twig`
- Removed: `themes/custom/digital_platform/config/optional/block.block.digital_platformpowered.yml`

## Existing product modules

### `digital_card_admin`

- `modules/custom/digital_card_admin/digital_card_admin.info.yml`
- `modules/custom/digital_card_admin/digital_card_admin.install`
- `modules/custom/digital_card_admin/digital_card_admin.libraries.yml`
- `modules/custom/digital_card_admin/digital_card_admin.module`
- `modules/custom/digital_card_admin/css/dashboards.css`
- `modules/custom/digital_card_admin/js/portal-navigation.js`
- `modules/custom/digital_card_admin/src/Controller/DashboardLinkController.php`
- `modules/custom/digital_card_admin/src/Plugin/Block/OrganizationPortalHeaderBlock.php`
- `modules/custom/digital_card_admin/src/Plugin/Block/PlatformAdminHeaderBlock.php`
- `modules/custom/digital_card_admin/src/Service/OrganizationAdminMailer.php`
- `modules/custom/digital_card_admin/templates/digital-card-organization-portal-header.html.twig`
- `modules/custom/digital_card_admin/templates/digital-card-platform-admin-header.html.twig`
- `modules/custom/digital_card_admin/templates/digital-card-platform-dashboard.html.twig`
- `modules/custom/digital_card_admin/templates/organization-admin-created-email.html.twig`

### Delivery and public access

- `modules/custom/digital_card_delivery/digital_card_delivery.info.yml`
- `modules/custom/digital_card_delivery/src/Service/CardStaticGenerator.php`
- `modules/custom/digital_card_public/digital_card_public.info.yml`
- `modules/custom/digital_card_enforcement/digital_card_enforcement.info.yml`
- `modules/custom/digital_card_subscription/digital_card_subscription.info.yml`

### Internationalization, offers, and social links

- `modules/custom/digital_card_i18n/digital_card_i18n.info.yml`
- `modules/custom/digital_card_i18n/digital_card_i18n.module`
- `modules/custom/digital_card_i18n/js/platform-arabic.js`
- `modules/custom/digital_card_i18n/js/platform-arabic-v2.js`
- `modules/custom/digital_card_i18n/js/platform-arabic-v3.js`
- `modules/custom/digital_card_offers/digital_card_offers.info.yml`
- `modules/custom/digital_card_offers/digital_card_offers.module`
- `modules/custom/digital_card_social/digital_card_social.info.yml`
