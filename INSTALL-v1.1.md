# Digital Card Platform multilingual UX update 1.2

This release expands the editable Arabic catalog across dashboards, forms,
notifications, emails, merchant workflows, table tools, and validation messages.
Technical helper text was rewritten as concise guidance for the person using the
page.

## Install or update

1. Back up the database and `modules/custom` directory.
2. Extract the archive into the Drupal root so that `modules/custom` and
   `themes/custom` merge with the existing directories.
3. Run database updates through `/update.php` or Drush.
4. Clear all Drupal caches.
5. Review translations at **Configuration > Regional and language > User
   interface translation**. Search for any English source string to edit its
   Arabic wording without changing code.

The `digital_card_i18n_update_10003()` update imports the expanded catalog as
customized translations. Administrator edits remain configurable through
Drupal's translation interface.

This version also fixes language-prefixed Platform Dashboard quick links and
translation support on the Scan Analytics and Organization Card Themes pages.

## Content translation

Interface translation does not automatically translate organization names,
plans, card-holder names, offer wording, or other stored content. Editors can
add Arabic content translations where enabled. Offer forms also provide
dedicated Arabic wording fields with English fallback.
