<?php

declare(strict_types=1);

/**
 * Validates the staged Ropleon brand overlay without booting Drupal.
 *
 * Usage: php scripts/validate-brand-release.php C:/path/to/drupal
 */

$drupalRoot = rtrim((string) ($argv[1] ?? ''), '/\\');
if ($drupalRoot === '' || !is_file($drupalRoot . '/vendor/autoload.php')) {
  fwrite(STDERR, "Pass a Drupal root containing vendor/autoload.php.\n");
  exit(2);
}
require $drupalRoot . '/vendor/autoload.php';

$root = dirname(__DIR__);
$errors = [];
$checked = [
  'yaml' => 0,
  'json' => 0,
  'twig' => 0,
  'po' => 0,
  'assets' => 0,
];

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

$twig = new Twig\Environment(new Twig\Loader\ArrayLoader());
foreach (['t', 'render', 'clean_id', 'clean_class', 'without'] as $filter) {
  $twig->addFilter(new Twig\TwigFilter($filter, static fn(mixed $value): mixed => $value));
}
foreach (['path', 'url'] as $function) {
  $twig->addFunction(new Twig\TwigFunction($function, static fn(mixed $value): mixed => $value));
}

foreach ($iterator as $file) {
  if (!$file->isFile()) {
    continue;
  }
  $path = $file->getPathname();
  $extension = strtolower($file->getExtension());
  try {
    if (in_array($extension, ['yml', 'yaml'], TRUE)) {
      Drupal\Component\Serialization\Yaml::decode((string) file_get_contents($path));
      $checked['yaml']++;
    }
    elseif ($extension === 'json' || $file->getFilename() === 'site.webmanifest') {
      json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
      $checked['json']++;
    }
    elseif ($extension === 'twig') {
      $source = new Twig\Source((string) file_get_contents($path), $path);
      $twig->parse($twig->tokenize($source));
      $checked['twig']++;
    }
    elseif ($extension === 'po') {
      $reader = new Drupal\Component\Gettext\PoStreamReader();
      $reader->setLangcode(str_contains(str_replace('\\', '/', $path), '/ar/') || str_ends_with($file->getFilename(), '.ar.po') ? 'ar' : 'en');
      $reader->setURI($path);
      $reader->open();
      while ($reader->readItem()) {
        // Reading to EOF validates the complete catalog.
      }
      $checked['po']++;
    }
  }
  catch (Throwable $exception) {
    $errors[] = $path . ': ' . $exception->getMessage();
  }
}

$theme = $root . '/themes/custom/digital_platform';
$expectedAssets = [
  'assets/brand/ropleon-technologies.svg' => '9992a5d7a95d066b74d072c5059103a76dfccdbc96059c236a387bbed784cf95',
  'assets/brand/ropleon-cards.svg' => 'd934c68743426050393edae38d483c3706853bf699b45f48057d311777a3b509',
  'assets/brand/favicon.svg' => 'ef70447ababda0c40078f26ae8d9db1b7b624704f99ca5f2c5ffd35e97677aa5',
  'assets/brand/favicon.ico' => 'b4722f7bfa3e78e8f38bc5176fdb2bf09edd95b6f5656524578e83a8994905bc',
];
foreach ($expectedAssets as $relativePath => $expectedHash) {
  $path = $theme . '/' . $relativePath;
  if (!is_file($path)) {
    $errors[] = 'Missing approved runtime asset: ' . $relativePath;
    continue;
  }
  if (hash_file('sha256', $path) !== $expectedHash) {
    $errors[] = 'Approved runtime asset checksum mismatch: ' . $relativePath;
  }
  $checked['assets']++;
}

$libraries = Drupal\Component\Serialization\Yaml::decode(
  (string) file_get_contents($theme . '/digital_platform.libraries.yml'),
);
foreach ($libraries as $libraryName => $library) {
  foreach (['css', 'js'] as $assetType) {
    foreach (($library[$assetType] ?? []) as $group => $groupAssets) {
      $assets = $assetType === 'css' ? $groupAssets : [$group => $groupAssets];
      foreach ($assets as $relativePath => $_options) {
        if (is_string($relativePath) && !is_file($theme . '/' . $relativePath)) {
          $errors[] = sprintf('Library %s references missing %s asset: %s', $libraryName, $assetType, $relativePath);
        }
      }
    }
  }
}

$manifest = json_decode(
  (string) file_get_contents($theme . '/assets/brand/site.webmanifest'),
  TRUE,
  512,
  JSON_THROW_ON_ERROR,
);
foreach ($manifest['icons'] ?? [] as $icon) {
  if (empty($icon['src']) || !is_file($theme . '/assets/brand/' . $icon['src'])) {
    $errors[] = 'Web manifest references a missing icon: ' . ($icon['src'] ?? '(empty)');
  }
}

$tokens = strtolower((string) file_get_contents($theme . '/css/brand-tokens.css'));
foreach (['#00184a', '#00297e', '#007bff', '#00beff'] as $color) {
  if (!str_contains($tokens, $color)) {
    $errors[] = 'Approved production token is missing: ' . $color;
  }
}

if ($errors !== []) {
  fwrite(STDERR, "Release validation failed:\n- " . implode("\n- ", $errors) . "\n");
  exit(1);
}

printf(
  "Release validation passed: %d YAML, %d JSON/manifest, %d Twig, %d PO, and %d approved runtime assets.\n",
  $checked['yaml'],
  $checked['json'],
  $checked['twig'],
  $checked['po'],
  $checked['assets'],
);
