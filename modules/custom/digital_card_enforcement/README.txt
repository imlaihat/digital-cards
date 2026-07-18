Digital Card Enforcement - Updated module

Install path:
  modules/custom/digital_card_enforcement

Important:
  - The Service folder is capitalized correctly: src/Service
  - Approval checks are enforced in hook_entity_presave().
  - If subscription/card-limit checks fail, the card is not approved and static generation is blocked.
  - User-facing messages and watchdog log entries are created for every check result.

After extracting:
  drush cr

Check logs:
  /admin/reports/dblog
  Channel: digital_card_enforcement
