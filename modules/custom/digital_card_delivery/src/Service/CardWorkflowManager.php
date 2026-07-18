<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\digital_card_enforcement\Service\CardLimitChecker;
use Drupal\node\NodeInterface;

/**
 * Applies static delivery rules when card workflow status changes.
 */
class CardWorkflowManager {

  public const CARD_TYPE = 'digital_business_card';
  public const STATUS_APPROVED = 'card_workflow_approved';

  protected CardStaticGenerator $generator;
  protected CardLimitChecker $checker;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected MessengerInterface $messenger;

  public function __construct(
    CardStaticGenerator $generator,
    CardLimitChecker $checker,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger
  ) {
    $this->generator = $generator;
    $this->checker = $checker;
    $this->loggerFactory = $logger_factory;
    $this->messenger = $messenger;
  }

  /**
   * Processes one card after save and shows/logs clear results.
   */
  public function process(NodeInterface $node, string $source = 'workflow change', bool $interactive = TRUE): array {
    $result = [
      'success' => TRUE,
      'action' => 'workflow process',
      'messages' => [],
    ];

    if ($node->bundle() !== self::CARD_TYPE) {
      return $result;
    }

    $status_field = $this->getStatusFieldName();
    if (!$status_field || !$node->hasField($status_field)) {
      $result['success'] = FALSE;
      $result['messages'][] = 'Static delivery skipped: workflow status field was not found on this card.';
      $this->logResult($node, $result, 'warning');
      $this->notify($result, $interactive);
      return $result;
    }

    $status = (string) ($node->get($status_field)->value ?? '');

    if ($status === self::STATUS_APPROVED) {
      return $this->processApprovedCard($node, $source, $interactive);
    }

    $delete_result = $this->generator->delete($node, 'card status is ' . ($status ?: 'empty') . '; source: ' . $source);
    $result['success'] = !empty($delete_result['success']);
    $result['messages'] = $delete_result['messages'];

    if (!empty($delete_result['success'])) {
      $result['messages'][] = 'Card is not approved, so static public card is paused/deleted.';
      $this->logResult($node, $result, 'notice');
    }
    else {
      $result['messages'][] = 'Card is not approved and static public card could not be deleted.';
      $this->logResult($node, $result, 'warning');
    }

    $this->notify($result, $interactive);
    return $result;
  }

  protected function processApprovedCard(NodeInterface $node, string $source, bool $interactive): array {
    $check = $this->runChecker($node, 'static generation after approval; source: ' . $source);
    $this->logCheckerResult($node, $check);

    if (empty($check['allowed'])) {
      $delete_result = $this->generator->delete($node, 'approval check failed; static card must not be public');

      $result = [
        'success' => FALSE,
        'action' => 'approve/generate',
        'messages' => array_merge(
          ['Card is approved in workflow, but static generation is blocked. Static files were deleted if they existed.'],
          $check['messages'] ?? [],
          $delete_result['messages'] ?? []
        ),
      ];

      $this->logResult($node, $result, 'warning');
      $this->notify($result, $interactive);
      return $result;
    }

    $generate_result = $this->generator->generate($node, 'approval check passed; source: ' . $source);

    $result = [
      'success' => !empty($generate_result['success']),
      'action' => 'approve/generate',
      'messages' => array_merge(
        ['Card approval checks passed.'],
        $check['messages'] ?? [],
        $generate_result['messages'] ?? []
      ),
    ];

    $this->logResult($node, $result, !empty($result['success']) ? 'notice' : 'error');
    $this->notify($result, $interactive);
    return $result;
  }

  protected function runChecker(NodeInterface $node, string $operation): array {
    if (method_exists($this->checker, 'checkCard')) {
      return $this->checker->checkCard($node, $operation);
    }

    // Backward compatibility with older checker implementation.
    if (method_exists($this->checker, 'canCreateCard')) {
      $allowed = (bool) $this->checker->canCreateCard($node);
      return [
        'allowed' => $allowed,
        'operation' => $operation,
        'messages' => [
          $allowed ? 'Legacy card checker passed.' : 'Legacy card checker failed. Subscription or card limit may be invalid.',
        ],
      ];
    }

    return [
      'allowed' => FALSE,
      'operation' => $operation,
      'messages' => ['Card checker service does not provide checkCard() or canCreateCard().'],
    ];
  }

  protected function getStatusFieldName(): ?string {
    if (method_exists($this->checker, 'getCardStatusFieldName')) {
      return $this->checker->getCardStatusFieldName();
    }
    return 'field_status';
  }

  protected function logCheckerResult(NodeInterface $node, array $check): void {
    if (method_exists($this->checker, 'logResult')) {
      $this->checker->logResult($node, $check);
    }
  }

  protected function notify(array $result, bool $interactive): void {
    if (!$interactive) {
      return;
    }

    $messages = $result['messages'] ?? [];
    if (empty($messages)) {
      return;
    }

    if (!empty($result['success'])) {
      foreach ($messages as $message) {
        $this->messenger->addStatus($message);
      }
      return;
    }

    $this->messenger->addError(array_shift($messages));
    foreach ($messages as $message) {
      $this->messenger->addWarning($message);
    }
  }

  protected function logResult(NodeInterface $node, array $result, string $severity): void {
    $context = [
      '@node' => $node->id() ?: 'new',
      '@title' => $node->label(),
      '@action' => $result['action'] ?? 'unknown',
      '@messages' => implode(' | ', $result['messages'] ?? []),
    ];

    $message = 'Card workflow delivery @action for node @node (@title): @messages';
    $channel = $this->loggerFactory->get('digital_card_delivery');

    match ($severity) {
      'error' => $channel->error($message, $context),
      'warning' => $channel->warning($message, $context),
      default => $channel->notice($message, $context),
    };
  }

}
