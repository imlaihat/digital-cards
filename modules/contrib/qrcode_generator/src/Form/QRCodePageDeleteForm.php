<?php

namespace Drupal\qrcode_generator\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form for deleting QR page override item list.
 */
class QRCodePageDeleteForm extends ConfirmFormBase {

  /**
   * Delete id paramter value.
   *
   * @var id paramter
   *   id parameter
   */
  private $id;

  /**
   * The database object to handle db operation.
   *
   * @var \Drupal\Core\Database\Connection
   *   Database connection object.
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Class constructor.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qrcode_page_override_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure want to delete this record?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('qrcode_page_config.page_qr_list');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $this->id = $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * Perform delete Form Submission.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pid = $this->id;
    if (is_numeric($pid)) {
      $this->deleteConfigValue($pid);
      $delete = $this->database->delete('qrcode_generator_settings')->condition('pid', $pid)->execute();
      if ($delete) {
        $this->messenger()->addMessage($this->t('Record deleted successfully.'));
      }
    }
  }

  /**
   * Helper function to clear the configuration key value based on pid.
   *
   * @param int $pid
   *   Pid to match the deletion paramter.
   */
  public function deleteConfigValue($pid) {
    $fetch = $this->database->select("qrcode_generator_settings", "a");
    $fetch->fields('a', ['pageurl']);
    $fetch->condition('a.pid', $pid);
    $fetch_results = $fetch->execute()->fetchAssoc();
    $parent = $fetch_results['pageurl'];
    $parent_key = str_replace('/', '::', $parent);
    \Drupal::service('config.factory')->getEditable('qrcode_page_config.settings')
      ->clear($parent_key)->save();
  }

}
