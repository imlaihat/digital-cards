# Ropleon Technologies / Ropleon Cards branding release

This release separates the public Ropleon Technologies corporate presence from
the authenticated Ropleon Cards product portals while preserving the existing
Drupal machine names and business workflows.

## Public architecture

- `/` resolves to the Ropleon corporate page after `ropleon_brand` is enabled.
- `/ropleon` is the internal front-page target.
- `/products/cards` is the dedicated Ropleon Cards product page.
- `/about` is the company page.
- `/contact` continues to use Drupal Core Contact.
- `/user/login` uses the Ropleon Cards product identity.
- Platform, organization, and merchant workspaces retain their existing routes,
  permissions, blocks, and role-aware login redirects.
- Public NFC paths remain `/c/{nfc_id}`.

## Editable branding

Platform/system administrators can edit company and product copy at:

`/admin/config/system/ropleon-branding`

The configuration schema is translatable. Language-specific values are
available through Drupal Configuration Translation after the module is enabled.

## Official logo files

The approved `ropleon.svg`, `ropleon-cards.svg`, `favicon.svg`, and
`apple-touch-icon.png` assets are included under:

`themes/custom/digital_platform/assets/brand/`

The corporate header/footer, product landing page, platform header, login
experience, favicon, and mobile icon use the supplied artwork directly.

## Performance and accessibility

- Local CSS and a small dependency-free Drupal behavior power the public pages.
- No remote font, icon, analytics, or image dependency was introduced.
- Public pages have semantic landmarks, keyboard navigation, focus states,
  reduced-motion support, responsive layouts, RTL support, meta descriptions,
  Open Graph data, canonical URLs, and JSON-LD.
- Existing static cards remain organization-scoped and continue to be generated
  into both organization directories and stable `/c/{nfc_id}` directories.
- Platform and organization portal headers use accessible collapsible mobile
  navigation with visible labels and keyboard support.
