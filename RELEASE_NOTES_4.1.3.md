# Ropleon Cards 4.1.3 — Organization Language Policy

This update separates the organization language policy from the original
language of each Digital Business Card.

## Behavior

- `Default static card language = English`: new cards are English-only.
- `Default static card language = Arabic`: new cards are Arabic-only.
- `Default static card language = Arabic and English`: the create form offers
  English or Arabic as the original language. After saving, editors can create
  only the opposite language through Drupal's Translate tab.
- The original language is locked after the first save.
- Static `/c/{nfc_id}/` output opens in the original language.
- The language selector and second language directory are published only when
  a real translation exists.
- Removing a translation immediately regenerates the static card and removes
  its obsolete language directory.
- The database update normalizes legacy card language values in batches and
  aligns safe, one-language source records with their chosen original language.

## Installation

Extract the package into the Drupal root so the module folders merge under
`modules/custom/`. Back up the database and current modules first.

Run database updates and rebuild caches. On this Windows/UniServerZ setup, use
`/update.php` while signed in as an administrator if Drush `updatedb` fails
because `sh` is unavailable. Then clear Drupal caches.

After installation, edit each organization at **Platform Admin > Card Themes**
and save its Default static card language. Regenerate already-approved cards
after changing an organization's policy, or allow the delivery cron to do it.
