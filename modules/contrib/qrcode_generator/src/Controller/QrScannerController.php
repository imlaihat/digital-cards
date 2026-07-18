<?php

namespace Drupal\qrcode_generator\Controller;

use Drupal\Core\Controller\ControllerBase;


/**
 * Controller class for handling QR code scanning.
 */
class QrScannerController extends ControllerBase {

  /**
   * Handles the qr scan logic.
   */
  public function scanPage() {
    return [
      '#theme' => 'scanqrpage',
    ];
  }

}
