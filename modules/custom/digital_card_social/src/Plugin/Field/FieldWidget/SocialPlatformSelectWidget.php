<?php

namespace Drupal\digital_card_social\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\digital_card_social\Service\SocialPlatformRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the controlled Social Platform select widget.
 *
 * @FieldWidget(
 *   id = "digital_card_social_platform_select",
 *   label = @Translation("Controlled social platform"),
 *   field_types = {"string"}
 * )
 */
final class SocialPlatformSelectWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    private readonly SocialPlatformRegistry $registry,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('digital_card_social.registry'),
    );
  }

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $current = trim((string) ($items[$delta]->value ?? ''));
    $canonical = $this->registry->resolveId($current);
    $options = $this->registry->options();
    $default = $canonical ?? $current;
    if ($canonical !== NULL && !isset($options[$canonical])) {
      $definition = $this->registry->get($canonical);
      $options[$canonical] = $this->t('@platform (disabled — choose another platform)', [
        '@platform' => $definition['label'] ?? $current,
      ]);
    }
    elseif ($current !== '' && $canonical === NULL) {
      // Do not make unrelated edits impossible when an older card contains a
      // value that cannot be migrated automatically. It remains visibly
      // marked and must be replaced before a new URL can pass validation.
      $options[$current] = $this->t('Legacy value: @value — choose a supported platform', ['@value' => $current]);
    }
    $element['value'] = $element + [
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('- Select a platform -'),
      '#default_value' => $default,
    ];
    // Override the stored field description so the editor always receives a
    // concise, interface-translatable instruction in the active language.
    $element['value']['#description'] = $this->t('Select the social network for this link. The card will automatically use its approved name and icon.');
    return $element;
  }

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      $raw = trim((string) ($value['value'] ?? ''));
      $value['value'] = $this->registry->resolveId($raw) ?? $raw;
    }
    unset($value);
    return $values;
  }

}
