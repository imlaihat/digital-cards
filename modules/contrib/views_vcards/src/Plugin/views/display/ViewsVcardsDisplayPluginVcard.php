<?php

namespace Drupal\views_vcards\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\views\Plugin\views\display\PathPluginBase;
use Drupal\views\Plugin\views\display\ResponseDisplayPluginInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipStream\CompressionMethod;
use ZipStream\OperationMode;
use ZipStream\ZipStream;

/**
 * The plugin that handles one or more vCards.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "views_vcard",
 *   title = @Translation("vCard"),
 *   help = @Translation("Display the view results as a vCard or Zip file."),
 *   uses_route = TRUE,
 *   admin = @Translation("vCard"),
 *   returns_response = TRUE
 * )
 */
class ViewsVcardsDisplayPluginVcard extends PathPluginBase implements ResponseDisplayPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesAJAX = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesPager = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesMore = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAreas = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $renderer;

  /**
   * Constructs a PathPluginBase object.
   *
   * @param array $configuration
   *   A test_views array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteProviderInterface $route_provider, StateInterface $state, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider, $state);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('state'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'views_vcard';
  }

  /**
   * {@inheritdoc}
   */
  public static function buildResponse($view_id, $display_id, array $args = []) {
    // Load the View we're working with and set its display ID so we can get
    // the amount of rows and choose the response type (cacheable vs stream)
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->setArguments($args);

    // Get the amount of rows for choosing Zip or vCard output.
    $view->get_total_rows = TRUE;

    // Execute the view.
    $view->preExecute();
    $view->execute();
    $view->postExecute();

    // If the executed view has more than 1 row, deliver as a Zipped file.
    if ($view->total_rows > 1) {
      return static::buildZippedItemResponse($view);
    }
    // Otherwise return a single item response.
    return static::buildSingleItemResponse($view);
  }

  /**
   * Builds a stream response of a multi vCard zip file.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to convert to a response.
   *
   * @return \Symfony\Component\HttpFoundation\StreamedResponse
   *   The streamed response.
   */
  private static function buildZippedItemResponse(ViewExecutable $view) {
    $filename = (!empty($view->getTitle()) ? $view->getTitle() : 'vcards') . '.zip';

    // Prepare the empty streamed response.
    $response = new StreamedResponse(
      NULL,
      StreamedResponse::HTTP_OK,
      [
        'Content-Type' => 'application/zip; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        'Cache-Control' => 'must-revalidate, no-cache, private',
        'Max-Age' => '0',
      ]
    );

    // Set the response callback function.
    $response->setCallback(function () use ($view) {

      /** @var \Drupal\Core\Render\RendererInterface $drupal_renderer */
      $drupal_renderer = \Drupal::service('renderer');
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliteration */
      $transliteration = \Drupal::service('transliteration');
      $phpzip = new ZipStream();
      // If we are on ZipStream v3+ the archive has to be created differently.
      if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        // We not want to send the HTTP headers from ZipStream-PHP.
        $phpzip = new ZipStream(OperationMode::NORMAL, '', NULL, CompressionMethod::STORE, 6, TRUE, TRUE, FALSE);
      }

      // Store all names in a keyed array for quick lookup of names.
      $rows = [];
      foreach ($view->result as $row_index => $item) {
        $view->row_index = $row_index;
        $rendered_item = $view->rowPlugin->render($item);

        $person_name = $rendered_item['#row']->full_name;
        $person_name = $drupal_renderer->renderInIsolation($person_name);
        $person_name = $unique_person_name = $transliteration->transliterate($person_name, $langcode);

        $iterator = 2;
        // Append an iterator to already existing names.
        while (array_key_exists($unique_person_name, $rows)) {
          $unique_person_name = $person_name . '_' . $iterator;
          $iterator++;
        }
        $rows[$unique_person_name] = $drupal_renderer->renderInIsolation($rendered_item);

        $phpzip->addFile($unique_person_name . '.vcf', $rows[$unique_person_name]);
      }
      $phpzip->finish();
    });

    return $response;
  }

  /**
   * Builds a cacheable response of a vCard file.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to convert to a response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private static function buildSingleItemResponse(ViewExecutable $view) {
    /** @var \Drupal\Core\Render\RendererInterface $drupal_renderer */
    $drupal_renderer = \Drupal::service('renderer');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliteration */
    $transliteration = \Drupal::service('transliteration');

    // This should be only one.
    foreach ($view->result as $item) {
      $view->row_index = $item->index;
      $build = $view->rowPlugin->render($item);
    }

    $person_name = $build['#row']->full_name;
    $person_name = $drupal_renderer->renderInIsolation($person_name);

    $filename = $transliteration->transliterate($person_name, $langcode);
    $filename = (!empty($filename) ? $filename : 'vcard') . '.vcf';

    // Setup an empty response.
    $response = new Response('', Response::HTTP_OK, [
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Content-Type' => 'text/vcard; charset=UTF-8',
    ]);

    $build['#response'] = $response;
    $output = (string) $drupal_renderer->renderInIsolation($build);

    if (empty($output)) {
      throw new NotFoundHttpException();
    }

    $response->setContent($output);

    // @todo Convert this into a cacheable response.
    // $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    // $response->addCacheableDependency($cache_metadata);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    parent::execute();

    return $this->view->render();
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    $output = $this->view->render();

    if (!empty($this->view->live_preview)) {
      $output = [
        '#prefix' => '<pre>',
        '#plain_text' => $this->renderer->renderRoot($output),
        '#suffix' => '</pre>',
      ];
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = $this->view->style_plugin->render();

    $this->applyDisplayCacheabilityMetadata($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultableSections($section = NULL) {
    $sections = parent::defaultableSections($section);

    if (in_array($section, ['style', 'row'])) {
      return FALSE;
    }

    return $sections;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['displays'] = ['default' => []];

    // Overrides for standard stuff.
    $options['style']['contains']['type']['default'] = 'views_vcard_style';
    $options['row']['contains']['type']['default'] = 'views_vcard_fields';
    $options['defaults']['default']['style'] = FALSE;
    $options['defaults']['default']['row'] = FALSE;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function newDisplay() {
    parent::newDisplay();

    // Set the default row style. Ideally this would be part of the option
    // definition, but in this case it's dependent on the view's base table,
    // which we don't know until init().
    if (empty($this->options['row']['type'])) {
      $options = $this->getOption('row');
      $options['type'] = 'views_vcard_fields';
      $this->setOption('row', $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    // Since we're childing off the 'path' type, we'll still *call* our
    // category 'page' but let's override it so it says vCard settings.
    $categories['page'] = [
      'title' => $this->t('vCard settings'),
      'column' => 'second',
      'build' => [
        '#weight' => -10,
      ],
    ];

    $displays = array_filter($this->getOption('displays'));
    if (count($displays) > 1) {
      $attach_to = $this->t('Multiple displays');
    }
    elseif (count($displays) == 1) {
      $display = array_shift($displays);
      $displays = $this->view->storage->get('display');
      if (!empty($displays[$display])) {
        $attach_to = $displays[$display]['display_title'];
      }
    }

    if (!isset($attach_to)) {
      $attach_to = $this->t('None');
    }

    $options['displays'] = [
      'category' => 'page',
      'title' => $this->t('Attach to'),
      'value' => $attach_to,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'displays':
        $form['#title'] .= $this->t('Attach to');
        $displays = [];
        foreach ($this->view->storage->get('display') as $display_id => $display) {
          // @todo The display plugin should have display_title and id as well.
          if ($this->view->displayHandlers->has($display_id) && $this->view->displayHandlers->get($display_id)->acceptAttachments()) {
            $displays[$display_id] = $display['display_title'];
          }
        }
        $form['displays'] = [
          '#title' => $this->t('Displays'),
          '#type' => 'checkboxes',
          '#description' => $this->t('The vCard icon will be available only to the selected displays.'),
          '#options' => array_map('\Drupal\Component\Utility\Html::escape', $displays),
          '#default_value' => $this->getOption('displays'),
        ];
        break;

      case 'path':
        $form['path']['#description'] = $this->t('This view will be displayed by visiting this path on your site. It is recommended that the path be something like "userlist/download" or "user/%/vcard.vcf", putting one % in the path for each contextual filter you have defined in the view.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    switch ($section) {
      case 'displays':
        $this->setOption($section, $form_state->getValue($section));
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(ViewExecutable $clone, $display_id, array &$build) {
    $displays = $this->getOption('displays');
    if (empty($displays[$display_id])) {
      return;
    }

    // Defer to the vCard style plugin; it may put in meta information, and/or
    // attach a vCard icon.
    $clone->setArguments($this->view->args);
    $clone->setDisplay($this->display['id']);
    $clone->buildTitle();
    $title = $clone->getTitle();

    if ($plugin = $clone->display_handler->getPlugin('style')) {
      $plugin->attachTo($build, $display_id, $clone->getUrl(), $title);
      foreach ($clone->feedIcons as $feed_icon) {
        $this->view->feedIcons[] = $feed_icon;
      }
    }

    // Clean up.
    $clone->destroy();
    unset($clone);
  }

  /**
   * {@inheritdoc}
   */
  public function usesLinkDisplay() {
    return TRUE;
  }

}
