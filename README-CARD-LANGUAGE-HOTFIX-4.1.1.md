# Ropleon Cards — Language and Translation Hotfix 4.1.1

This hotfix resolves the Arabic-save exception introduced in 4.1.0 and makes
the static-card language behavior follow Drupal content translations.

## Corrections

- Fixes `CardStaticGenerator::esc(): null given` when a bilingual card with a
  verified badge is saved.
- Preserves bilingual label keys and keeps HTML escaping null-safe.
- Hides the public language selector for a one-language card.
- Shows the selector only when the card is configured as **Arabic and
  English** and real Drupal content translations exist in both languages.
- Generates only translated language subdirectories and removes stale copies.
- Generates language-specific `contact.vcf` files.
- Keeps the root `/c/{nfc_id}/` page in the card's original content language,
  regardless of which translation was edited most recently.
- Enables card translation fields for name, job title, department, and social
  links while contact identifiers and workflow data remain shared.
- Grants card translation permissions to Platform Admin, Organization Admin,
  Content Editor, and Administrator roles.
- Adds a translated form explanation and a **Translate** action to the Platform
  card Actions menu.

## Install

1. Back up the database and the affected custom modules.
2. Extract the combined package at the Drupal root.
3. Run `/update.php` to apply update `digital_card_i18n_update_10021`.
4. Rebuild caches.
5. Save or regenerate approved cards.

## Creating a bilingual card

1. Create and save the original card in English or Arabic.
2. Open the card and select the **Translate** tab.
3. Add the missing Arabic or English translation and enter its translated
   full name, job title, department, and social links.
4. Set **Static card language override** to **Arabic and English**, or use an
   organization theme whose default is Arabic and English.
5. Save. The public selector appears only after both translations exist.

Single-language cards continue to work at `https://ropleon.com/c/{nfc_id}/`
without displaying an unnecessary language selector.

