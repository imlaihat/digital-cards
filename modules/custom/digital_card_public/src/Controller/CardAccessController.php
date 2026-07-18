<?php

namespace Drupal\digital_card_public\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\digital_card_public\Service\CardLookup;
use Drupal\digital_card_public\Service\ScannerContextResolver;
use Drupal\digital_card_public\Service\ScanLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

final class CardAccessController implements ContainerInjectionInterface {

  public function __construct(
    private readonly CardLookup $cardLookup,
    private readonly ScannerContextResolver $scannerContext,
    private readonly FloodInterface $flood,
    private readonly RequestStack $requestStack,
    private readonly LoggerChannelInterface $logger,
    private readonly ScanLogger $scanLogger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('digital_card_public.card_lookup'),
      $container->get('digital_card_public.scanner_context'),
      $container->get('flood'),
      $container->get('request_stack'),
      $container->get('logger.channel.digital_card_public'),
      $container->get('digital_card_public.scan_logger'),
    );
  }

  public function access(string $nfc_id): JsonResponse {
    $request = $this->requestStack->getCurrentRequest();
    $identifier = (string) ($request?->getClientIp() ?: 'unknown');
    if (!$this->flood->isAllowed('digital_card_public.scan', 120, 60, $identifier)) {
      $this->logger->warning('Scanner API rate limit exceeded for @ip.', ['@ip' => $identifier]);
      return $this->response(['available' => FALSE, 'message' => 'Too many requests.'], 429);
    }
    $this->flood->register('digital_card_public.scan', 60, $identifier);
    $card = $this->cardLookup->loadApprovedByNfc($nfc_id);
    if (!$card) {
      return $this->response([
        'available' => FALSE,
        'logged_in' => FALSE,
        'is_owner' => FALSE,
        'scanner_type' => 'unknown',
        'capabilities' => [
          'view_public_card' => FALSE,
          'check_offer_eligibility' => FALSE,
          'redeem_offer' => FALSE,
        ],
      ], 404);
    }
    $context = $this->scannerContext->resolve($card);
    if ($request) {
      $this->scanLogger->record($card, $context, $request);
    }
    return $this->response(['available' => TRUE] + $context);
  }

  private function response(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Vary', 'Cookie');
    return $response;
  }
}
