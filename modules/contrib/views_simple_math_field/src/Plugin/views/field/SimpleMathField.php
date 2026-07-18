<?php

namespace Drupal\views_simple_math_field\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\NumericField;
use Drupal\views\ResultRow;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Andileco\Util\EvalMath\EvalMath;
use Drupal\Core\Url;
use Andileco\Util\EvalMath\Exception\DivisionByZeroException;

/**
 * @file
 * Defines Drupal\views_simple_math_field\Plugin\views\field\SimpleMathField.
 */

/**
 * Field handler to complete mathematical operation.
 *
 * @ingroup views_field_handlers
 * @ViewsField("field_views_simple_math_field")
 */
class SimpleMathField extends NumericField implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Support multiple commerce price fields.
   *
   * @var array
   */
  protected $commercePriceFields = [
    'commerce_price_default',
    'commerce_product_variation',
    'commerce_price_plain',
    'commerce_price_calculated',
    'commerce_order_total_summary',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {

    return FALSE;
  }

  /**
   * Sets the initial field data at zero.
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Give this field and alias.
    $this->field_alias = 'field_views_simple_math_field';

    $options['fieldset_one']['default'] = NULL;
    $options['fieldset_one']['data_fields'] = ['default' => NULL];
    $options['fieldset_one']['formula'] = ['default' => NULL];
    $options['mute_logs'] = ['default' => 0];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $fieldDelta = preg_replace('[\D]', '', $this->options['id']);
    $fieldList = $this->displayHandler->getFieldLabels();
    foreach ($fieldList as $key => $value) {
      if ($this->field_alias === $key && $fieldDelta < preg_replace('[\D]', '', $key)) {
        unset($fieldList[$key]);
      }
      else {
        $fieldList[$key] .= new FormattableMarkup(". Formula token: @%field", ["%field" => $key]);
      }
    }
    unset($fieldList[$this->options['id']]);
    $form['fieldset_one'] = [
      '#type' => 'fieldset',
      '#title' => t('Select the fields to use in the formula.'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#weight' => -10,
      '#required' => TRUE,
    ];
    $form['fieldset_one']['data_field'] = [
      '#type' => 'checkboxes',
      '#title' => t('Data Fields'),
      '#options' => $fieldList,
      '#default_value' => $this->options['fieldset_one']['data_field'],
      '#weight' => -10,
    ];
    $form['fieldset_one']['formula'] = [
      '#type' => 'textarea',
      '#title' => t('Formula'),
      '#default_value' => $this->options['fieldset_one']['formula'],
      '#weight' => -8,
      '#description' => t('Enter the formula to give this field its value. You can use any fields specified in the checkboxes above, using the formula token listed beside the field name. It uses the EvalMath library, refer to this web to see math expressions: <a href=":url">project in github</a>.', [
        ':url' => Url::fromUri('https://github.com/andileco/eval-math')
          ->toString(),
      ]),
    ];
    $form['mute_logs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mute database logs for this field'),
      '#default_value' => $this->options['mute_logs'],
      '#weight' => -7,
      '#description' => t('Normally, a DivisionByZeroException will occur if you attempt to divide a number by zero within your Views Simple Math Field. If you are plotting data containing zeros, for example, and know that this exception is likely to occur, you can check this box to prevent logging of this exception in your Drupal database log.'),
    ];

    return $form;
  }

  /**
   * Determine the field type we are dealing with.
   *
   * @param string $field
   *   The view field name string.
   *
   * @return string
   *   The type of field as understood by Views.
   */
  protected function getFieldType($field) {
    $field_handler = $this->displayHandler->getHandler('field', $field)->options;
    if (!empty($field_handler['type'])) {
      $field_type = $field_handler['type'];
    }
    else {
      $field_type = 'undefined';
    }

    return $field_type;
  }

  /**
   * Determine the field plugin we are dealing with.
   *
   * @param string $field
   *   A view field name string.
   *
   * @return string
   *   The field plugin ID.
   */
  protected function getFieldPlugin($field) {
    $field_handler = $this->displayHandler->getHandler('field', $field)->options;
    if (!empty($field_handler['plugin_id'])) {
      $field_plugin = $field_handler['plugin_id'];
    }
    else {
      $field_plugin = 'undefined';
    }

    return $field_plugin;
  }

  /**
   * Determine if the field comes from a relationship.
   *
   * @param string $field
   *   The view field name string.
   *
   * @return mixed
   *   The relationship as defined by Views.
   */
  protected function getFieldRelationship($field) {
    $field_handler = $this->displayHandler->getHandler('field', $field)->options;
    if (!empty($field_handler['relationship']) && $field_handler['relationship'] !== 'none') {
      $relationship = $field_handler['relationship'];
    }
    else {
      $relationship = NULL;
    }

    return $relationship;
  }

  /**
   * Determine if the field is rewritten/altered.
   *
   * @param string $field
   *   The view field name string.
   *
   * @return mixed
   *   The text of the rewritten field.
   */
  protected function getRewriteStatus($field) {
    $field_handler = $this->displayHandler->getHandler('field', $field)->options;
    if (isset($field_handler['alter']['alter_text']) && !empty($field_handler['alter']['text'])) {
      $alter = $field_handler['alter']['text'];
    }
    else {
      $alter = NULL;
    }

    return $alter;
  }

  /**
   * Get the entity referenced in the view relationship.
   *
   * @param \Drupal\views\ResultRow $values
   *   The ResultRow object.
   * @param string $field
   *   The view field name string.
   * @param mixed $relationship
   *   The View relationship.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The relationship entity.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRelationshipEntity(ResultRow $values, $field, $relationship) {
    $relationship_entity = NULL;
    // Get the entity type of the relationship.
    $relationship_entity_type = $this->displayHandler
      ->getHandler('field', $field)->getEntityType();
    $relationship_entities = $values->_relationship_entities;
    // First check the referenced entity.
    if (isset($relationship_entities[$relationship])) {
      // Get the id of the relationship entity.
      $entity_id = $relationship_entities[$relationship]->id();
      // Get the data of the relationship entity.
      $relationship_entity = $this->entityTypeManager
        ->getStorage($relationship_entity_type)
        ->load($entity_id);
    }

    return $relationship_entity;
  }

  /**
   * Remove thousands marker.
   *
   * @param string $field
   *   The view field name string.
   * @param mixed $data
   *   The number that may need a thousands separator removed.
   *
   * @return mixed
   *   The resulting number with thousands separator removed.
   */
  protected function removeSeparator($field, $data) {
    if (!empty($this->view->field[$field]->options['separator'])) {
      $separator = $this->view->field[$field]->options['separator'];
    }
    if (!empty($separator)) {
      if (strpos($data, $separator)) {
        $data = str_replace($separator, '', $data);
      }
    }
    /*
     * Even if a separator has not been selected, your data may have a
     * common separator already applied. This is a very U.S.-centric
     * approach, so open to other ideas here!
     */
    else {
      if (strpos($data, ',')) {
        $data = str_replace(',', '', $data);
      }
      if (strpos($data, ' ')) {
        $data = str_replace(' ', '', $data);
      }
    }

    return $data;
  }

  /**
   * Get the value of a simple math field.
   *
   * @param \Drupal\views\ResultRow $values
   *   Row results.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The current row entity.
   * @param bool $field
   *   The field we are fetching.
   *
   * @return mixed
   *   The field value.
   *
   * @throws \Exception
   */
  protected function getFieldValue(ResultRow $values, $entity, $field) {
    // Determine what type of field is being used.
    $field_type = $this->getFieldType($field);
    // Determine what type of field plugin is being used.
    $field_plugin = $this->getFieldPlugin($field);
    // If the field is rewritten, get the rewritten text. Else, returns null.
    $rewritten = $this->getRewriteStatus($field);
    // Check if the display is aggregated. Not needed right now.
    // $isAggregated = $this->view->getDisplay()->getOption('group_by');
    $data = NULL;
    // Compatibility with the views_entity_form_field module.
    if ($field_plugin === 'entity_form_field') {
      $field_handler = $this->displayHandler->getHandler('field', $field)->options;
      if (!empty($field_handler['plugin']['type'])) {
        $field_type = $field_handler['plugin']['type'];
      }
      if (!empty($field_handler['field'])) {
        $form_field = $field_handler['field'];
        $prefix = 'form_field_';
        if (0 === strpos($form_field, $prefix)) {
          $field = substr($form_field, strlen($prefix));
        }
      }
      $relationship = $this->getFieldRelationship($field);
      if ($relationship) {
        // Use the relationship's entity to fetch the field value.
        $entity = $this->getRelationshipEntity($values, $field, $relationship);
      }
      // For Commerce fields.
      if (in_array($field_type, $this->commercePriceFields)) {
        $commerce_field_id = $this->displayHandler->getHandler('field', 'form_field_' . $field)->options['id'];
        if ($entity->hasField($commerce_field_id) && !empty($entity->get($commerce_field_id)->getValue())) {
          $data = $entity->get($commerce_field_id)->first()->toPrice();
        }
      }
      else {
        $data = $entity->get($field)->getValue()[0]['value'];
      }

      return $data;
    }

    // Process if not undefined.
    if ($field_type !== 'undefined') {
      // Get the value of a field that comes from a relationship.
      $relationship = $this->getFieldRelationship($field);
      if ($relationship) {
        // Use the relationship's entity to fetch the field value.
        $entity = $this->getRelationshipEntity($values, $field, $relationship);
      }
      // The next two statements handle fields with or without a relationship.
      if (isset($entity) && $rewritten) {
        // If already numeric, there is no need for advancedRender().
        if (is_numeric($rewritten) == TRUE) {
          $data = $rewritten;
        }
        else {
          // @todo Rewrite using dependency injection.
          if (\Drupal::routeMatch()->getRouteName() == 'entity.view.preview_form') {
            \Drupal::service('messenger')
              ->addMessage(t('It appears that <em>@field</em> is rewritten and requires advanced rendering. Do not use the Views Simple Math Field sort handler for this View.', [
                '@field' => $field,
              ]), 'warning');
          }
          $data = $this->view->field[$field]->advancedRender($values);
        }
      }
      if (isset($entity) && !$rewritten) {
        // Gets the value from the row, which works when aggregated (or not).
        $data = $this->view->field[$field]->getValue($values);

        /*
         * Keeping these here, but I've commented out in favor of the above
         * line, as this was not working with aggregation.
         * $field_base = $this->displayHandler
         *   ->getHandler('field', $field)->field;
         * if ($entity->hasField($field_base)) {
         *   $data = $entity->get($field_base)->getValue()[0]['value'];
         * }
         */

        // For Commerce fields.
        if (in_array($field_type, $this->commercePriceFields)) {
          $commerce_field_id = $this->displayHandler->getHandler('field', $field)->options['id'];
          if ($entity->hasField($commerce_field_id) && !empty($entity->get($commerce_field_id)->getValue())) {
            $data = $entity->get($commerce_field_id)->first()->toPrice();
          }
        }
      }
    }
    /*
     * Some fields, such as fields from Views Database Connector, pull
     * directly from the database or an index. This attempts to handle those
     * types of fields.
     */
    else {
      if (isset($this->view->field[$field]->original_value)) {
        $data = $this->view->field[$field]->original_value;
      }
      // Decided to leave this as a fallback.
      else {
        $data = $this->view->field[$field]->getValue($values);
      }

      if ($rewritten) {
        // @todo Rewrite using dependency injection.
        if (\Drupal::routeMatch()->getRouteName() == 'entity.view.preview_form') {
          \Drupal::service('messenger')
            ->addMessage(t('Views Simple Math Field sometimes has difficulty rendering the correct value for rewritten fields. You may want to double check that field ID <em>@field</em> is properly outputting a value.', [
              '@field' => $field,
            ]), 'warning');
        }
        $data = $this->displayHandler->getHandler('field', $field)
          ->advancedRender($values);
      }
    }

    // There's no value. Default to 0.
    if (!isset($data)) {
      $data = 0;
    }

    // Remove the thousands marker.
    $data = $this->removeSeparator($field, $data);

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function getValue(ResultRow $values, $field = NULL) {
    parent::getValue($values, $field);

    // If no fields are selected as data fields, return NULL.
    if (!isset($this->options['fieldset_one']['data_field'])) {
      return NULL;
    }

    // Get the entity from the row.
    $entity = $this->getEntityOrNull($values);

    // Collect all the fields checked.
    $fields_in_formula = [];
    foreach ($this->options['fieldset_one']['data_field'] as $key => $value) {
      if ($value) {
        $raw_field_in_formula = $this->getFieldValue($values, $entity, $key);
        // Filter and sanitize out the float values.
        if (preg_match('/^.*?([\d]+(?:\.[\d]+)?).*?$/', $raw_field_in_formula, $match)) {
          $raw_field_in_formula_sane = floatval($match[0]);
          $fields_in_formula['@' . $key] = filter_var($raw_field_in_formula_sane, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        else {
          $fields_in_formula['@' . $key] = 0;
        }
      }
    }
    // Format the numbers to a string that EvalMath can evaluate.
    $formula = new FormattableMarkup($this->options['fieldset_one']['formula'], $fields_in_formula);
    $m = new EvalMath();
    try {
      $result = $m->evaluate($formula);
    }
    catch (DivisionByZeroException $e) {
      if ($this->options['mute_logs'] != 1) {
        \Drupal::logger('views_simple_math_field')
          ->error('DivisionByZeroException');
      }
    }

    // Return the value if evaluate is successful.
    return $result ?? NULL;
  }

  /**
   * Get the entity from the row.
   *
   * @param \Drupal\views\ResultRow $values
   *   The ResultRow object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity.
   */
  public function getEntityOrNull(ResultRow $values): ?object {
    // Get the entity from the row.
    if (!empty($values->_entity)) {
      return $this->getEntity($values);
    }
    else {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

}
