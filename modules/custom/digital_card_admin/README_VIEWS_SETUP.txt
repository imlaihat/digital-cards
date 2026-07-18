Digital Card Admin - Views Setup
================================

This module package includes bundled Views configuration for:

1. Platform Plans
   View machine name: platform_plans
   Path: /platform-plans

2. Platform Subscriptions
   View machine name: platform_subscriptions
   Path: /platform-subscriptions

3. Organization Subscription Details
   View machine name: organization_subscription_details
   Path: /organization/subscription-details

Important:
- /organization/subscription remains a secure custom module page because it filters by the current user's organization membership.
- Keep the Organization Portal header Subscription link pointing to /organization/subscription.
- The /organization/subscription-details View is provided as a styled detail/history View template and should not be used as the only security boundary unless you add the correct Group/current-user relationship filters in Views UI.

After extracting the module, run:

  drush updb -y
  drush cr

The update hook digital_card_admin_update_10002 imports/updates the bundled Views.

If you prefer to manage those Views manually, you can still edit them from:

  /admin/structure/views

Recommended field names used by the bundled Views:
- subscription_plan
  - field_max_cards
- organization_subscription
  - field_organization_subscribed
  - field_plan
  - field_sub_status
  - field_end_date

If your machine names differ, edit the View fields from the Drupal UI after import.
