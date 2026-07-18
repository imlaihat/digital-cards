Digital Card Admin safe dashboard fix

Changes:
1. Keeps dashboard routes/controllers/templates/CSS.
2. Removes module-defined menu links so manually-created Primary menu links are not affected.
3. Adds update hook digital_card_admin_update_10001 to remove stale block configs assigned to missing theme "color".
4. Does not create or modify any theme configuration.

After extracting, run:
  drush updb -y
  drush cr

