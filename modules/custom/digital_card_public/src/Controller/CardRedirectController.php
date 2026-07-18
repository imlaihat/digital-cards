<?php

namespace Drupal\digital_card_public\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\digital_card_delivery\Service\OrganizationCardContext;
use Drupal\digital_card_public\Service\CardLookup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a permanent NFC identifier to the current organization card path.
 */
final class CardRedirectController implements ContainerInjectionInterface {

  public function __construct(
    private readonly CardLookup $cardLookup,
    private readonly OrganizationCardContext $organizationContext,
    private readonly LoggerChannelInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('digital_card_public.card_lookup'),
      $container->get('digital_card_delivery.organization_context'),
      $container->get('logger.channel.digital_card_public'),
    );
  }

  public function redirect(string $nfc_id): RedirectResponse {
    $card = $this->cardLookup->loadApprovedByNfc($nfc_id);
    if (!$card) {
      throw new NotFoundHttpException('This digital card is unavailable.');
    }
    try {
      $organization = $this->organizationContext->fromCard($card);
      $relativePath = 'cards/' . $organization['directory'] . '/' . $nfc_id . '/index.html';
      // The subscription maintenance process pauses a card by deleting its
      // static output. Never redirect to a missing file even if workflow data
      // has not yet been reconciled.
      if (!is_file(DRUPAL_ROOT . '/' . $relativePath)) {
        throw new NotFoundHttpException('This digital card is temporarily unavailable.');
      }
      $basePath = base_path();
      $target = $basePath . 'cards/' . rawurlencode($organization['directory']) . '/' . rawurlencode($nfc_id) . '/index.html';
      $response = new RedirectResponse($target, 302);
      $response->headers->set('Cache-Control', 'no-store, max-age=0');
      $response->headers->set('X-Content-Type-Options', 'nosniff');
      return $response;
    }
    catch (NotFoundHttpException $exception) {
      throw $exception;
    }
    catch (\Throwable $exception) {
      $this->logger->error('NFC redirect failed for card @card: @message', [
        '@card' => $card->id(),
        '@message' => $exception->getMessage(),
      ]);
      throw new NotFoundHttpException('This digital card is unavailable.', $exception);
    }
  }

}
