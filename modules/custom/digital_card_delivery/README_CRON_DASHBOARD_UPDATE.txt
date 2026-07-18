Digital Card Delivery - Subscription Maintenance Cron
====================================================

Cron entry point:
  digital_card_delivery_cron()

What it does:
  1. Calls digital_card_subscription.manager::expireOldSubscriptions().
  2. For organizations with expired/invalid subscriptions, deletes generated static card folders for approved cards.
     This pauses public availability without deleting Drupal card content.
  3. For organizations with active subscriptions, re-checks approved cards and regenerates their static files.
  4. Logs every action under digital_card_delivery, digital_card_subscription and digital_card_enforcement.
  5. Shows interactive messages when actions are triggered by an admin save/update.

Manual test:
  drush cr
  drush cron

Logs:
  /admin/reports/dblog
