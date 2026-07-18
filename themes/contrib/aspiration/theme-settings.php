<?php

/**
 * @file
 * Theme settings form for Aspiration Bootstrap theme.
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function aspiration_form_system_theme_settings_alter(array &$form, FormState $form_state): void {
  aspiration_update_slider_images();
  $form['theme_settings']['#open'] = FALSE;
  $form['logo']['#open'] = FALSE;
  $form['favicon']['#open'] = FALSE;

  // Create vertical tabs for organizing the Aspiration settings.
  $form['aspiration_tabs'] = [
    '#type' => 'vertical_tabs',
    '#prefix' => '<h2><small>' . t('Aspiration Theme Settings') . '</small></h2>',
    '#weight' => -10,
  ];

  // Bootstrap Configuration Section.
  $form['aspiration_bootstrap'] = [
    '#type' => 'details',
    '#title' => t('Bootstrap'),
    '#open' => TRUE,
    '#group' => 'aspiration_tabs',
  ];

  // Dropdown for selecting the Bootstrap library source.
  $form['aspiration_bootstrap']['bootstrap'] = [
    '#type' => 'select',
    '#title' => t('Bootstrap Library'),
    '#default_value' => theme_get_setting('bootstrap'),
    '#options' => [
      'none' => t('None'),
      'cdn' => t('Bootstrap From CDN'),
      'local' => t('Bootstrap From Local'),
      'libraries' => t('Bootstrap From Libraries'),
    ],
    '#description' => t('Select the Bootstrap library source for your site. Choose "None" to turn off Bootstrap, or pick from CDN, Local, or Libraries for various integration options.'),
  ];

  // Versions of Bootstrap for CDN and Local options.
  $bootstrap_versions = [
    'v5.3' => t('Bootstrap 5.3'),
    'v5.2' => t('Bootstrap 5.2'),
    'v5.1' => t('Bootstrap 5.1'),
    'v5.0' => t('Bootstrap 5.0'),
    'v4' => t('Bootstrap 4.6'),
  ];

  // Dropdown for selecting Bootstrap version from CDN.
  $form['aspiration_bootstrap']['bootstrap_cdn'] = [
    '#type' => 'select',
    '#title' => t('Bootstrap Version (CDN)'),
    '#default_value' => theme_get_setting('bootstrap_cdn'),
    '#options' => $bootstrap_versions,
    // Conditional visibility based on selected library source.
    '#states' => [
      'visible' => [':input[name="bootstrap"]' => ['value' => 'cdn']],
    ],
    '#description' => t('Choose the Bootstrap version to use when loading from a CDN or local source. This setting is available only if "Bootstrap from CDN or Local" is selected as the library source.'),
  ];

  // Dropdown for selecting Bootstrap version from Local files.
  $form['aspiration_bootstrap']['bootstrap_local'] = [
    '#type' => 'select',
    '#title' => t('Bootstrap Version (Local)'),
    '#default_value' => theme_get_setting('bootstrap_local'),
    '#options' => $bootstrap_versions,
    // Conditional visibility based on selected library source.
    '#states' => [
      'visible' => [':input[name="bootstrap"]' => ['value' => 'local']],
    ],
  ];

  // Dropdown for selecting Bootstrap container type.
  $form['aspiration_bootstrap']['bootstrap_container'] = [
    '#type' => 'select',
    '#title' => t('Bootstrap Container Type'),
    '#default_value' => theme_get_setting('bootstrap_container'),
    '#options' => [
      'container' => t('Container - Fixed Width'),
      'container-fluid' => t('Container Fluid - Full Width'),
    ],
    '#description' => t('Select the Bootstrap container type for your layout. "Container – Fixed Width" offers a responsive layout with set widths, while "Container Fluid – Full Width" stretches the layout to fit the full width of the screen.'),
  ];

  // Global Configuration Section.
  $form['aspiration_global'] = [
    '#type' => 'details',
    '#title' => t('Global Settings'),
    '#open' => FALSE,
  // Grouping under Aspiration tabs.
    '#group' => 'aspiration_tabs',
  ];

  // Checkbox for enabling/disabling sticky header.
  $form['aspiration_global']['sticky_header'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable Sticky Header'),
    '#default_value' => theme_get_setting('sticky_header'),
    '#description' => t('Make the header sticky on scroll.'),
  ];

  // Dropdown for selecting local fonts.
  $form['aspiration_global']['local_fonts'] = [
    '#type' => 'select',
    '#title' => t('Local Fonts'),
    '#default_value' => theme_get_setting('local_fonts'),
    '#options' => [
      'none' => t('Default Fonts'),
      'helvetica' => t('Helvetica, "Trebuchet MS", Verdana, sans-serif'),
      'verdana' => t('Verdana, sans-serif'),
    ],
    '#description' => t('Choose a local font for your site. "Default Fonts" will use system fonts, while the other options allow for a more customized look.'),
  ];

  // Dropdown for selecting Google Fonts.
  $form['aspiration_global']['google_fonts'] = [
    '#type' => 'select',
    '#title' => t('Google Fonts'),
    '#default_value' => theme_get_setting('google_fonts'),
    '#options' => [
      'none' => t('None'),
      'open-sans' => t('Open Sans, sans-serif'),
      'roboto' => t('Roboto, sans-serif'),
      'lato' => t('Lato, sans-serif'),
      'poppins' => t('Poppins, sans-serif'),
    ],
    '#description' => t('Select a Google Font to enhance your site’s typography. This option allows you to choose from a variety of popular web fonts.'),
  ];

  // Dropdown for selecting global icon library.
  $form['aspiration_global']['global_icons'] = [
    '#type' => 'checkboxes',
    '#title' => t('Global Icons'),
    '#default_value' => theme_get_setting('global_icons'),
    '#options' => [
      'fontawesome' => t('Font Awesome Icons'),
      'google-material' => t('Google Material Icons'),
      'bootstrap-icons' => t('Bootstrap Icons'),
      'flaticon' => t('Flaticon'),
    ],
    '#description' => t('Select one or more icon libraries to use throughout your site. Icons can enhance user experience and visual appeal.'),
  ];

  // Dropdown for selecting Font Awesome version.
  $form['aspiration_global']['global_icons_fontawesome'] = [
    '#type' => 'select',
    '#title' => t('Fontawesome Icons Library'),
    '#default_value' => theme_get_setting('global_icons_fontawesome'),
    '#options' => [
      'fa_local' => t('FA Local'),
      'fa_cdn' => t('FA CDN'),
    ],
      // Conditional visibility based on icon selection.
    '#states' => [
      'visible' => [':input[name="global_icons"]' => ['value' => 'fontawesome']],
    ],
    '#description' => t('Choose the version of Font Awesome icons to load. "FA Local" uses local files, while "FA CDN" loads icons from a content delivery network for improved performance.'),
  ];

  // Textarea for adding custom global styles.
  $form['aspiration_global']['global_style'] = [
    '#type' => 'textarea',
    '#title' => t('Global Style'),
    '#placeholder' => t('Global CSS'),
    '#default_value' => theme_get_setting('global_style'),
    '#description' => t('Add your custom CSS rules here to apply global styles to your site. This allows for further customization beyond the provided options. Make sure to include valid CSS syntax for effective styling.'),
  ];

  $form['aspiration_global']['button_style'] = [
    '#type' => 'select',
    '#title' => t('Button style'),
    '#default_value' => theme_get_setting('button_style'),
    '#options' => [
      'rounded' => t('Rounded corners'),
      'button-no-rounded' => t('No rounded corners'),
    ],
    '#description' => t('Choose the button style. "Rounded corners" gives buttons a softer, more modern look, while "No rounded corners" provides a more traditional square button style.'),
  ];

  // Define social media options.
  $social_media_options = aspiration_get_social_media_platforms();

  // Top Header Configuration Section.
  $form['aspiration_top_header'] = [
    '#type' => 'details',
    '#title' => t('Header Top'),
    '#open' => FALSE,
  // Grouping under Aspiration tabs.
    '#group' => 'aspiration_tabs',
  ];

  // Checkbox for showing/hiding the top header.
  $form['aspiration_top_header']['show_header_top'] = [
    '#type' => 'checkbox',
    '#title' => t('Show Top Header'),
    '#default_value' => theme_get_setting('show_header_top'),
    '#description' => t('Enable this option to display the top header section on your site. Uncheck to hide it.'),
  ];

  // Array of fields for the Top Header settings.
  $top_header_fields = [
    'header_address' => [
      '#type' => 'textfield',
      '#title' => t('Top Header Address'),
      '#default_value' => theme_get_setting('header_address'),
      '#description' => t('Enter the address to be displayed in the top header.'),
    ],
    'header_email' => [
      '#type' => 'textfield',
      '#title' => t('Top Header Email'),
      '#default_value' => theme_get_setting('header_email'),
      '#description' => t('Provide the email address for contact information in the top header.'),
    ],
    'header_link_1' => [
      '#type' => 'details',
      '#title' => t('Top Header Link 1'),
      'header_link_1_title' => [
        '#type' => 'textfield',
        '#title' => t('Top Header Link 1 Title'),
        '#default_value' => theme_get_setting('header_link_1_title'),
        '#description' => t('Set the title for the first link in the top header.'),
      ],
      'header_link_1_url' => [
        '#type' => 'textfield',
        '#title' => t('Top Header Link 1 URL'),
        '#default_value' => theme_get_setting('header_link_1_url'),
        '#description' => t('Enter the URL for the first link in the top header.'),
      ],
    ],
    'header_link_2' => [
      '#type' => 'details',
      '#title' => t('Top Header Link 2'),
      'header_link_2_title' => [
        '#type' => 'textfield',
        '#title' => t('Top Header Link 2 Title'),
        '#default_value' => theme_get_setting('header_link_2_title'),
        '#description' => t('Set the title for the second link in the top header.'),
      ],
      'header_link_2_url' => [
        '#type' => 'textfield',
        '#title' => t('Top Header Link 2 URL'),
        '#default_value' => theme_get_setting('header_link_2_url'),
        '#description' => t('Enter the URL for the second link in the top header.'),
      ],
    ],
    'header_social_medias' => [
      '#type' => 'details',
      '#title' => t('Top Header Social Media Links'),
      'social_media_prefix' => [
        '#type' => 'textfield',
        '#title' => t('Top Header Social Media Links Prefix'),
        '#default_value' => theme_get_setting('social_media_prefix'),
        '#description' => t('Specify a prefix (e.g., "Follow us on") for the social media links.'),
      ],
      'header_social_media' => [
        '#type' => 'checkboxes',
        '#title' => t('Select Social Media Platforms'),
        '#options' => $social_media_options,
        '#default_value' => theme_get_setting('header_social_media'),
        '#description' => t('Select the social media platforms to display in the top header.'),
      ],
    ],
  ];

  // Add top header fields with visibility conditions.
  foreach ($top_header_fields as $key => $field) {
    // Visibility condition for fields based on the checkbox.
    $field['#states'] = [
      'visible' => [':input[name="show_header_top"]' => ['checked' => TRUE]],
    ];
    // Add fields to the top header section.
    $form['aspiration_top_header'][$key] = $field;
  }

  // Header Bottom Configuration Section.
  $form['aspiration_header'] = [
    '#type' => 'details',
    '#title' => t('Header Bottom'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  // Dropdown for selecting header sidebar icon position.
  $form['aspiration_header']['hdr_sidebar_icn_pos'] = [
    '#type' => 'select',
    '#title' => t('Header Sidebar Icon Position'),
    '#default_value' => theme_get_setting('hdr_sidebar_icn_pos'),
    '#options' => [
      '' => t('No Header Sidebar'),
      'left' => t('Left'),
      'right' => t('Right'),
    ],
    '#description' => t('Choose the position of the sidebar icon in the header. Select "No Header Sidebar" to disable the icon.'),
  ];

  $form['aspiration_header']['dark_header'] = [
    '#type' => 'checkbox',
    '#title' => t('Dark header'),
    '#default_value' => theme_get_setting('dark_header'),
    '#description' => t('Enable this option to apply a dark color scheme to the header.'),
  ];

  // Allowed formats for text formatting.
  $allowed_formats = aspiration_get_text_formats();

  // Text field for adding sidebar text.
  $form['aspiration_header']['hdr_sidebar_txt'] = [
    '#type' => 'text_format',
    '#title' => t('Menu Sidebar Text'),
    '#default_value' => theme_get_setting('hdr_sidebar_txt')['value'] ?? '',
    '#allowed_formats' => $allowed_formats,
    '#format' => theme_get_setting('hdr_sidebar_txt')['format'] ?? 'basic_html',
    '#description' => t('Enter the text to be displayed in the sidebar. This will appear only if a sidebar icon is selected.'),
    '#states' => [
      'visible' => [':input[name="hdr_sidebar_icn_pos"]' => ['!value' => '']],
    ],
  ];

  // Checkboxes for selecting social media platforms in sidebar.
  $form['aspiration_header']['hdr_sidebar_social_media'] = [
    '#type' => 'checkboxes',
    '#title' => t('Select Social Media Platforms'),
    '#options' => $social_media_options,
    '#default_value' => theme_get_setting('hdr_sidebar_social_media'),
    '#description' => t('Select the social media platforms you want to display alongside the sidebar text. This option is visible only if a sidebar icon is selected.'),
    '#states' => [
      'visible' => [':input[name="hdr_sidebar_icn_pos"]' => ['!value' => '']],
    ],
  ];

  // Checkbox for displaying search form in mobile navigation.
  $form['aspiration_header']['mobile_nav_search'] = [
    '#type' => 'checkbox',
    '#title' => t('Mobile Nav Display Search Form'),
    '#default_value' => theme_get_setting('mobile_nav_search'),
    '#description' => t('Enable this option to display a search form in the mobile navigation menu.'),
  ];

  // Header configs for social links.
  $form['banner_slideshow'] = [
    '#type' => 'details',
    '#title' => t('Front Page Slideshow'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  $form['banner_slideshow']['slideshow_display'] = [
    '#type' => 'checkbox',
    '#title' => t('Show slideshow'),
    '#default_value' => theme_get_setting('slideshow_display'),
    '#description'   => t("Check this option to show Slideshow in front page. Uncheck to hide."),
  ];
  $form['banner_slideshow']['slide'] = [
    '#markup' => t('You can change the description and URL of each slide in the following Slide Setting fieldsets.'),
  ];

  $form['banner_slideshow']['slidecontent'] = [
    '#type' => 'container',
    '#attributes' => ['id' => 'slide-wrapper'],
  ];

  if (!$form_state->get('num_rows')) {
    $total_slide = theme_get_setting('slide_num');
    if ($total_slide) {
      $form_state->set('num_rows', $total_slide);
    }
    else {
      $form_state->set('num_rows', 2);
    }
  }
  for ($i = 1; $i <= $form_state->get('num_rows'); $i++) {
    $slide_key = 'slide' . $i;

    $form['banner_slideshow']['slidecontent'][$slide_key] = [
      '#type' => 'details',
      '#title' => t('Slide @num', ['@num' => $i]),
      '#open' => FALSE,
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_subtitle'] = [
      '#type' => 'textfield',
      '#title' => t('Slide Subtitle'),
      '#default_value' => theme_get_setting($slide_key . '_subtitle'),
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_head'] = [
      '#type' => 'textfield',
      '#title' => t('Slide Headline'),
      '#default_value' => theme_get_setting($slide_key . '_head'),
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_desc'] = [
      '#type' => 'textarea',
      '#title' => t('Slide Description'),
      '#default_value' => theme_get_setting($slide_key . '_desc'),
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_url'] = [
      '#type' => 'textfield',
      '#title' => t('Slide URL'),
      '#default_value' => theme_get_setting($slide_key . '_url'),
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_text'] = [
      '#type' => 'textfield',
      '#title' => t('Slide URL Text'),
      '#default_value' => theme_get_setting($slide_key . '_text'),
    ];

    $form['banner_slideshow']['slidecontent'][$slide_key][$slide_key . '_image'] = [
      '#type' => 'managed_file',
      '#title' => t('Slide Image'),
      '#default_value' => theme_get_setting($slide_key . '_image'),
      '#upload_location' => 'public://',
    ];

  }

  $form['banner_slideshow']['info'] = [
    '#markup' => t('You can add or remove slider content using both button.'),
  ];
  $form['banner_slideshow']['actions'] = [
    '#type' => 'actions',
  ];
  $form['banner_slideshow']['actions']['add_more'] = [
    '#type' => 'submit',
    '#value' => t('Add one more'),
    '#submit' => ['aspiration_add_one'],
    '#attributes' => [
      'class' => ['button-addmore'],
    ],
    '#ajax' => [
      'callback' => 'aspiration_add_more',
      'wrapper' => 'slide-wrapper',
    ],
  ];
  if ($form_state->get('num_rows') > 2) {
    $form['banner_slideshow']['actions']['remove_last'] = [
      '#type' => 'submit',
      '#value' => t('remove last one'),
      '#submit' => ['aspiration_remove_last_callback'],
      '#attributes' => [
        'class' => ['button-remove'],
      ],
      '#ajax' => [
        'callback' => 'aspiration_remove_last',
        'wrapper' => 'slide-wrapper',
      ],
    ];
  }
  $form['#submit'][] = 'market_wave_custom_submit_callback';

  $form['aspiration_social'] = [
    '#type' => 'details',
    '#title' => t('Social Links'),
    '#open' => TRUE,
    '#group' => 'aspiration_tabs',
  ];

  // Define the header for the table.
  $form['aspiration_social']['social_links'] = [
    '#type' => 'table',
    '#header' => [
      t('Platform'),
      t('URL'),
      t('Weight'),
    ],
    '#empty' => t('No social platforms defined.'),
    '#tree' => TRUE,
    '#tabledrag' => [[
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'social-links-weight',
    ],
    ],
  ];

  $social_media_platforms = aspiration_get_social_media_platforms();

  $saved_links = theme_get_setting('social_links') ?? [];

  $sortable = [];
  if ($social_media_platforms) {
    foreach ($social_media_platforms as $key => $label) {
      $weight = isset($saved_links[$key]['weight']) ? (int) $saved_links[$key]['weight'] : 0;
      $sortable[$key] = [
        'label' => $label,
        'url' => $saved_links[$key]['url'] ?? '',
        'weight' => $weight,
      ];
    }

    uasort($sortable, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    foreach ($sortable as $key => $item) {
      $form['aspiration_social']['social_links'][$key]['#attributes']['class'][] = 'draggable';

      $form['aspiration_social']['social_links'][$key]['platform'] = [
        '#markup' => $item['label'],
      ];

      $form['aspiration_social']['social_links'][$key]['url'] = [
        '#type' => 'textfield',
        '#title_display' => 'invisible',
        '#title' => t('@label URL', ['@label' => $item['label']]),
        '#default_value' => $item['url'],
      ];

      $form['aspiration_social']['social_links'][$key]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => $item['weight'],
        '#attributes' => ['class' => ['social-links-weight']],
      ];
    }
  }

  // Preloader Configuration Section.
  $form['aspiration_preloader'] = [
    '#type' => 'details',
    '#title' => t('Preloader'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  $form['aspiration_preloader']['show_preloader_text'] = [
    '#type' => 'checkbox',
    '#title' => t('Show preloader text'),
    '#default_value' => theme_get_setting('show_preloader_text'),
    '#description' => t('Check this box to display preloader text on page load.'),
  ];

  $form['aspiration_preloader']['custom_preloader_css'] = [
    '#type' => 'checkbox',
    '#title' => t('Use custom preloader css'),
    '#default_value' => theme_get_setting('custom_preloader_css'),
    '#description' => t('Check this box to custom preloader css.'),
    '#states' => [
      'visible' => [':input[name="show_preloader_text"]' => ['checked' => TRUE]],
    ],
  ];

  $form['aspiration_preloader']['preloader_text'] = [
    '#type' => 'textfield',
    '#title' => t('Preloader text'),
    '#default_value' => theme_get_setting('preloader_text'),
    '#description' => t('Enter the preloader text'),
    '#states' => [
      'visible' => [':input[name="show_preloader_text"]' => ['checked' => TRUE]],
    ],
  ];

  // Color picker for preloader text color.
  $form['aspiration_preloader']['aptn_preloader_txt_color'] = [
    '#type' => 'color',
    '#title' => t('Text Color'),
    '#default_value' => theme_get_setting('aptn_preloader_txt_color') ?: '#000000',
    '#description' => t('Select the color of the preloader text. Default: #000000'),
    '#states' => [
      'visible' => [
        [
          ':input[name="show_preloader_text"]' => ['checked' => TRUE],
          ':input[name="custom_preloader_css"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];

  // Color picker for text shadow color.
  $form['aspiration_preloader']['aptn_preloader_txt_stroke_color'] = [
    '#type' => 'color',
    '#title' => t('Text Stroke Color'),
    '#default_value' => theme_get_setting('aptn_preloader_txt_stroke_color') ?: '#FFFFFF4D',
    '#description' => t('Select the color of the preloader text shadow (@color). Default: #FFFFFF4D', [
      '@color' => theme_get_setting('aptn_preloader_txt_stroke_color') ?? '',
    ]),
    '#states' => [
      'visible' => [
        [
          ':input[name="show_preloader_text"]' => ['checked' => TRUE],
          ':input[name="custom_preloader_css"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];

  // Color picker for preloader background.
  $form['aspiration_preloader']['aptn_preloader_background'] = [
    '#type' => 'color',
    '#title' => t('Background Color'),
    '#default_value' => theme_get_setting('aptn_preloader_background') ?: '#000038',
    '#description' => t('Select the background color for the preloader. Default #000038'),
    '#states' => [
      'visible' => [
        [
          ':input[name="show_preloader_text"]' => ['checked' => TRUE],
          ':input[name="custom_preloader_css"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];

  // Back to Top Configuration Section.
  $form['aspiration_backtotop'] = [
    '#type' => 'details',
    '#title' => t('Back To Top Button'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  // Checkbox to enable/disable back to top button.
  $form['aspiration_backtotop']['enable_backtotop'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable Back To Top'),
    '#default_value' => theme_get_setting('enable_backtotop'),
    '#description' => t('Check this box to display the back to top button.'),
  ];

  $form['aspiration_backtotop']['custom_backtotop_css'] = [
    '#type' => 'checkbox',
    '#title' => t('Use custom back to top css'),
    '#default_value' => theme_get_setting('custom_backtotop_css'),
    '#description' => t('Check this box to custom preloader css.'),
    '#states' => [
      'visible' => [':input[name="enable_backtotop"]' => ['checked' => TRUE]],
    ],
  ];

  // Color picker for arrow icon.
  $form['aspiration_backtotop']['backtotop_arrow_color'] = [
    '#type' => 'color',
    '#title' => t('Arrow Color'),
    '#default_value' => theme_get_setting('backtotop_arrow_color') ?: '#29abe2',
    '#description' => t('Color of the arrow icon. Default: #29abe2'),
    '#states' => [
      'visible' => [
        [
          ':input[name="enable_backtotop"]' => ['checked' => TRUE],
          ':input[name="custom_backtotop_css"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];

  // Color picker for circular path.
  $form['aspiration_backtotop']['backtotop_circle_color'] = [
    '#type' => 'color',
    '#title' => t('Circle Path Color'),
    '#default_value' => theme_get_setting('backtotop_circle_color') ?: '#000038',
    '#description' => t('Color of the circular path. Default: #000038'),
    '#states' => [
      'visible' => [
        [
          ':input[name="enable_backtotop"]' => ['checked' => TRUE],
          ':input[name="custom_backtotop_css"]' => ['checked' => TRUE],
        ],
      ],
    ],
  ];

  // Footer configs.
  $form['aspiration_footer'] = [
    '#type' => 'details',
    '#title' => t('Footer'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  // Footer logo type selection.
  $form['aspiration_footer']['footer_logo_type'] = [
    '#type' => 'select',
    '#title' => t('Footer Logo Type'),
    '#default_value' => theme_get_setting('footer_logo_type'),
    '#options' => [
      'none' => t('None'),
      'default' => t('Default Site Logo'),
      'custom' => t('Custom Logo'),
    ],
    '#description' => t('Choose how you want the logo to appear in the footer. Select "None" if you prefer to have no logo at all.'),
    '#required' => TRUE,
  ];

  // Custom Footer logo fieldset.
  $form['aspiration_footer']['footer_logo_fieldset'] = [
    '#type' => 'fieldset',
    '#title' => t('Custom Footer Logo'),
    '#states' => [
      'visible' => [':input[name="footer_logo_type"]' => ['value' => 'custom']],
    ],
    '#description' => t('Upload a custom logo to be displayed in the footer. This option is available only if "Custom Logo" is selected as the logo type.'),
  ];

  // Footer logo upload.
  $form['aspiration_footer']['footer_logo_fieldset']['footer_logo'] = [
    '#type' => 'managed_file',
    '#title' => t('Footer Logo'),
    '#default_value' => theme_get_setting('footer_logo'),
    '#upload_location' => 'public://footer-logos/',
    '#description' => t('Upload your custom footer logo here. Accepted formats are JPG, JPEG, GIF, and PNG. The logo will be stored in the specified directory.'),
    '#required' => FALSE,
    '#upload_validators' => [
      'FileExtension' => ['extensions' => 'gif png jpg jpeg svg'],
    ],
  ];

  // Ensure the upload directory exists.
  $directory = 'public://footer-logos/';
  $file_system = \Drupal::service('file_system');
  $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

  // Store previous footer logo ID.
  $form['aspiration_footer']['previous_footer_logo_id'] = [
    '#type' => 'value',
    '#value' => theme_get_setting('footer_logo'),
  ];

  $form['aspiration_footer']['footer_branding_txt'] = [
    '#type' => 'text_format',
    '#title' => t('Branding text'),
    '#default_value' => theme_get_setting('footer_branding_txt')['value'] ?? '',
    '#allowed_formats' => $allowed_formats,
    '#format' => theme_get_setting('footer_branding_txt')['format'] ?? 'basic_html',
    '#description' => t('Enter the text to be displayed in the sidebar. This will appear only if a sidebar icon is selected.'),
  ];

  // Footer contact fieldset.
  $form['aspiration_footer']['footer_contact_fieldset'] = [
    '#type' => 'fieldset',
    '#title' => t('Footer Contact Information'),
    '#description' => t('Provide the details that you want to show in the footer, such as contact title, location, and business hours.'),
  ];

  // Toggle to show footer contact.
  $form['aspiration_footer']['footer_contact_fieldset']['show_footer_contact'] = [
    '#type' => 'checkbox',
    '#title' => t('Show Footer Contact Information'),
    '#default_value' => theme_get_setting('show_footer_contact'),
    '#description' => t('Check this box to display your contact information in the footer.'),
  ];

  // Footer contact details fields with visibility conditions.
  $footer_contact_fields = [
    'footer_contact_title' => t('Footer Contact Section Title'),
    'footer_location' => t('Footer Location'),
    'footer_contact' => t('Footer Contact Details'),
    'footer_aspiration_time' => t('Business Hours'),
  ];

  foreach ($footer_contact_fields as $key => $title) {
    $form['aspiration_footer']['footer_contact_fieldset'][$key] = [
      '#type' => 'textarea',
      '#title' => $title,
      '#default_value' => theme_get_setting($key),
      '#states' => [
        'visible' => [':input[name="show_footer_contact"]' => ['checked' => TRUE]],
      ],
      '#description' => t('Enter the details for this field, or leave it blank to hide this field.'),
      '#required' => FALSE,
    ];
  }

  // Footer copyright text.
  $form['aspiration_footer']['footer_copyright_text'] = [
    '#type' => 'textfield',
    '#title' => t('Footer Copyright Text'),
    '#default_value' => theme_get_setting('footer_copyright_text'),
    '#description' => t('Enter the copyright text that will appear in the footer. You can use the token [current_year] to display the current year automatically. Additionally, you can include HTML elements, such as &lt;a&gt; tags, for links.'),
    '#required' => TRUE,
  ];

  // Social media icons in footer.
  $form['aspiration_footer']['footer_social_icons'] = [
    '#type' => 'checkboxes',
    '#title' => t('Social Media Links in Footer'),
    '#options' => $social_media_options,
    '#default_value' => theme_get_setting('footer_social_icons'),
    '#description' => t('Select the social media links you want to display in the footer.'),
    '#required' => FALSE,
  ];

  // Theme settings form alter or your theme-settings.php implementation.
  $form['color_settings'] = [
    '#type' => 'details',
    '#title' => t('Theme Color Settings'),
    '#open' => FALSE,
    '#group' => 'aspiration_tabs',
  ];

  // Define the options.
  $color_options = aspiration_color_palettes();

  // Create the settings form.
  $form['color_settings']['color_theme'] = [
    '#type' => 'select',
    '#title' => t('Select Color Theme'),
    '#description' => t('Choose a color theme for your site, or select "Custom" to customize the color palette.'),
    '#default_value' => theme_get_setting('color_theme', 'aspiration'),
    '#options' => $color_options,
    '#required' => TRUE,
  ];

  // Wrapper for color fields (optional).
  $form['color_settings']['color_fields_wrapper'] = [
    '#type' => 'container',
    '#states' => [
      'visible' => [
        ':input[name="color_theme"]' => ['value' => 'custom'],
      ],
    ],
  ];

  // Load default color values.
  $default_colors = aspiration_get_default_colors();

  $count = 0;
  $row_index = 0;

  foreach ($default_colors as $var => $fallback) {
    // Create a new row every 3 items.
    if ($count % 3 === 0) {
      $row_index = $count;
      $form['color_settings']['color_fields_wrapper']["row_$row_index"] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['container-inline'],
          'style' => 'display: flex; gap: 1rem; margin-bottom: 1rem;',
        ],
      ];
    }

    // Each column container to enforce equal width.
    $form['color_settings']['color_fields_wrapper']["row_$row_index"][$var . '_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'flex: 1; max-width: 33.33%;',
      ],
    ];

    // Actual color input field.
    $form['color_settings']['color_fields_wrapper']["row_$row_index"][$var . '_wrapper'][$var] = [
      '#type' => 'color',
      '#title' => t('@label', ['@label' => str_replace('_', ' ', ucfirst($var))]),
      '#default_value' => theme_get_setting($var) ?: $fallback,
      '#description' => t('Default: <code>@default</code>', ['@default' => $fallback]),
      // Ensures the values are saved correctly.
      '#parents' => [$var],
      '#attributes' => [
        'style' => 'width: 100%; height: 40px;',
      ],
    ];

    $count++;
  }

  // Get the previously saved header sidebar text.
  $old_hdr_sidebar_txt = theme_get_setting('hdr_sidebar_txt')['value'] ?? '';
  $old_uuids = _editor_parse_file_uuids($old_hdr_sidebar_txt);

  $old_footer_branding_txt = theme_get_setting('footer_branding_txt')['value'] ?? '';
  $old_uuids_branding_txt = _editor_parse_file_uuids($old_footer_branding_txt);

  $form['old_uuids'] = [
    '#type' => 'value',
    '#value' => $old_uuids,
  ];
  $form['old_uuids_branding_txt'] = [
    '#type' => 'value',
    '#value' => $old_uuids_branding_txt,
  ];

  $old_images = [];

  for ($i = 1; $i <= $form_state->get('num_rows'); $i++) {
    $fid = theme_get_setting("slide{$i}_image");
    if (!empty($fid)) {
      $old_images["slide{$i}_image"] = $fid;
    }
  }

  $form_state->set('old_slide_images', $old_images);

  $form['#submit'][] = 'aspiration_form_system_theme_settings_submit';

}

/**
 * Submit handler for the theme settings form.
 */
function aspiration_form_system_theme_settings_submit($form, &$form_state) {

  $old_images = $form_state->get('old_slide_images') ?? [];

  for ($i = 1; $i <= $form_state->get('num_rows'); $i++) {
    $key = "slide{$i}_image";
    $new_fid = $form_state->getValue($key);
    $old_fid = $old_images[$key] ?? NULL;

    // Normalize to array.
    $new_fid = is_array($new_fid) ? $new_fid : [$new_fid];
    $old_fid = is_array($old_fid) ? $old_fid : [$old_fid];

    // Set new image to permanent.
    if (!empty($new_fid[0])) {
      $file = File::load($new_fid[0]);
      if ($file && $file->isTemporary()) {
        $file->setPermanent();
        $file->save();
        \Drupal::service('file.usage')->add($file, 'aspiration', 'user', \Drupal::currentUser()->id());
      }
    }

    // If old image removed, make it temporary.
    if (!empty($old_fid[0]) && (!isset($new_fid[0]) || $old_fid[0] != $new_fid[0])) {
      $old_file = File::load($old_fid[0]);
      if ($old_file) {
        \Drupal::service('file.usage')->delete($old_file, 'aspiration', 'user', \Drupal::currentUser()->id());
        $old_file->setTemporary();
        $old_file->save();
      }
    }
  }

  // Initialize the new file IDs array.
  $new_fids = [];

  // Get the submitted rich text value.
  $content = $form_state->getValue('hdr_sidebar_txt');
  $hdr_sidebar_txt = $content['value'] ?? '';

  if ($hdr_sidebar_txt) {
    // Parse the new UUIDs from the submitted content.
    $new_uuids = _editor_parse_file_uuids($hdr_sidebar_txt);

    if ($new_uuids) {
      // Process new file UUIDs.
      foreach ($new_uuids as $uuid) {
        if ($file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $uuid)) {
          /** @var \Drupal\file\FileInterface $file */
          if ($file->isTemporary()) {
            $file->setPermanent();
            $file->save();
          }

          $new_fids[] = $file->id();
          \Drupal::service('file.usage')->add($file, 'aspiration', 'file', $file->id());
        }
      }
    }
  }

  // Get old UUIDs from the form state or wherever you have stored them.
  $old_uuids = $form_state->getValue('old_uuids') ?? [];

  // Check if old UUIDs exist and if they differ from new UUIDs.
  if (!empty($old_uuids)) {
    // Compare old and new UUIDs. Find those that are no longer present.
    $uuids_to_remove = array_diff($old_uuids, $new_uuids);

    // Process old file UUIDs that are no longer in the new list.
    foreach ($uuids_to_remove as $uuid) {
      if ($file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $uuid)) {
        /** @var \Drupal\file\FileInterface $file */
        if ($file->isPermanent()) {
          $file->setTemporary();
          $file->save();
        }
        // Optionally, remove from file usage or handle differently.
        \Drupal::service('file.usage')->delete($file, 'aspiration', 'file', $file->id());
      }
    }
  }
  // Get old UUIDs from the form state or wherever you have stored them.
  $old_uuids_branding_txt = $form_state->getValue('old_uuids_branding_txt') ?? [];

  // Check if old UUIDs exist and if they differ from new UUIDs.
  if (!empty($old_uuids_branding_txt)) {
    // Compare old and new UUIDs. Find those that are no longer present.
    $uuids_to_remove = array_diff($old_uuids_branding_txt, $new_uuids);

    // Process old file UUIDs that are no longer in the new list.
    foreach ($uuids_to_remove as $uuid) {
      if ($file = \Drupal::service('entity.repository')->loadEntityByUuid('file', $uuid)) {
        /** @var \Drupal\file\FileInterface $file */
        if ($file->isPermanent()) {
          $file->setTemporary();
          $file->save();
        }
        // Optionally, remove from file usage or handle differently.
        \Drupal::service('file.usage')->delete($file, 'aspiration', 'file', $file->id());
      }
    }
  }

  // Get the submitted file ID.
  $new_file_id = $form_state->getValue('footer_logo');

  // Get the previous file ID from the theme settings.
  $previous_footer_logo = $form_state->getValue('previous_footer_logo_id');
  $previous_file_id = $previous_footer_logo[0] ?? '';
  $new_logo_file_id = '';

  if ($new_file_id) {
    $new_logo_file_id = $new_file_id[0];
    // If a new file is uploaded, set it to be permanent.
    $new_file = File::load($new_logo_file_id);
    if ($new_file) {
      $new_file->setPermanent();
      $new_file->save();
    }
  }

  // If there was a previous file, and it's different from the new one,
  // set it to temporary.
  if ($previous_file_id && $previous_file_id !== $new_logo_file_id) {
    $previous_file = File::load($previous_file_id);
    if ($previous_file) {
      $previous_file->setTemporary();
      $previous_file->save();
    }
  }

}

/**
 * Callback for both ajax-enabled buttons.
 *
 * Selects and returns the slide content in it.
 */
function aspiration_add_more(array &$form, FormStateInterface $form_state) {
  return $form['banner_slideshow']['slidecontent'];
}

/**
 * Submit handler for the "add-one-more" button.
 *
 * Increments the max counter and causes a rebuild.
 */
function aspiration_add_one(array &$form, FormStateInterface $form_state) {
  $number_of_slide = $form_state->get('num_rows');
  $add_button = $number_of_slide + 1;
  $form_state->set('num_rows', $add_button);
  $form_state->setRebuild();
}

/**
 * Callback for both ajax-enabled buttons.
 *
 * Selects and returns the slide content in it.
 */
function aspiration_remove_last(array &$form, FormStateInterface $form_state) {
  return $form['banner_slideshow']['slidecontent'];
}

/**
 * Submit handler for the "add-one-more" button.
 *
 * Increments the max counter and causes a rebuild.
 */
function aspiration_remove_last_callback(array &$form, FormStateInterface $form_state) {
  $config_market_wave = \Drupal::configFactory()->getEditable('market_wave.settings');
  $number_of_slide = $form_state->get('num_rows');
  $add_button = $number_of_slide - 1;

  $slide_key = 'slide' . $i;

  $form_state->set('num_rows', $add_button);

  $keys = ["{$slide_key}_head",
    "{$slide_key}_desc",
    "{$slide_key}_url",
    "{$slide_key}_text",
    "{$slide_key}_image",
  ];

  foreach ($keys as $slide_index) {
    $config_market_wave->delete($slide_index);
  }
  $form_state->setRebuild();
}

/**
 * Custom submit callback for the theme settings form.
 */
function market_wave_custom_submit_callback(&$form, FormStateInterface $form_state) {
  // Retrieve the submitted value for 'slide_num'.
  $slide_num_value = $form_state->get('num_rows');
  $form_state->setValue('slide_num', $slide_num_value);
  // Set the value using the Configuration API.
  \Drupal::configFactory()->getEditable('theme.market_wave')->set('slide_num', $slide_num_value)->save();
}

/**
 * Get all text format machine names programmatically.
 */
function aspiration_get_text_formats() {
  // Load all text formats.
  $formats = \Drupal::entityTypeManager()->getStorage('filter_format')->loadMultiple();
  
  // Initialize an array to store the machine names.
  $machine_names = [];

  // Loop through each format and get the machine name.
  foreach ($formats as $format) {
    // Add the machine name to the array.
    $machine_names[] = $format->id();
  }

  // Return the machine names.
  return $machine_names;
}
