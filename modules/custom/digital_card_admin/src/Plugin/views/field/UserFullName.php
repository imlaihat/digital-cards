<?php

namespace Drupal\digital_card_admin\Plugin\views\field;

use Drupal\Component\Utility\Html;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Displays a user's business name with a safe username fallback.
 *
 * @ViewsField("digital_card_user_full_name")
 */
class UserFullName extends FieldPluginBase {

  /**
   * This display-only field does not alter the database query.
   */
  public function query(): void {
    // The base User entity is already available on each result row.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $account = $values->_entity ?? NULL;
    if (!$account instanceof UserInterface) {
      return '';
    }

    $parts = [];
    foreach (['field_first_name', 'field_last_name'] as $field_name) {
      if ($account->hasField($field_name) && !$account->get($field_name)->isEmpty()) {
        $parts[] = trim((string) $account->get($field_name)->value);
      }
    }

    $full_name = trim(implode(' ', array_filter($parts)));
    if ($full_name === '' && $account->hasField('field_full_name') && !$account->get('field_full_name')->isEmpty()) {
      $full_name = trim((string) $account->get('field_full_name')->value);
    }

    // Existing accounts predate the profile fields. Showing the account name
    // keeps their list row meaningful until an administrator adds their name.
    return Html::escape($full_name !== '' ? $full_name : $account->getDisplayName());
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

}
