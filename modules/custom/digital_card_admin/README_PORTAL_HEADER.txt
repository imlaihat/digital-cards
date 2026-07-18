Digital Card Admin - Portal Header Implementation
=================================================

This package keeps all menus manually managed in Drupal UI and does not create
or override menu links.

New blocks included:

1. Digital Card: Organization Portal Header
   Plugin ID: digital_card_organization_portal_header
   Recommended region: Header or Primary menu
   Recommended visibility paths:
     /organization/*
     /my-cards
     /group/*/content/create/group_node:digital_business_card

2. Digital Card: Platform Admin Header
   Plugin ID: digital_card_platform_admin_header
   Recommended region: Header or Primary menu
   Recommended visibility paths:
     /platform/*

Recommended manual cleanup after installing this package:

1. Go to /admin/structure/block
2. Place "Digital Card: Organization Portal Header" in Header or Primary menu.
3. Restrict it to Organization Portal pages only.
4. Place "Digital Card: Platform Admin Header" in Header or Primary menu if you want the same SaaS-style header for platform pages.
5. Disable or remove the old manually created Organization Portal links from the standard Primary menu if they duplicate this new header.
6. Do not delete the routes or dashboard pages. The dashboard links use route-safe URLs.

Commands after extracting:

  drush cr

If you still see the old blue menu above the new portal header, disable the old
Main navigation block on /organization/* pages, or remove only the old
Organization Portal parent/children from Main navigation.
Platform Admin header additions
-------------------------------
The header now includes:
- Card Themes: /platform/organization-card-themes
- Scan Analytics: /platform/card-scans

Links are permission-aware. Grant these permissions to Platform Admin:
- manage organization card themes
- view digital card scan analytics
