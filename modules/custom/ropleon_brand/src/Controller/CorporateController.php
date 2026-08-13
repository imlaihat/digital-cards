<?php

namespace Drupal\ropleon_brand\Controller;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Builds the public Ropleon corporate and product pages.
 *
 * This is intentionally a parameterless thin controller. The local Uniform
 * Server environment resolves route controllers through Drupal's class
 * resolver, while the trait and facade calls below remain lazy and cacheable.
 */
final class CorporateController {

  use StringTranslationTrait;

  /**
   * Returns the Ropleon Technologies corporate homepage.
   */
  public function home(): array {
    return $this->page(
      'ropleon_corporate_home',
      (string) $this->t('Ropleon | Enterprise Software, Integration and Digital Platforms'),
      (string) $this->t('Ropleon develops enterprise software, integration solutions, AI-enabled services, cloud platforms, and connected digital products for modern organizations.'),
      'website',
    );
  }

  /**
   * Returns the focused Ropleon products overview.
   */
  public function products(): array {
    return $this->page(
      'ropleon_products',
      (string) $this->t('Ropleon Products'),
      (string) $this->t('Ropleon develops focused digital products that solve real organizational challenges through secure, scalable, and connected design.'),
      'website',
    );
  }

  /**
   * Returns the Ropleon Cards product landing page.
   */
  public function cards(): array {
    return $this->page(
      'ropleon_cards_landing',
      (string) $this->t('Ropleon Cards | Enterprise Digital Business Card Platform'),
      (string) $this->t('Create, approve, publish, and manage professional digital identities and digital business cards for your organization with Ropleon Cards.'),
      'product',
    );
  }

  /**
   * Returns the Ropleon capabilities and services page.
   */
  public function solutions(): array {
    return $this->page(
      'ropleon_solutions',
      (string) $this->t('Ropleon Solutions'),
      (string) $this->t('Connected software, integration, cloud, AI, and digital identity solutions for complex organizations.'),
      'website',
    );
  }

  /**
   * Returns the Ropleon Technologies company page.
   */
  public function about(): array {
    return $this->page(
      'ropleon_about',
      (string) $this->t('About Ropleon Technologies'),
      (string) $this->t('Ropleon is a technology company focused on secure, scalable, and connected solutions for modern organizations.'),
      'website',
    );
  }

  /**
   * Returns the public privacy information page.
   */
  public function privacy(): array {
    $build = $this->page(
      'ropleon_legal',
      (string) $this->t('Privacy'),
      (string) $this->t('How Ropleon approaches privacy, responsible information handling, and your choices.'),
      'website',
    );
    $build['#page_type'] = 'privacy';
    return $build;
  }

  /**
   * Returns the public terms information page.
   */
  public function terms(): array {
    $build = $this->page(
      'ropleon_legal',
      (string) $this->t('Terms of Use'),
      (string) $this->t('The general terms for using Ropleon public websites and digital services.'),
      'website',
    );
    $build['#page_type'] = 'terms';
    return $build;
  }

  /**
   * Builds a cacheable, translated public page and its essential metadata.
   */
  private function page(string $theme, string $title, string $description, string $type): array {
    $config = \Drupal::config('ropleon_brand.settings');
    $brand = [
      'company_name' => (string) $config->get('company_name'),
      'company_legal_name' => (string) $config->get('company_legal_name'),
      'company_name_ar' => (string) $config->get('company_name_ar'),
      'company_tagline' => (string) $config->get('company_tagline'),
      'product_name' => (string) $config->get('product_name'),
      'product_tagline' => (string) $config->get('product_tagline'),
      'product_description' => (string) $config->get('product_description'),
      'product_branding_line' => (string) $config->get('product_branding_line'),
    ];
    $urls = [
      'home' => Url::fromRoute('<front>')->toString(),
      'products' => Url::fromRoute('ropleon_brand.products')->toString(),
      'cards' => Url::fromRoute('ropleon_brand.cards')->toString(),
      'solutions' => Url::fromRoute('ropleon_brand.solutions')->toString(),
      'about' => Url::fromRoute('ropleon_brand.about')->toString(),
      'contact' => Url::fromRoute('contact.site_page')->toString(),
      'login' => Url::fromRoute('user.login')->toString(),
      'privacy' => Url::fromRoute('ropleon_brand.privacy')->toString(),
      'terms' => Url::fromRoute('ropleon_brand.terms')->toString(),
    ];
    $assets = [
      'company_logo' => $this->brandAssetUrl('ropleon-technologies.svg'),
      'product_logo' => $this->brandAssetUrl('ropleon-cards.svg'),
      'favicon' => $this->brandAssetUrl('favicon.svg'),
    ];
    $canonical_route = $theme === 'ropleon_corporate_home'
      ? '<front>'
      : (string) \Drupal::routeMatch()->getRouteName();
    $canonical = Url::fromRoute($canonical_route, [], ['absolute' => TRUE])->toString();
    $structured_data = $type === 'product'
      ? [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $brand['product_name'],
        'description' => $brand['product_description'],
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'url' => $canonical,
        'brand' => [
          '@type' => 'Brand',
          'name' => $brand['company_name'],
        ],
      ]
      : [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $brand['company_legal_name'],
        'alternateName' => [$brand['company_name'], $brand['company_name_ar']],
        'url' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(),
        'slogan' => $brand['company_tagline'],
      ];

    return [
      '#theme' => $theme,
      '#brand' => $brand,
      '#urls' => $urls,
      '#ropleon_assets' => $assets,
      '#attached' => [
        'library' => ['digital_platform/ropleon-public'],
        'html_head' => [
          [[
            '#tag' => 'meta',
            '#attributes' => ['name' => 'description', 'content' => $description],
          ], 'ropleon_description'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['property' => 'og:title', 'content' => $title],
          ], 'ropleon_og_title'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['property' => 'og:description', 'content' => $description],
          ], 'ropleon_og_description'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['property' => 'og:type', 'content' => $type],
          ], 'ropleon_og_type'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['property' => 'og:site_name', 'content' => $brand['company_legal_name']],
          ], 'ropleon_og_site_name'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['property' => 'og:url', 'content' => $canonical],
          ], 'ropleon_og_url'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['name' => 'twitter:card', 'content' => 'summary_large_image'],
          ], 'ropleon_twitter_card'],
          [[
            '#tag' => 'meta',
            '#attributes' => ['name' => 'theme-color', 'content' => '#00184A'],
          ], 'ropleon_theme_color'],
          [[
            '#tag' => 'script',
            '#attributes' => ['type' => 'application/ld+json'],
            '#value' => json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
          ], 'ropleon_structured_data'],
        ],
        'html_head_link' => [
          [[
            'rel' => 'canonical',
            'href' => $canonical,
          ], TRUE],
        ],
      ],
      '#cache' => [
        'contexts' => ['languages:language_interface', 'url.site'],
        'tags' => ['config:ropleon_brand.settings', 'config:system.site'],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds a cache-safe URL for an approved Ropleon brand asset.
   */
  private function brandAssetUrl(string $filename): string {
    $theme_path = \Drupal::service('extension.list.theme')->getPath('digital_platform');
    $relative_path = $theme_path . '/assets/brand/' . ltrim($filename, '/');
    $absolute_path = DRUPAL_ROOT . '/' . $relative_path;
    $version = is_file($absolute_path) ? (string) filemtime($absolute_path) : '1';

    return base_path() . $relative_path . '?v=' . rawurlencode($version);
  }

}
