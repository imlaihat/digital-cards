LOYALTY POINTS UPDATE
=====================

Offer behavior options:
- Standard redemption: existing behavior.
- Earn loyalty points: credits points after a successful redemption.
- Points prize: validates and deducts points to grant a prize/free product.

The wallet identity is Partner + normalized NFC ID. Points are therefore not
shared between different cards or merchants. Balance changes and redemptions
use one database transaction and an immutable ledger.

INSTALL/UPDATE
1. Back up the database and modules/custom.
2. Extract this folder over modules/custom/digital_card_offers.
3. Visit /update.php and run digital_card_offers_update_10004.
4. Clear all Drupal caches.
5. Install the matching digital_card_delivery update and regenerate existing
   approved static cards so logged-in holders see their loyalty progress.

CONFIGURATION
1. Create an earning offer at /platform/offers for a Merchant Partner.
2. Select "Earn loyalty points" and enter points awarded.
3. Create the prize offer for the same Partner.
4. Select "Points prize" and enter the points required.
5. Keep using the existing NFC-holder, organization, and total redemption
   limits; those controls remain independent of the loyalty balance.

