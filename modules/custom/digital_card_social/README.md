# Digital Card Social Platforms

This module replaces free-text social platform entry with an administrator-managed registry while preserving the existing `field_platform` string storage and Paragraph content.

## Platform Admin

Visit `/platform/social-platforms` to add, edit, order, enable, or safely delete a platform. Each definition includes English and Arabic labels, an approved built-in icon, trusted domains, aliases for legacy data, an example URL, and status. The add/edit form shows every supported icon with its stable icon name, so administrators never need to guess a key.

## Card editors

- Platform is a controlled bilingual select, not free text.
- The editor shows a permanently visible **Remove this social link** button on every Social Link row.
- The platform help text is translated through Drupal Locale and the Arabic field configuration override.
- The platform icon and display label are selected automatically by the generated static card.

## Safety and compatibility

- Recognized legacy values are normalized in the current and revision field tables.
- Update `10004` removes invalid and empty legacy Social Link Paragraphs after storing a recovery snapshot in Drupal State API.
- New links must use a supported platform and HTTPS URL on an allowed domain.
- Static cards load no external icon library or social network asset.
- Platform deletion is blocked while content or revisions still reference it.

Run the cleanup again when required:

```powershell
& $php .\vendor\drush\drush\drush.php php:script modules/custom/digital_card_social/scripts/cleanup-invalid-social-links.php
```
