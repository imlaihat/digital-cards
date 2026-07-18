Digital Card Delivery - Final enhanced module

Install path:
  modules/custom/digital_card_delivery

What this version does:
  - Generates NFC ID automatically when field_nfc_id is empty.
  - Assigns organization from the Group route context when field_organization is empty.
  - Generates QR image into public://qr_codes when field_qr_code is empty.
  - Generates static files under /cards/{field_nfc_id}/index.html and contact.vcf after approval.
  - Copies module CSS to /assets/cards/card.css whenever a static card is generated.
  - Deletes/pauses static files when a card is not approved or when subscription checks fail.
  - Runs cron maintenance:
      1. expires old organization_subscription records through digital_card_subscription.manager
      2. deletes static files for approved cards when organization subscription is expired/invalid
      3. regenerates approved static cards when organization subscription is active/valid
  - Runs the same maintenance immediately when an organization_subscription node is updated.
  - Logs every success/failure under /admin/reports/dblog channel: digital_card_delivery
  - Shows clear user notifications during interactive saves/updates.

Required field names currently expected/supported:
  Digital Business Card:
    - field_nfc_id
    - field_status
    - field_organization
    - field_full_name
    - field_job_title
    - field_email
    - field_mobile
    - field_department
    - field_profile_image
    - field_qr_code
    - field_social_links

  Organization Group:
    - field_logo

After extracting:
  drush cr
  drush cron

Check logs:
  /admin/reports/dblog
  Channels:
    digital_card_delivery
    digital_card_subscription
    digital_card_enforcement
