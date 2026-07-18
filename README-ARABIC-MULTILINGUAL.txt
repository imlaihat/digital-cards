DIGITAL CARD PLATFORM - ARABIC AND MULTILINGUAL RELEASE
========================================================

WHAT THIS RELEASE ADDS
----------------------
- Arabic language bootstrap and /ar + /en URL prefixes for Drupal pages.
- Editable Arabic interface translations imported as customized translations.
- Shared RTL styling for Platform Admin, Organization Portal, Merchant Portal,
  Views, tables, forms, dropdowns, dashboards, and mobile layouts.
- Locally hosted Noto Sans Arabic variable font and its OFL license; no runtime
  dependency on Google Fonts or another third-party service.
- Arabic-aware organization invitations and password reset messages.
- Preferred-language selection when creating Organization Admin or Merchant users.
- Content Translation enabled for cards, plans, subscriptions, organizations,
  departments, and Social Link paragraphs when those bundles exist.
- Organization default static-card language: English, Arabic, or bilingual.
- Optional card-level language override.
- Arabic static-card labels, direction, translated content, offers, loyalty
  balance, prize progress, social links, QR labels, and vCard content.
- Indexed offer translation storage. Arabic offer text does not join or modify
  redemption counters, limits, locks, or NFC loyalty wallets.
- Stable NFC URL remains /c/{nfc_id}/ with no language prefix or redirect.

BACKUP FIRST
------------
Back up both the database and these folders before extraction:
  modules/custom
  themes/custom/digital_platform

INSTALLATION ORDER
------------------
1. Extract this package into the Drupal root. It contains:
     modules/custom/digital_card_i18n
     updated Digital Card modules
     themes/custom/digital_platform

2. Visit /update.php and run pending database updates. Important hooks:
     digital_card_delivery_update_10002
     digital_card_offers_update_10005

3. Enable "Digital Card Arabic & Multilingual Support" at /admin/modules.
   Its dependencies are the four core multilingual modules already enabled.

4. Clear all caches.

5. Visit /admin/config/regional/language and confirm English and Arabic.
   Drupal application pages use /en/... and /ar/.... NFC /c/{id}/ is unchanged.

6. Place Drupal core's "Language switcher" block in the Header or Primary Menu
   region at /admin/structure/block. Do this manually so the module never
   creates theme-specific block configuration or repeats the old missing-theme
   problem.

7. Grant translation permissions only as needed:
   System Admin:
     administer languages
     translate interface
     translate configuration
     translate any entity
   Platform Admin:
     translate interface (optional)
     translate configuration (optional)
     translate any entity, or bundle-specific translation access
   Organization Admin and Merchant should not receive interface/configuration
   translation administration.

EDITING ARABIC
--------------
Interface text:
  /admin/config/regional/translate

Views, menu labels, and configuration:
  /admin/config/regional/config-translation

Cards, plans, subscriptions, organizations, and taxonomy:
  Use the entity's Translate tab after assigning the appropriate permission.

Offers:
  Open /platform/offers and edit an offer. The "Arabic translation" section
  stores title, description/terms, and discount/prize text. Empty Arabic text
  safely falls back to English.

STATIC CARD LANGUAGE
--------------------
Organization default:
  /platform/organization-card-themes
  Edit the organization and choose English, Arabic, or Arabic and English.

Card override:
  Edit the Digital Business Card and choose "Static card language override".
  Leave it empty to inherit the organization setting.

For Arabic content, add the card's Arabic translation using the Translate tab.
When no Arabic value exists, generation falls back safely to English.

After installation/configuration regenerate existing approved cards:
  & $php .\vendor\drush\drush\drush.php php:script modules/custom/digital_card_delivery/scripts/regenerate-static-cards.php

TRANSLATION MAINTENANCE
-----------------------
The initial PO file is located at:
  modules/custom/digital_card_i18n/translations/digital_card_platform.ar.po

It is imported as customized translation data, so administrator edits in the
Drupal UI remain editable. Export translations before replacing production
translations during a future deployment.

ROLLBACK
--------
1. Restore the database backup.
2. Restore the backed-up custom modules and digital_platform theme.
3. Clear caches.
Do not only replace files after running update hooks; the database backup is
required for a complete rollback.

VALIDATION
----------
The package is syntax-checked with PHP 8.3. Twig, YAML, and PO files are parsed
before packaging. Complete role/access, mail delivery, translated content, and
browser RTL testing must be performed on the backup environment before release.
