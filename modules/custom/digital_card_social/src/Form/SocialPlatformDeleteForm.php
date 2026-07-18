<?php

namespace Drupal\digital_card_social\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\digital_card_social\Service\SocialPlatformRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Safely deletes an unused social platform definition.
 */
final class SocialPlatformDeleteForm extends ConfirmFormBase {

  private string $platformId = '';

  private array $platform = [];

  public function __construct(
    private readonly SocialPlatformRegistry $registry,
    private readonly LoggerInterface $logger,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('digital_card_social.registry'),
      $container->get('logger.channel.digital_card_social'),
    );
  }

  public function getFormId(): string {
    return 'digital_card_social_platform_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $platform = NULL): array {
    $this->platformId = (string) $platform;
    $this->platform = $this->registry->get($this->platformId) ?? [];
    if (!$this->platform) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $usage = $this->registry->usageCount($this->platformId);
    if ($usage > 0) {
      $form['usage_warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => ['#markup' => $this->t('This platform is referenced @count times in card content or revisions. Disable it instead of deleting it.', ['@count' => $usage])],
      ];
      $form['#platform_in_use'] = TRUE;
    }
    $form = parent::buildForm($form, $form_state);
    if ($usage > 0) {
      $form['actions']['submit']['#disabled'] = TRUE;
      $form['actions']['submit']['#access'] = FALSE;
    }
    $form['#attached']['library'][] = 'digital_card_social/admin';
    return $form;
  }

  public function getQuestion(): string {
    return (string) $this->t('Delete @platform?', ['@platform' => $this->platform['label'] ?? $this->platformId]);
  }

  public function getDescription(): string {
    return (string) $this->t('This action cannot be undone. Disable a platform when you only want to prevent new selections.');
  }

  public function getCancelUrl(): Url {
    return Url::fromRoute('digital_card_social.platforms');
  }

  public function getConfirmText(): string {
    return (string) $this->t('Delete platform');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->registry->usageCount($this->platformId) > 0) {
      $this->messenger()->addError($this->t('The platform is still used by card content and was not deleted.'));
      $form_state->setRedirect('digital_card_social.platforms');
      return;
    }
    try {
      $this->registry->delete($this->platformId);
      $this->messenger()->addStatus($this->t('The social platform @platform was deleted.', ['@platform' => $this->platform['label']]));
      $this->logger->notice('Social platform @id was deleted by user @uid.', ['@id' => $this->platformId, '@uid' => $this->currentUser()->id()]);
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('The social platform could not be deleted.'));
      $this->logger->error('Social platform @id could not be deleted: @message', ['@id' => $this->platformId, '@message' => $e->getMessage()]);
    }
    $form_state->setRedirect('digital_card_social.platforms');
  }

}

