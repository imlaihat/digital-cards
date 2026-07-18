Digital Card Subscription - Updated module

Install path:
  modules/custom/digital_card_subscription

Important:
  - This version uses hook_cron() to expire organization_subscription nodes.
  - It sets the subscription status field to the stored value for Expired when field_end_date is before today.
  - It auto-detects common field machine names:
      organization: field_organization, field_organization_subscribed
      plan: field_subscription_plan, field_plan
      status: field_sub_status, field_subscription_status
      end date: field_end_date, field_subscription_end_date
      max cards on plan: field_max_cards, field_maximum_cards

After extracting:
  drush cr
  drush cron

Check logs:
  /admin/reports/dblog
  Channel: digital_card_subscription

Additional integration note:
  When digital_card_delivery is enabled, its cron also calls this manager and then pauses/regenerates static approved card files based on the current subscription state.
