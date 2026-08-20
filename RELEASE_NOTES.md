# Ropleon Portal User & Mobile Update 4.2.0

## Included fixes

- Mobile Platform and Organization Portal menu button remains visible while Drupal behaviors initialize, and works with touch, keyboard, RTL, and viewport changes.
- All authenticated portal tables, including Group members/content operations, receive a horizontally scrollable mobile container.
- Group forms, local task tabs, exposed filters, action buttons, and entity metadata adapt to narrow screens.
- Organization administrator first and last names are backed by real Drupal user fields and persist on create/edit.
- The Organization Administrators View receives a **Full name** column, with the username as a safe fallback for legacy accounts.
- Editing an administrator no longer deletes and recreates an unchanged Group membership, preserving Group roles and relationship data.
- Successful login redirects Platform, Organization, and Merchant users to their role dashboard in their selected preferred language.
- Organization administrator welcome/password emails include the login link in the account's preferred language.
- New interface text is included in the editable Arabic translation catalog.

## Installation

1. Back up the database and these existing folders:
   - `modules/custom/digital_card_admin`
   - `modules/custom/digital_card_i18n`
   - `themes/custom/digital_platform`
2. Extract this ZIP into the Drupal root, preserving folders and allowing files to be overwritten.
3. Run database updates and clear cache. On Windows, if `drush updb` reports that `sh` is missing, run the included installer instead:

   `& $php .\vendor\drush\drush\drush.php php:script .\scripts\apply_ropleon_user_mobile_update.php`

4. If normal Drush works, use:

   `& $php .\vendor\drush\drush\drush.php updb -y`

   `& $php .\vendor\drush\drush\drush.php cr`

## Important legacy-data note

Older releases displayed First Name and Last Name inputs without defining storage fields, so values entered previously could not be persisted and cannot be reconstructed safely. Existing users show their username in the Full name column until a Platform Administrator edits the account once and supplies the real name.

## Acceptance checks

- At a mobile viewport, open Platform and Organization Portal pages and confirm the **Menu** button is visible and opens/closes the links.
- Create an Organization Administrator with first name, last name, organization, and Arabic preferred language; reopen Edit and confirm every value remains.
- Sign in as that user from `/en/user/login`; confirm the destination becomes `/ar/organization/dashboard`.
- Open an Organization Group's Members and Content pages on mobile; confirm tabs scroll, forms fit, and tables scroll horizontally without widening the page.
- Confirm the Organization Administrators list displays **Full name**.

