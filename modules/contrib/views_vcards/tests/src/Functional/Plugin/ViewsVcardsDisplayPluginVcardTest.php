<?php

namespace Drupal\Tests\views_vcards\Functional\Plugin;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use Drupal\views_vcards\Plugin\views\display\ViewsVcardsDisplayPluginVcard;

/**
 * Tests the vCard display plugin.
 *
 * @group views
 * @see \Drupal\views_vcards\Plugin\views\display\ViewsVcardsDisplayPluginVcard
 *
 * @see \Drupal\Tests\views\Functional\Plugin\DisplayFeedTest
 */
class ViewsVcardsDisplayPluginVcardTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'text',
    'image',
    'user',
    'block',
    'views',
    'views_vcards',
    'views_vcards_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_views_vcards_display'];

  /**
   * List of personal names around the world.
   *
   * Source: https://www.w3.org/International/questions/qa-personal-names
   * Transformed into first name, middle name, last name.
   *
   * @var array
   */
  protected $testNames = [
    ['Björk', '', 'Guðmundsdóttir'],
    ['Isa', '', 'bin Osman'],
    ['毛', '泽', '东'],
    ['María José', 'Carreño', 'Quiñones'],
    ['Борис', 'Николаевич', 'Ельцин'],
    ['Luke', '', 'Skywalker'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(
    $import_test_views = TRUE,
    $modules = [
      'views_vcards_test',
    ],
  ): void {
    // Do not yet import test views here, but do prepare the test setup.
    parent::setUp(FALSE, $modules);

    // Create 3 fields for the name parts.
    $fields = ['field_first', 'field_middle', 'field_last'];
    foreach ($fields as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'user',
        'module' => 'core',
        'type' => 'string',
        'cardinality' => 1,
        'locked' => FALSE,
        'indexes' => [],
        'settings' => [
          'max_length' => 255,
          'is_ascii' => FALSE,
          'case_sensitive' => FALSE,
        ],
      ])->save();

      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'user',
        'bundle' => 'user',
        'label' => $field_name,
        'description' => '',
        'required' => FALSE,
        'settings' => [],
      ])->save();
    }

    // Install the test views after the fields, so dependencies are in place.
    if ($import_test_views) {
      ViewTestData::createTestViews(static::class, $modules);
    }

    $user = $this->drupalCreateUser([
      'access user profiles',
      'view user email addresses',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests the single vCard output.
   */
  public function testSingleVcardOutput() {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliteration */
    $transliteration = \Drupal::service('transliteration');

    // Pick a random name from the list and create the account.
    $key = array_rand($this->testNames);
    $name_parts = $this->testNames[$key];
    [$first, $middle, $last] = $name_parts;
    $test_user = $this->drupalCreateUser([], NULL, NULL, [
      'field_first' => $first,
      'field_middle' => $middle,
      'field_last' => $last,
    ]);

    // Construct a full name (first + middle + last). This is not perfect as it
    // gives a double space when no middle name, but it matches what we do in
    // the test view configuration as well.
    $full_name = implode(' ', $name_parts);

    // vCards should get the full name field's value.
    $filename = $transliteration->transliterate($full_name, $langcode) . '.vcf';
    // The path as defined in the view configuration.
    $path = "userlist/download";

    $expected_contents = $this->getExpectedVcardContent($first, $middle, $last, $full_name, $test_user->getEmail());

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/vcard; charset=UTF-8');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="' . $filename . '"');
    // Test that the file transferred correctly.
    $this->assertSame($expected_contents, $this->getSession()->getPage()->getContent(), 'Contents of the vCard are correct.');

    // Create a second user, then apply the views filter to test attach to.
    $this->drupalCreateUser([], NULL, NULL, [
      'field_first' => $this->randomString(),
      'field_middle' => $this->randomString(),
      'field_last' => $this->randomString(),
    ]);
    $this->drupalGet('userlist');
    $this->submitForm(['uid' => "{$test_user->getAccountName()} ({$test_user->id()})"], 'Apply');

    // Test if the vCard icon is attached to the page.
    $icon_href = $this->cssSelect('a.vcard-icon[href *= "userlist"]')[0]->getAttribute('href');
    $this->assertStringContainsString($path, $icon_href, 'The vCard icon was found.');
    // Test that after filter the response is still the single user vCard now.
    $this->drupalGet($icon_href);
    $this->assertSame($expected_contents, $this->getSession()->getPage()->getContent(), 'Contents of the vCard are correct.');

    // Add a block display and attach the vCard display.
    $view = Views::getView('test_views_vcards_display');
    $view->newDisplay('block', 'Block', 'block_1');
    $view->save();
    // Add a block (default without attach to)
    $this->drupalPlaceBlock('views_block:test_views_vcards_display-block_1');
    $this->drupalGet('<front>');
    // Assert the view is not yet attached to the block.
    $this->assertSession()->elementNotExists('css', 'a.vcard-icon[href *= "userlist"]');

    // Change the vCard display and attach to the block.
    $view->setDisplay('views_vcard_1');
    $view->getDisplay()->setOption('displays', ['block_1' => 'block_1']);
    $view->save();

    // Assert the attached view now works.
    $this->drupalGet('<front>');
    $this->assertSession()->elementAttributeContains('css', 'a.vcard-icon[href *= "userlist"]', 'href', $path);

    // Now without the filter two vCards should be zipped.
    $this->drupalGet($path);
    // The filename is the title of the view.
    $filename = $view->getTitle() . '.zip';
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/zip; charset=UTF-8');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="' . $filename . '"');
  }

  /**
   * Tests the zipped output.
   */
  public function testMultipleVcardOutput() {
    $test_users = [];

    // Create multiple users.
    foreach ($this->testNames as $name_parts) {
      [$first, $middle, $last] = $name_parts;
      $test_users[] = $this->drupalCreateUser([], NULL, NULL, [
        'field_first' => $first,
        'field_middle' => $middle,
        'field_last' => $last,
      ]);
    }

    // Create 99 more of the last user. Together with the previous block this
    // makes 100 users of the same name.
    [$first, $middle, $last] = end($this->testNames);
    for ($x = 1; $x < 100; $x++) {
      $test_users[] = $this->drupalCreateUser([], NULL, NULL, [
        'field_first' => $first,
        'field_middle' => $middle,
        'field_last' => $last,
      ]);
    }

    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    /** @var \Drupal\Component\Transliteration\TransliterationInterface $transliteration */
    $transliteration = \Drupal::service('transliteration');

    // Get the executable view.
    $view = Views::getView('test_views_vcards_display');

    // The path as defined in the view configuration.
    $path = "userlist/download";
    // The filename is the title of the view.
    $filename = $view->getTitle() . '.zip';

    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'application/zip; charset=UTF-8');
    $this->assertSession()->responseHeaderEquals('Content-Disposition', 'attachment; filename="' . $filename . '"');

    // Because the stream is sent in chucks, we cannot obtain the full sent
    // contents. So instead generate the response manually from the display.
    ob_start();
    $response = ViewsVcardsDisplayPluginVcard::buildResponse('test_views_vcards_display', 'views_vcard_1');
    $response->sendContent();
    $zip_content = ob_get_clean();

    // Save the zip as a temporary file and check its contents.
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $local_path = $file_system->saveData($zip_content, 'temporary://');
    // ZipArchive is incompatible with stream wrappers, so resolve local path.
    $local_path = $file_system->realpath($local_path);

    $zip = new \ZipArchive();
    $open_status = $zip->open($local_path);
    $this->assertTrue($open_status, 'Zip was opened correctly. Anything other than TRUE indicates an error.');

    // Check if the zip contains all test names and 100 of the last user.
    $this->assertEquals(count($this->testNames) + 99, $zip->numFiles, 'Number of files in the zip matches with created accounts.');
    $zipped_files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $zipped_files[] = $zip->getNameIndex($i);
    }
    $first_user_filename = $zipped_files[0];
    $first_user_vcard = $zip->getFromName($first_user_filename);
    $last_user_filename = end($zipped_files);
    $last_user_vcard = $zip->getFromName($last_user_filename);
    $zip->close();

    // Test the first user.
    [$first, $middle, $last] = $this->testNames[0];
    $full_name = implode(' ', $this->testNames[0]);
    $expected_name = $transliteration->transliterate($full_name, $langcode) . '.vcf';
    $this->assertEquals($expected_name, $first_user_filename, 'Filename is correct.');
    $expected_contents = $this->getExpectedVcardContent($first, $middle, $last, $full_name, $test_users[0]->getEmail());
    // The zipping library adds a newline at the file end, which we exclude from
    // testing by using rtrim.
    $this->assertEquals($expected_contents, rtrim($first_user_vcard), 'The vCard content is correct.');

    // Test the last user. Should be suffixed with _100.
    [$first, $middle, $last] = end($this->testNames);
    $full_name = implode(' ', end($this->testNames));
    $expected_name = $transliteration->transliterate($full_name, $langcode) . '_100.vcf';
    $this->assertEquals($expected_name, $last_user_filename, 'Filename is correct.');
    $expected_contents = $this->getExpectedVcardContent($first, $middle, $last, $full_name, end($test_users)->getEmail());
    // The zipping library adds a newline at the file end, which we exclude from
    // testing by using rtrim.
    $this->assertEquals($expected_contents, rtrim($last_user_vcard), 'The vCard content is correct.');
  }

  /**
   * Helper function for constructing the expected contents of a vCard.
   *
   * @param string $first
   *   The first name.
   * @param string $middle
   *   The middle name.
   * @param string $last
   *   The last name.
   * @param string $full
   *   The full name.
   * @param string $email
   *   The email address.
   *
   * @return string
   *   The composed vCard.
   */
  protected function getExpectedVcardContent($first, $middle, $last, $full, $email) {
    return "BEGIN:VCARD
VERSION:4.0
FN:{$full}
N:{$last};{$first};{$middle};;
EMAIL:{$email}
END:VCARD";
  }

}
