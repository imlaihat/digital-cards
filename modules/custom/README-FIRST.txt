DIGITAL CARD ACCELERATED RELEASE
================================

Included modules
----------------
1. digital_card_delivery
   - Full static card fields and embedded SVG icons.
   - Organization-scoped static paths and themes.
   - QR/NFC preparation now targets stable /c/{nfc_id} URLs.
   - Static cards load verified offers dynamically for signed-in holders/merchants.

2. digital_card_public
   - Public scanner context API and privacy-conscious analytics.
   - Stable public NFC resolver: /c/{nfc_id}.
   - Resolver returns 404 when a card is unapproved, paused, or its static output was deleted.

3. digital_card_offers
   - Platform Admin Merchant-user listing and creation.
   - Merchant partner administration.
   - Offer administration with organization targeting and limits.
   - Card-holder/merchant eligibility API.
   - Authenticated, CSRF-protected, transaction-safe redemption.
   - Professional, mobile-first Merchant portal and redemption confirmation.
   - Constant-query eligibility calculation (no per-offer count queries).
   - Merchant Portal link in the Drupal user account menu.
   - Redemption audit report.
   - Human-readable redemption report with card/NFC, holder, organization,
     offer benefit, partner, Merchant account, reference, and timestamp.
   - Unified management toolbars and primary Add actions.
   - Reusable instant table search, filtered record count, clear search, and
     genuine OpenXML XLSX export on Platform/Organization management tables
     and Views. Export includes filtered rows, embeds same-origin QR/profile
     images in their worksheet cells, and omits Actions. Image capture supports
     normal/lazy images, linked files, CSS backgrounds, SVG, and WebP-to-PNG.

4. digital_card_admin
   - Adds Merchants, Offers, and Redemptions to the Platform Admin header.

BACK UP FIRST
-------------
Database (from Drupal root; update credentials/path as needed):
  mysqldump -u root -p DATABASE_NAME > digital-card-before-offers.sql

Modules:
  Compress-Archive -Path modules\custom -DestinationPath modules-custom-before-offers.zip

INSTALLATION
------------
1. Extract the bundle into modules/custom so each module directory is directly
   beneath modules/custom.
2. Enable Digital Card Offers:
   & $php .\vendor\drush\drush\drush.php en digital_card_offers -y
3. Run database updates for existing public/delivery installations:
   & $php .\vendor\drush\drush\drush.php updb -y
   If Windows reports that sh is unavailable, use /update.php temporarily and
   immediately restore $settings['update_free_access'] = FALSE afterward.
4. Clear caches:
   & $php .\vendor\drush\drush\drush.php cr

MANUAL ROLE/PERMISSION SETUP
----------------------------
Create role machine name: merchant
Grant only:
  - Check card-holder offer eligibility
  - Redeem card-holder offers

Platform Admin permissions:
  - Create and manage Merchant users
  - Administer digital card merchant partners
  - Administer digital card offers
  - View digital card redemption reports

Do not grant offer administration permissions to Merchant users.

WORKFLOW
--------
1. Open /platform/merchant-users and create a Merchant user. Platform Admin
   sets an initial temporary password; the module assigns the Merchant role
   automatically and can email both the temporary password and a secure
   one-time password link. Platform Admin can later edit, activate/block, or
   reset the password without receiving broad Drupal user-admin permission.
2. Open /platform/merchant-partners and assign that user to a partner.
3. Open /platform/offers and create an offer. Leave organizations unchecked
   for a global offer, or select target organizations.
4. Merchant opens /merchant/offers, scans/enters the NFC ID, and redeems an
   eligible offer. Every success gets a unique audit reference.
5. Platform Admin reviews /platform/offer-redemptions.

MERCHANT PORTAL UPDATE
----------------------
Open directly after login:
  /merchant/offers

Or send Merchants to the login form with a destination:
  /user/login?destination=/merchant/offers

This release replaces module files only and adds no new database schema.
When upgrading from the previous Offers release, run cache rebuild; database
updates are not required specifically for this Merchant Portal update.

NFC URL
-------
Program every new physical NFC tag with:
  https://YOUR-DOMAIN/DRUPAL-BASE-PATH/c/{nfc_id}/

Local example:
  http://localhost/drupal-10.6.9/c/jawwal-1739a6/

This URL must remain stable. Do not program the organization static directory
or the JSON API URL into physical cards.

The trailing slash is intentional: it avoids an extra HTTP redirect. Approved
cards are generated directly under /c/{nfc_id}/ and are served by the web
server without bootstrapping Drupal. The Drupal redirect route is now only a
legacy/fallback path.

After installing this release, generate fast-path copies for all existing
approved cards:
  & $php .\vendor\drush\drush\drush.php php:script modules/custom/digital_card_delivery/scripts/regenerate-static-cards.php

EXISTING QR CODES
-----------------
Existing QR images are not silently replaced. For an old card, remove its
field_qr_code image and save it through the web UI to generate a QR containing
the new stable /c/{nfc_id} URL. Then regenerate/save the approved card.

SECURITY/PERFORMANCE
--------------------
- Public endpoints expose no holder PII.
- Eligibility is verified on every request.
- Redemption requires Merchant permission, session authentication, CSRF token,
  rate limiting, partner ownership, offer validity, organization targeting,
  and per-holder/total limits.
- Offer rows are locked during redemption to protect limits under concurrency.
- Static card remains usable if the offer API is unavailable.
- Scan logging remains privacy-conscious and separate from redemptions.
- Offer limits are enforced independently at NFC holder, organization, and
  global levels. The normalized NFC ID is the authoritative holder identity,
  so different cards never share a holder allowance merely because the same
  organization administrator created them.

LOYALTY POINTS AND PRIZES
-------------------------
This release adds three offer behaviors without changing existing offers:
  Standard redemption  - current discount/benefit behavior.
  Earn loyalty points  - successful redemption credits the configured points.
  Points prize         - requires and deducts the configured points for a prize
                         such as a free item.

Points are stored in one wallet per Partner + normalized NFC ID. They are not
shared between cards or between partners. Every balance change is committed in
the same database transaction as the redemption and written to an immutable
ledger. Wallet and offer rows are locked during redemption to prevent double
spending and limit bypass under concurrency.

Recommended setup:
1. Create/edit an offer under /platform/offers.
2. For a purchase/visit offer, choose "Earn loyalty points" and enter the
   points awarded per successful redemption.
3. Create a second offer for the same partner, choose "Points prize", enter
   the required points, and describe the free product/prize.
4. Configure the existing NFC, organization, and global redemption limits as
   usual. These limits remain independent from the points wallet.
5. The Merchant verifies the NFC at /merchant/offers. The confirmation and
   success messages show points earned/spent, the new balance, and progress to
   the nearest prize.
6. A logged-in card owner sees the same wallet balance and prize progress on
   the generated card page. Regenerate existing approved static cards after
   installing this release using the command in the NFC URL section above.

DATABASE UPDATE
---------------
Back up the database and modules first, extract the release, then visit:
  /update.php

Run pending database updates. digital_card_offers_update_10004 adds the offer
behavior fields, redemption point audit fields, the indexed wallet table, and
the points ledger. Then clear caches and regenerate existing approved cards.

VALIDATION STATUS
-----------------
All included PHP files passed PHP 8.3 syntax checking and all YAML files parsed
successfully. Full Drupal browser/integration testing must be completed on a
backup copy of the site before production use.
