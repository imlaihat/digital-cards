# Ropleon content administration

## What can be changed without code today

Platform administrators can update the shared company and product identity at:

`/admin/config/system/ropleon-branding`

This includes the company name, legal name, Arabic name, company tagline,
Ropleon Cards product name, product tagline, product description, branding
line, and public contact email.

Arabic values remain editable through Drupal's Configuration Translation UI.
Interface labels and messages remain editable through:

`/admin/config/regional/translate`

Organization-specific portal and card colors, logos, and optional CSS remain
editable through the Organization Card Themes page.

## Recommended no-code page editing

The safest next content-management step is to model public page sections as
Drupal content instead of putting marketing copy directly in Twig or PHP.
Recommended structure:

1. Create a translatable `Ropleon Landing Page` content type.
2. Add reusable Paragraph types for hero, value statement, capability cards,
   product feature grid, call to action, statistics, and FAQ.
3. Enable Content Translation for the landing page and Paragraph bundles.
4. Use Layout Builder for ordering approved section components.
5. Keep navigation, portal shells, accessibility behavior, and brand tokens in
   code so editors cannot accidentally break application navigation.
6. Use revisions and moderation so public changes can be reviewed before
   publishing.

This gives editors full English and Arabic copy control while preserving the
tested Ropleon visual system and all existing portal routes.

