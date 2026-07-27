<?php

/**
 * Validates staged YAML, Twig, and PO files against a Drupal installation.
 *
 * Usage:
 * php scripts/validate-release.php C:/path/to/drupal
 */

$drupalRoot = $argv[1] ?? '';
if ($drupalRoot === '' || !is_file($drupalRoot . '/vendor/autoload.php')) {
  fwrite(STDERR, "Pass a Drupal root containing vendor/autoload.php.\n");
  exit(2);
}
require $drupalRoot . '/vendor/autoload.php';

$releaseRoot = dirname(__DIR__);
$yamlCount = 0;
$twigCount = 0;
$poCount = 0;
$svgCount = 0;
$iconCount = 0;

$twig = new Twig\Environment(new Twig\Loader\ArrayLoader());
foreach (['t', 'render', 'clean_id', 'clean_class', 'without'] as $filter) {
  $twig->addFilter(new Twig\TwigFilter($filter, static fn($value) => $value));
}
foreach (['path', 'url', 'link'] as $function) {
  $twig->addFunction(new Twig\TwigFunction($function, static fn($value) => $value));
}

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($releaseRoot, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
  if (!$file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
    continue;
  }

  $extension = strtolower($file->getExtension());
  if ($extension === 'yml') {
    Drupal\Component\Serialization\Yaml::decode((string) file_get_contents($file->getPathname()));
    $yamlCount++;
  }
  elseif ($extension === 'twig') {
    $source = new Twig\Source((string) file_get_contents($file->getPathname()), $file->getPathname());
    $twig->parse($twig->tokenize($source));
    $twigCount++;
  }
  elseif ($extension === 'po') {
    $reader = new Drupal\Component\Gettext\PoStreamReader();
    $reader->setLangcode('ar');
    $reader->setURI($file->getPathname());
    $reader->open();
    while ($reader->readItem()) {
      // Reading every item validates the catalog structure.
    }
    $poCount++;
  }
  elseif ($extension === 'svg') {
    $document = new DOMDocument();
    $loaded = $document->loadXML(
      (string) file_get_contents($file->getPathname()),
      LIBXML_NONET | LIBXML_NOBLANKS,
    );
    if (!$loaded || $document->documentElement?->localName !== 'svg') {
      throw new RuntimeException('Invalid SVG asset: ' . $file->getPathname());
    }
    $svgCount++;
  }
  elseif ($extension === 'png' && str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR)) {
    if (getimagesize($file->getPathname()) === FALSE) {
      throw new RuntimeException('Invalid brand icon: ' . $file->getPathname());
    }
    $iconCount++;
  }
}

printf(
  "Validated %d YAML files, %d Twig templates, %d PO catalogs, %d SVG assets, and %d raster icons.\n",
  $yamlCount,
  $twigCount,
  $poCount,
  $svgCount,
  $iconCount,
);
