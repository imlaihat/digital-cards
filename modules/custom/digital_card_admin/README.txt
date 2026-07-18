Digital Card Admin - Final Dashboard Module
==========================================

Install path:
  modules/custom/digital_card_admin

Main routes:
  /platform/dashboard
  /organization/dashboard

Menus added:
  Platform Admin > Dashboard
  Organization Portal > Dashboard

Permissions to grant:
  access platform admin dashboard      -> Platform Admin role
  access organization portal dashboard -> Organization Admin role
  manage digital card workflow         -> Platform Admin role
  create organization administrators   -> Platform Admin role

Dashboard behavior:
  - Platform dashboard summarizes organizations, subscriptions, approvals, capacity and recent cards.
  - Organization dashboard summarizes the current organization admin's organization, subscription, plan consumption and recent cards.
  - All dashboard loads and failure conditions are logged under digital_card_admin.
  - Clear UI warnings are shown when dashboard data cannot be loaded or when subscription/quota alerts exist.

After extracting:
  drush cr
  drush cron

Cron behavior is implemented in digital_card_delivery:
  - Expire old organization_subscription records through digital_card_subscription.
  - Pause/delete public static card output when organization subscription is invalid/expired.
  - Regenerate static card output for approved cards when the subscription is active again.
