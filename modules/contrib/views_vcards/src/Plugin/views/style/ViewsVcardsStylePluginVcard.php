<?php

namespace Drupal\views_vcards\Plugin\views\style;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Default style plugin to render one or more vCards.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "views_vcard_style",
 *   title = @Translation("vCard"),
 *   help = @Translation("Generates a vCard from a view."),
 *   display_types = {"views_vcard"}
 * )
 */
class ViewsVcardsStylePluginVcard extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * Defines the way the attachment should be rendered.
   *
   * @todo This should implement AttachableStyleInterface once
   * https://www.drupal.org/node/2779205 lands.
   */
  public function attachTo(array &$build, $display_id, Url $url, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $url = $url->setOptions($url_options)->toString();

    // Add the vCard icon to the view.
    $this->view->feedIcons[] = [
      '#theme' => 'views_vcards_vcard_icon',
      '#url' => $url,
      '#title' => $title,
      '#attached' => [
        'library' => [
          'views_vcards/vcard_icon',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->view->rowPlugin)) {
      trigger_error('Drupal\views\Plugin\views\style\ViewsVcardsStylePluginVcard: Missing row plugin', E_WARNING);
      return [];
    }

    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }
    unset($this->view->row_index);

    return $rows;
  }

}
