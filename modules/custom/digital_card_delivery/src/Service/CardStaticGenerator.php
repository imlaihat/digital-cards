<?php

namespace Drupal\digital_card_delivery\Service;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\digital_card_social\Service\SocialPlatformRegistry;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CardStaticGenerator {

  public function __construct(
    private readonly OrganizationCardContext $organizationContext,
    private readonly FileSystemInterface $fileSystem,
    private readonly LockBackendInterface $lock,
    private readonly LoggerChannelInterface $logger,
    private readonly RequestStack $requestStack,
    private readonly ?SocialPlatformRegistry $socialPlatforms = NULL,
  ) {}

  public function generate(NodeInterface $card): void {
    $nfc = $this->nfc($card);
    $context = $this->organizationContext->fromCard($card);
    $lock = 'digital_card_delivery:' . $card->id();
    if (!$this->lock->acquire($lock, 20.0)) {
      $this->logger->warning('Generation skipped for card @id: another process owns the lock.', ['@id' => $card->id()]);
      return;
    }
    try {
      $directory = DRUPAL_ROOT . '/cards/' . $context['directory'] . '/' . $nfc;
      $assetsDirectory = DRUPAL_ROOT . '/cards/' . $context['directory'] . '/assets';
      $sharedAssetsDirectory = DRUPAL_ROOT . '/cards/_assets';
      // The physical /c/{nfc}/ directory is the primary scan path. Apache or
      // Nginx serves it directly without bootstrapping Drupal, making even a
      // cold/first scan effectively immediate.
      $stableDirectory = DRUPAL_ROOT . '/c/' . $nfc;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->prepareDirectory($assetsDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->prepareDirectory($sharedAssetsDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->prepareDirectory($stableDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $css = $this->buildOrganizationCss($context);
      $vcard = $this->buildVcard($card, $context);
      $this->atomicWrite($assetsDirectory . '/card.css', $css);
      $fontSource = dirname(__DIR__, 2) . '/assets/fonts/NotoSansArabic-Variable.ttf';
      if (is_file($fontSource) && !is_file($sharedAssetsDirectory . '/NotoSansArabic-Variable.ttf')) {
        $this->atomicWrite($sharedAssetsDirectory . '/NotoSansArabic-Variable.ttf', (string) file_get_contents($fontSource));
      }
      $this->atomicWrite($directory . '/index.html', $this->buildHtml($card, $context, $nfc, '../assets/card.css'));
      $this->atomicWrite($directory . '/contact.vcf', $vcard);
      // Keep a self-contained stable copy so it has no redirect and no
      // dependency on organization directory/slug resolution at request time.
      $this->atomicWrite($stableDirectory . '/card.css', $css);
      $this->atomicWrite($stableDirectory . '/index.html', $this->buildHtml($card, $context, $nfc, './card.css'));
      $this->atomicWrite($stableDirectory . '/contact.vcf', $vcard);
      $this->logger->notice('Generated card @card at @path for organization @org.', ['@card' => $card->id(), '@path' => $directory, '@org' => $context['id']]);
    }
    catch (\Throwable $e) {
      $this->logger->error('Static generation failed for card @card: @reason', ['@card' => $card->id(), '@reason' => $e->getMessage()]);
      throw $e;
    }
    finally {
      $this->lock->release($lock);
    }
  }

  public function delete(NodeInterface $card): void {
    try {
      $nfc = $this->nfc($card);
      $context = $this->organizationContext->fromCard($card);
      $directory = DRUPAL_ROOT . '/cards/' . $context['directory'] . '/' . $nfc;
      if (is_dir($directory)) {
        $this->fileSystem->deleteRecursive($directory);
        $this->logger->notice('Deleted static output for card @card at @path.', ['@card' => $card->id(), '@path' => $directory]);
      }
      $stableDirectory = DRUPAL_ROOT . '/c/' . $nfc;
      if (is_dir($stableDirectory)) {
        $this->fileSystem->deleteRecursive($stableDirectory);
        $this->logger->notice('Deleted stable static output for card @card at @path.', ['@card' => $card->id(), '@path' => $stableDirectory]);
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('Could not delete static output for card @card: @reason', ['@card' => $card->id(), '@reason' => $e->getMessage()]);
    }
  }

  private function nfc(NodeInterface $card): string {
    $value = $card->hasField('field_nfc_id') ? trim((string) $card->get('field_nfc_id')->value) : '';
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]{3,128}$/', $value)) {
      throw new \RuntimeException('The card NFC identifier is missing or unsafe.');
    }
    return $value;
  }

  private function value(NodeInterface $card, string $field): string {
    return $card->hasField($field) ? trim((string) $card->get($field)->value) : '';
  }

  private function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private function buildHtml(NodeInterface $card, array $org, string $nfc, string $cssHref): string {
    $override = $this->value($card, 'field_card_language');
    $mode = in_array($override, ['en', 'ar', 'bilingual'], TRUE) ? $override : ($org['card_language'] ?? 'en');
    $englishCard = $card->hasTranslation('en') ? $card->getTranslation('en') : $card;
    $arabicCard = $card->hasTranslation('ar') ? $card->getTranslation('ar') : $card;
    $displayCard = $mode === 'ar' ? $arabicCard : $englishCard;
    $name = $this->esc($this->localizedValue($englishCard, $arabicCard, 'field_full_name', $mode, (string) $card->label()));
    $job = $this->esc($this->localizedValue($englishCard, $arabicCard, 'field_job_title', $mode));
    $email = $this->esc($this->value($displayCard, 'field_email'));
    $mobile = $this->esc($this->value($displayCard, 'field_mobile'));
    $organization = $org['entity'];
    $orgEn = $organization->hasTranslation('en') ? (string) $organization->getTranslation('en')->label() : (string) $organization->label();
    $orgAr = $organization->hasTranslation('ar') ? (string) $organization->getTranslation('ar')->label() : $orgEn;
    $orgName = $this->esc($this->combineLanguages($orgEn, $orgAr, $mode));
    $departmentEn = $this->departmentLabel($englishCard, 'en');
    $departmentAr = $this->departmentLabel($arabicCard, 'ar');
    $department = $this->esc($this->combineLanguages($departmentEn, $departmentAr, $mode));
    $labels = $this->cardLabels($mode);
    $language = $mode === 'en' ? 'en' : 'ar';
    $direction = $mode === 'en' ? 'ltr' : 'rtl';
    $profileUrl = $this->organizationContext->entityFileUrl($card, 'field_profile_image');
    $profile = $profileUrl !== '' ? '<div class="dc-avatar-wrap"><img class="dc-avatar" src="' . $this->esc($profileUrl) . '" alt="' . $name . '"></div>' : '<div class="dc-avatar-wrap"><div class="dc-avatar dc-avatar-empty" aria-hidden="true"></div></div>';
    $logo = $org['logo_url'] !== '' ? '<img class="dc-logo" src="' . $this->esc($org['logo_url']) . '" alt="' . $orgName . '">' : '';
    $base = rtrim((string) $this->requestStack->getCurrentRequest()?->getBasePath(), '/');
    $api = $base . '/api/digital-card/access/' . rawurlencode($nfc) . '?langcode=' . $language;
    $offersApi = $base . '/api/digital-card/offers/' . rawurlencode($nfc) . '?langcode=' . $language;
    $social = $this->buildSocialLinks($displayCard, $labels, $language);
    $qr = $this->organizationContext->entityFileUrl($card, 'field_qr_code');
    $qrHtml = $qr !== '' ? '<section class="dc-qr"><div class="dc-section-label">' . $labels['scan_share'] . '</div><img src="' . $this->esc($qr) . '" alt="' . $labels['qr_for'] . ' ' . $name . '"></section>' : '';
    $phoneHref = $this->esc((string) preg_replace('/[^0-9+*#,;]/', '', $this->value($card, 'field_mobile')));
    $icons = $this->iconSprite();
    $html = <<<HTML
<!doctype html><html lang="{$language}" dir="{$direction}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$name}</title><link rel="stylesheet" href="{$cssHref}"></head><body>{$icons}<main class="dc-card"><div class="dc-cover"></div>{$profile}<div class="dc-content"><div class="dc-brand">{$logo}<div><div class="dc-org">{$orgName}</div><div class="dc-department">{$department}</div></div></div><h1 class="dc-name" dir="auto">{$name}</h1><div class="dc-job" dir="auto">{$job}</div><div class="dc-contact"><a href="tel:{$phoneHref}"><svg><use href="#i-phone"/></svg><span><small>{$labels['mobile']}</small><strong dir="auto">{$mobile}</strong></span></a><a href="mailto:{$email}"><svg><use href="#i-mail"/></svg><span><small>{$labels['email']}</small><strong>{$email}</strong></span></a></div><div class="dc-actions"><a href="tel:{$phoneHref}"><svg><use href="#i-phone"/></svg><span>{$labels['call']}</span></a><a href="mailto:{$email}"><svg><use href="#i-mail"/></svg><span>{$labels['email']}</span></a><a href="./contact.vcf" download><svg><use href="#i-person-plus"/></svg><span>{$labels['save']}</span></a></div>{$social}{$qrHtml}<section id="dc-private" class="dc-private" hidden aria-live="polite"></section><section id="dc-loyalty" class="dc-loyalty" hidden><div class="dc-section-label">{$labels['loyalty']}</div><div class="dc-loyalty-list"></div></section><section id="dc-offers" class="dc-offers" hidden><div class="dc-section-label">{$labels['offers']}</div><div class="dc-offer-list"></div></section><footer class="dc-powered"><span>{$labels['powered_by']}</span><strong>Ropleon Cards</strong></footer></div></main>
<script>(function(){var box=document.getElementById('dc-private'),offersBox=document.getElementById('dc-offers'),loyaltyBox=document.getElementById('dc-loyalty');function loadOffers(){fetch('{$offersApi}',{credentials:'include',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(d){if(!d)return;if(d.loyalty&&d.loyalty.length){var wallets=loyaltyBox.querySelector('.dc-loyalty-list');d.loyalty.forEach(function(w){var item=document.createElement('article'),top=document.createElement('div'),name=document.createElement('strong'),balance=document.createElement('b'),text=document.createElement('p'),bar=document.createElement('div'),fill=document.createElement('i');name.textContent=w.partner;balance.textContent=w.balance+' points';top.append(name,balance);item.append(top);if(w.next_prize){text.textContent=w.next_prize.unlocked?w.next_prize.title+' is unlocked':w.next_prize.points_remaining+' more points to unlock '+w.next_prize.title;var pct=Math.min(100,Math.round((w.balance/w.next_prize.points_required)*100));fill.style.width=pct+'%';bar.className='dc-loyalty-progress';bar.append(fill);item.append(text,bar)}wallets.append(item)});loyaltyBox.hidden=false}if(d.offers&&d.offers.length){var list=offersBox.querySelector('.dc-offer-list');d.offers.forEach(function(o){var item=document.createElement('article'),h=document.createElement('strong'),p=document.createElement('p'),b=document.createElement('span'),points=document.createElement('small');h.textContent=o.title+' · '+o.partner;b.textContent=o.benefit;p.textContent=o.description||'';if(o.reward_type==='earn_points')points.textContent='Earn '+o.points_awarded+' points';else if(o.reward_type==='points_prize')points.textContent='Costs '+o.points_required+' points';item.append(h,b,p);if(points.textContent)item.append(points);list.appendChild(item)});offersBox.hidden=false}}).catch(function(){});}fetch('{$api}',{credentials:'include',headers:{Accept:'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(d){if(!d||!d.logged_in)return;box.hidden=false;if(d.is_owner){box.textContent='Welcome back. Your loyalty progress and verified benefits are shown below.';loadOffers();}else if(d.scanner_type==='merchant'&&d.capabilities&&d.capabilities.check_offer_eligibility){box.textContent='Merchant mode is active. ';var verify=document.createElement('a');verify.className='dc-verify-offers';verify.href='{$base}/merchant/offers?nfc='+encodeURIComponent('{$nfc}');verify.textContent='Verify offers for this card';box.appendChild(verify);loadOffers();}else{box.textContent='Signed in as '+d.scanner_type+'.';}}).catch(function(){/* Public card remains usable when APIs are unavailable. */});})();</script></body></html>
HTML;
    return strtr($html, [
      ' points' => ' ' . $labels['points'],
      ' is unlocked' => ' ' . $labels['unlocked'],
      ' more points to unlock ' => ' ' . $labels['more_points'] . ' ',
      'Earn ' => $labels['earn'] . ' ',
      'Costs ' => $labels['costs'] . ' ',
      'Welcome back. Your loyalty progress and verified benefits are shown below.' => $labels['welcome'],
      'Merchant mode is active. ' => $labels['merchant_mode'] . ' ',
      'Verify offers for this card' => $labels['verify'],
      'Signed in as ' => $labels['signed_in'] . ' ',
    ]);
  }

  private function buildOrganizationCss(array $org): string {
    $base = rtrim((string) $this->requestStack->getCurrentRequest()?->getBasePath(), '/');
    return <<<CSS
@font-face{font-family:"Noto Sans Arabic";src:url("{$base}/cards/_assets/NotoSansArabic-Variable.ttf") format("truetype");font-style:normal;font-weight:100 900;font-display:swap}
:root{--dc-primary:{$org['primary_color']};--dc-on-primary:{$org['on_primary']};--dc-primary-text:{$org['primary_text']};--dc-secondary:{$org['secondary_color']};--dc-secondary-text:{$org['secondary_text']};--dc-bg:{$org['background']};--dc-on-bg:{$org['on_background']}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:var(--dc-bg);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--dc-on-bg)}
.dc-icon-sprite{position:absolute;width:0;height:0;overflow:hidden}.dc-card{width:min(94vw,460px);overflow:hidden;color:var(--dc-secondary-text);background:#fff;border-radius:30px;box-shadow:0 26px 80px rgba(15,23,42,.18)}.dc-cover{height:132px;background:var(--dc-primary);border-block-end:7px solid var(--dc-secondary)}.dc-avatar-wrap{height:0;display:flex;justify-content:center;position:relative;top:-66px}.dc-avatar{width:132px;height:132px;border-radius:50%;object-fit:cover;background:#e2e8f0;border:6px solid #fff;box-shadow:0 12px 28px rgba(15,23,42,.18)}.dc-avatar-empty:after{content:"";display:block;width:54px;height:54px;margin:33px auto;border-radius:50%;background:#cbd5e1}.dc-content{padding:82px 28px 30px;text-align:center}.dc-brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:18px}.dc-logo{max-width:110px;max-height:46px;object-fit:contain}.dc-org{font-weight:800}.dc-department,.dc-job{color:#64748b}.dc-name{margin:8px 0 5px;font-size:2rem;line-height:1.15}.dc-contact{display:grid;gap:10px;margin:24px 0;text-align:left}.dc-contact a{display:flex;align-items:center;gap:13px;padding:13px 15px;border:1px solid #e5e7eb;border-radius:15px;color:inherit;text-decoration:none}.dc-contact svg{width:22px;height:22px;color:var(--dc-primary-text);fill:currentColor;flex:0 0 auto}.dc-contact span{min-width:0}.dc-contact small{display:block;color:#64748b}.dc-contact strong{display:block;overflow-wrap:anywhere}.dc-actions{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:18px 0}.dc-actions a{display:flex;align-items:center;justify-content:center;gap:7px;padding:12px 8px;border-radius:14px;background:var(--dc-primary);color:var(--dc-on-primary);text-decoration:none;font-weight:750}.dc-actions svg,.dc-social svg{width:19px;height:19px;fill:currentColor}.dc-section-label{margin-bottom:12px;color:#64748b;font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.dc-social{margin:24px 0}.dc-social-list{display:flex;justify-content:center;flex-wrap:wrap;gap:10px}.dc-social a{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border-block-end:3px solid var(--dc-secondary);border-radius:999px;background:#f1f5f9;color:var(--dc-secondary-text);text-decoration:none;font-weight:700}.dc-social a:hover{background:var(--dc-primary);color:var(--dc-on-primary)}.dc-qr{margin-top:25px}.dc-qr img{width:150px;height:150px;object-fit:contain;border:8px solid #fff;border-radius:16px;box-shadow:0 8px 28px rgba(15,23,42,.1)}.dc-private{margin-top:18px;padding:14px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0}.dc-verify-offers{display:inline-flex;margin-left:6px;padding:7px 11px;border-radius:999px;background:var(--dc-primary);color:var(--dc-on-primary);text-decoration:none;font-weight:800}.dc-private[hidden],.dc-offers[hidden]{display:none}.dc-offers{margin-top:22px}.dc-offer-list{display:grid;gap:10px;text-align:left}.dc-offer-list article{padding:14px;border-radius:16px;background:linear-gradient(135deg,#f8fafc,#eff6ff);border:1px solid #dbeafe}.dc-offer-list strong,.dc-offer-list span{display:block}.dc-offer-list span{margin-top:6px;color:var(--dc-primary-text);font-weight:900}.dc-offer-list p{margin:6px 0 0;color:#64748b;font-size:.9rem}@media(max-width:420px){body{padding:0}.dc-card{width:100%;min-height:100vh;border-radius:0}.dc-actions span{display:none}}
.dc-loyalty[hidden]{display:none}.dc-loyalty{margin-top:22px}.dc-loyalty-list{display:grid;gap:10px;text-align:start}.dc-loyalty-list article{padding:15px;border-radius:17px;background:linear-gradient(135deg,#fff7ed,#fffbeb);border:1px solid #fed7aa}.dc-loyalty-list article>div{display:flex;justify-content:space-between;gap:12px}.dc-loyalty-list article b{color:var(--dc-primary)}.dc-loyalty-list p{margin:8px 0;color:#64748b;font-size:.9rem}.dc-loyalty-progress{display:block;height:8px;overflow:hidden;border-radius:999px;background:#e2e8f0}.dc-loyalty-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--dc-primary),#f59e0b)}.dc-offer-list small{display:block;margin-top:8px;color:#92400e;font-weight:800}html[dir=rtl] .dc-contact,html[dir=rtl] .dc-offer-list{text-align:right}html[dir=rtl] body{font-family:"Noto Sans Arabic","Tajawal",Arial,sans-serif}html[dir=rtl] .dc-contact svg{margin-left:0;margin-right:0}
.dc-powered{display:flex;justify-content:center;gap:5px;margin-top:26px;padding-top:18px;border-top:1px solid #e2e8f0;color:#64748b;font-size:.72rem}.dc-powered strong{color:#0f172a}
{$org['custom_css']}
CSS;
  }

  private function localizedValue(NodeInterface $english, NodeInterface $arabic, string $field, string $mode, string $fallback = ''): string {
    $en = $this->value($english, $field) ?: $fallback;
    $ar = $this->value($arabic, $field) ?: $en;
    return $this->combineLanguages($en, $ar, $mode);
  }

  private function combineLanguages(string $english, string $arabic, string $mode): string {
    if ($mode === 'ar') {
      return $arabic !== '' ? $arabic : $english;
    }
    if ($mode === 'bilingual' && $arabic !== '' && $arabic !== $english) {
      return $arabic . ' · ' . $english;
    }
    return $english !== '' ? $english : $arabic;
  }

  private function departmentLabel(NodeInterface $card, string $langcode): string {
    $term = $card->hasField('field_department') ? $card->get('field_department')->entity : NULL;
    if (!$term) {
      return '';
    }
    return $term->hasTranslation($langcode) ? (string) $term->getTranslation($langcode)->label() : (string) $term->label();
  }

  private function cardLabels(string $mode): array {
    $en = [
      'mobile' => 'Mobile', 'email' => 'Email', 'call' => 'Call', 'save' => 'Save contact',
      'scan_share' => 'Scan or share', 'qr_for' => 'QR code for', 'loyalty' => 'Loyalty points',
      'offers' => 'Exclusive offers', 'connect' => 'Connect', 'website' => 'Website',
      'points' => 'points', 'unlocked' => 'is unlocked', 'more_points' => 'more points to unlock', 'powered_by' => 'Powered by',
      'earn' => 'Earn', 'costs' => 'Costs', 'welcome' => 'Welcome back. Your loyalty progress and verified benefits are shown below.',
      'merchant_mode' => 'Merchant mode is active.', 'verify' => 'Verify offers for this card', 'signed_in' => 'Signed in as',
    ];
    $ar = [
      'mobile' => 'الجوال', 'email' => 'البريد الإلكتروني', 'call' => 'اتصال', 'save' => 'حفظ جهة الاتصال',
      'scan_share' => 'مسح أو مشاركة', 'qr_for' => 'رمز الاستجابة السريعة لـ', 'loyalty' => 'نقاط الولاء',
      'offers' => 'العروض الحصرية', 'connect' => 'تواصل', 'website' => 'الموقع الإلكتروني',
      'points' => 'نقطة', 'unlocked' => 'متاحة الآن', 'more_points' => 'نقطة إضافية للحصول على', 'powered_by' => 'بدعم من',
      'earn' => 'اكسب', 'costs' => 'تحتاج', 'welcome' => 'مرحباً بعودتك. يظهر أدناه رصيد الولاء والمزايا المتاحة لك.',
      'merchant_mode' => 'وضع التاجر مفعّل.', 'verify' => 'التحقق من عروض هذه البطاقة', 'signed_in' => 'تم تسجيل الدخول بصفتك',
    ];
    if ($mode === 'ar') {
      return $ar;
    }
    if ($mode === 'bilingual') {
      return array_map(static fn(string $value, string $key): string => $ar[$key] . ' / ' . $value, $en, array_keys($en));
    }
    return $en;
  }

  private function buildSocialLinks(NodeInterface $card, array $labels, string $langcode): string {
    if (!$card->hasField('field_social_links') || $card->get('field_social_links')->isEmpty()) {
      return '';
    }
    $links = [];
    $seen = [];
    foreach ($card->get('field_social_links')->referencedEntities() as $item) {
      $platform = $item->hasField('field_platform') ? trim((string) $item->get('field_platform')->value) : '';
      $rawUrl = $item->hasField('field_url') && !$item->get('field_url')->isEmpty()
        ? trim((string) $item->get('field_url')->value)
        : '';
      $platformId = $this->socialPlatforms?->resolveId($platform);
      if ($platformId !== NULL) {
        $definition = $this->socialPlatforms->get($platformId, FALSE);
        if (!$definition || isset($seen[$platformId])) {
          continue;
        }
        $normalized = $this->socialPlatforms->normalizeUrl($platformId, $rawUrl);
        $url = $normalized['url'];
        if ($url === '') {
          $this->logger->warning('Social link @platform was omitted from card @card: @reason', [
            '@platform' => $platformId,
            '@card' => $card->id(),
            '@reason' => $normalized['error'],
          ]);
          continue;
        }
        $label = $this->socialPlatforms->label($definition, $langcode);
        $icon = 'i-' . $definition['icon'];
        $seen[$platformId] = TRUE;
      }
      else {
        // Backward-compatible fallback for an unknown legacy value. New and
        // edited Paragraphs use the controlled widget and cannot create this.
        $url = $this->normalizeExternalUrl($rawUrl);
        $label = $platform !== '' ? ucfirst($platform) : $labels['website'];
        $icon = $this->socialIconId($platform);
      }
      if ($url !== '') {
        $links[] = '<a href="' . $this->esc($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . $this->esc($label) . '"><svg aria-hidden="true" focusable="false"><use href="#' . $icon . '"/></svg><span>' . $this->esc($label) . '</span></a>';
      }
      if (count($links) >= 8) {
        break;
      }
    }
    return $links ? '<section class="dc-social"><div class="dc-section-label">' . $labels['connect'] . '</div><div class="dc-social-list">' . implode('', $links) . '</div></section>' : '';
  }

  private function normalizeExternalUrl(string $value): string {
    $value = trim($value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
      return '';
    }
    if (!preg_match('#^https?://#i', $value)) {
      $value = 'https://' . ltrim($value, '/');
    }
    $parts = parse_url($value);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], TRUE) || empty($parts['host'])) {
      return '';
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
  }

  private function socialIconId(string $platform): string {
    $platform = strtolower(trim($platform));
    return match (TRUE) {
      str_contains($platform, 'facebook') => 'i-facebook',
      str_contains($platform, 'linkedin') => 'i-linkedin',
      str_contains($platform, 'instagram') => 'i-instagram',
      str_contains($platform, 'twitter'), $platform === 'x' => 'i-x',
      str_contains($platform, 'youtube') => 'i-youtube',
      str_contains($platform, 'whatsapp') => 'i-whatsapp',
      str_contains($platform, 'tiktok') => 'i-tiktok',
      str_contains($platform, 'telegram') => 'i-telegram',
      str_contains($platform, 'snapchat') => 'i-snapchat',
      str_contains($platform, 'threads') => 'i-threads',
      str_contains($platform, 'github') => 'i-github',
      str_contains($platform, 'googlemap'), str_contains($platform, 'google map') => 'i-google_maps',
      str_contains($platform, 'website'), str_contains($platform, 'web') => 'i-website',
      default => 'i-link',
    };
  }

  private function iconSprite(): string {
    return <<<'SVG'
<svg class="dc-icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
<symbol id="i-phone" viewBox="0 0 24 24"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1.1-.3 1.2.4 2.5.6 3.8.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.7 21 3 13.3 3 3.7c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.6.6 3.8.1.4 0 .8-.3 1.1l-2.2 2.2Z"/></symbol>
<symbol id="i-mail" viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h16a2 2 0 0 0 2-2V6c0-1.1-.9-2-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></symbol>
<symbol id="i-person-plus" viewBox="0 0 24 24"><path d="M15 12c2.2 0 4-1.8 4-4s-1.8-4-4-4-4 1.8-4 4 1.8 4 4 4ZM6 10V7H4v3H1v2h3v3h2v-3h3v-2H6Zm9 4c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4Z"/></symbol>
<symbol id="i-link" viewBox="0 0 24 24"><path d="M3.9 12a5 5 0 0 1 5-5H12v2H8.9a3 3 0 1 0 0 6H12v2H8.9a5 5 0 0 1-5-5Zm5.1 1h6v-2H9v2Zm6.1-6H12V5h3.1a7 7 0 1 1 0 14H12v-2h3.1a5 5 0 1 0 0-10Z"/></symbol>
<symbol id="i-facebook" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z"/></symbol>
<symbol id="i-linkedin" viewBox="0 0 24 24"><path d="M6.5 8.3H3.2V21h3.3V8.3ZM4.9 3A1.9 1.9 0 1 0 5 6.8 1.9 1.9 0 0 0 4.9 3ZM21 13.7c0-3.8-2-5.6-4.8-5.6-2.2 0-3.2 1.2-3.8 2.1V8.3H9.1V21h3.3v-6.3c0-1.7.3-3.3 2.4-3.3 2 0 2.1 1.9 2.1 3.4V21H21v-7.3Z"/></symbol>
<symbol id="i-instagram" viewBox="0 0 24 24"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7Zm10.5 1.5a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24"><path d="M18.9 2H22l-6.8 7.8L23 22h-6.1l-4.8-6.3L6.6 22H3.5l7.1-8.2L3 2h6.3l4.3 5.7L18.9 2Zm-1.1 17.9h1.7L8.4 4H6.6l11.2 15.9Z"/></symbol>
<symbol id="i-youtube" viewBox="0 0 24 24"><path d="M23 12s0-3.5-.4-5.2a3 3 0 0 0-2.1-2.1C18.7 4.2 12 4.2 12 4.2s-6.7 0-8.5.5a3 3 0 0 0-2.1 2.1C1 8.5 1 12 1 12s0 3.5.4 5.2a3 3 0 0 0 2.1 2.1c1.8.5 8.5.5 8.5.5s6.7 0 8.5-.5a3 3 0 0 0 2.1-2.1C23 15.5 23 12 23 12Zm-13.2 3.4V8.6l6 3.4-6 3.4Z"/></symbol>
<symbol id="i-whatsapp" viewBox="0 0 24 24"><path d="M20.5 3.5A11.8 11.8 0 0 0 1.9 17.7L.3 23.5l5.9-1.6A11.7 11.7 0 0 0 12 23.4h.1A11.8 11.8 0 0 0 20.5 3.5ZM12.1 21.4a9.7 9.7 0 0 1-5-1.4l-.4-.2-3.5.9.9-3.4-.2-.4A9.8 9.8 0 1 1 12 21.4h.1Zm5.4-7.3c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2l-1 1.2c-.2.2-.4.2-.7.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2.1c-.2-.3 0-.5.1-.6l.5-.6.3-.6c.1-.2 0-.4 0-.6l-1-2.3c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.2-1.2 2.9 0 1.7 1.2 3.3 1.4 3.5.2.2 2.4 3.7 5.9 5.2.8.4 1.5.6 2 .7.8.3 1.6.2 2.2.1.7-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.2-.3-.3-.6-.4Z"/></symbol>
<symbol id="i-tiktok" viewBox="0 0 24 24"><path d="M14.5 2h3.1c.2 1.7 1.2 3.2 2.7 4.1.8.5 1.7.7 2.7.8V10a9 9 0 0 1-5.3-1.7v7.6A6.1 6.1 0 1 1 12.4 10v3.2a3 3 0 1 0 2.1 2.8V2Z"/></symbol>
<symbol id="i-telegram" viewBox="0 0 24 24"><path d="M22.4 2.3 1.8 10.2c-1.4.6-1.4 1.4-.3 1.7l5.3 1.7 2 6.1c.2.7.1 1 .9 1 .6 0 .9-.3 1.2-.6l2.6-2.5 5.4 4c1 .6 1.7.3 2-.9l3.4-16.2c.4-1.5-.6-2.2-1.9-1.7ZM8.7 13.2l10.4-6.5c.5-.3 1-.1.6.2l-8.6 7.8-.3 3.5-2.1-5Z"/></symbol>
<symbol id="i-snapchat" viewBox="0 0 24 24"><path d="M12 2c3.2 0 5.1 2.4 5.1 5.5 0 .8-.1 1.6-.1 2.2.4.3 1 .5 1.5.2.9-.5 1.6.9.7 1.5-.5.3-1.1.5-1.6.6.4 1.4 1.5 2.5 3.3 3.2.9.3.7 1.6-.2 1.8-.6.1-1 .3-1.1.7-.2.5-.7.7-1.3.5-1.2-.4-2.2-.1-3.3.6-.9.6-1.9 1.1-3 1.1s-2.1-.5-3-1.1c-1.1-.7-2.1-1-3.3-.6-.6.2-1.1 0-1.3-.5-.1-.4-.5-.6-1.1-.7-.9-.2-1.1-1.5-.2-1.8 1.8-.7 2.9-1.8 3.3-3.2-.5-.1-1.1-.3-1.6-.6-.9-.6-.2-2 .7-1.5.5.3 1.1.1 1.5-.2 0-.6-.1-1.4-.1-2.2C6.9 4.4 8.8 2 12 2Z"/></symbol>
<symbol id="i-threads" viewBox="0 0 24 24"><path d="M12.2 2C6.4 2 3 5.8 3 12.1 3 18.8 6.5 22 12.4 22c5.1 0 8.5-2.8 8.5-7.2 0-3.6-2.1-5.7-5.5-6.1-.8-2.3-2.6-3.4-5.1-3.4-2 0-3.6.7-4.7 1.8l1.7 1.8c.8-.8 1.8-1.2 3-1.2 1.3 0 2.2.5 2.7 1.4-4.7.4-7 2.1-7 5.2 0 2.7 2.2 4.6 5.1 4.6 3.1 0 5.1-1.9 5.1-4.8 0-.9-.1-1.7-.2-2.5 1.5.5 2.3 1.5 2.3 3.2 0 3-2.2 4.7-5.9 4.7-4.4 0-6.8-2.4-6.8-7.4 0-4.9 2.5-7.6 6.6-7.6 3.1 0 5.1 1.4 6.1 4l2.4-.9C19.4 4 16.5 2 12.2 2Zm-1 14.5c-1.5 0-2.6-.8-2.6-2.2 0-1.5 1.4-2.4 4.9-2.7.1.7.2 1.4.2 2.1 0 1.8-.9 2.8-2.5 2.8Z"/></symbol>
<symbol id="i-github" viewBox="0 0 24 24"><path d="M12 .8A11.4 11.4 0 0 0 8.4 23c.6.1.8-.3.8-.6v-2.2c-3.4.7-4.1-1.4-4.1-1.4-.5-1.4-1.3-1.8-1.3-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-6a4.7 4.7 0 0 1 1.2-3.2c-.1-.3-.5-1.6.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.6 1.6.2 2.9.1 3.2a4.7 4.7 0 0 1 1.2 3.2c0 4.7-2.8 5.7-5.5 6 .4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A11.4 11.4 0 0 0 12 .8Z"/></symbol>
<symbol id="i-google_maps" viewBox="0 0 24 24"><path d="M12 2a8 8 0 0 0-8 8c0 5.6 6.7 11.3 7.5 12a.8.8 0 0 0 1 0c.8-.7 7.5-6.4 7.5-12a8 8 0 0 0-8-8Zm0 11.5A3.5 3.5 0 1 1 12 6a3.5 3.5 0 0 1 0 7.5Z"/></symbol>
<symbol id="i-website" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 6h-3a15.7 15.7 0 0 0-1.4-3.4A8 8 0 0 1 18.9 8ZM12 4c.8 1 1.5 2.3 1.8 4h-3.6C10.5 6.3 11.2 5 12 4ZM4.3 14a8.1 8.1 0 0 1 0-4h3.4a16.5 16.5 0 0 0 0 4H4.3Zm.8 2h3a15.7 15.7 0 0 0 1.4 3.4A8 8 0 0 1 5.1 16Zm3-8h-3a8 8 0 0 1 4.4-3.4A15.7 15.7 0 0 0 8.1 8Zm3.9 12c-.8-1-1.5-2.3-1.8-4h3.6c-.3 1.7-1 3-1.8 4Zm2.2-6H9.8a14.3 14.3 0 0 1 0-4h4.4a14.3 14.3 0 0 1 0 4Zm.3 5.4a15.7 15.7 0 0 0 1.4-3.4h3a8 8 0 0 1-4.4 3.4Zm1.8-5.4a16.5 16.5 0 0 0 0-4h3.4a8.1 8.1 0 0 1 0 4h-3.4Z"/></symbol>
</defs></svg>
SVG;
  }

  private function buildVcard(NodeInterface $card, array $org): string {
    $override = $this->value($card, 'field_card_language');
    $mode = in_array($override, ['en', 'ar', 'bilingual'], TRUE) ? $override : ($org['card_language'] ?? 'en');
    if ($mode !== 'en' && $card->hasTranslation('ar')) {
      $card = $card->getTranslation('ar');
    }
    $escape = static fn(string $v): string => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', "\\n"], $v);
    $name = $escape($this->value($card, 'field_full_name') ?: (string) $card->label());
    $job = $escape($this->value($card, 'field_job_title'));
    $mobile = $escape($this->value($card, 'field_mobile'));
    $email = $escape($this->value($card, 'field_email'));
    $organization = '';
    if ($card->hasField('field_organization') && ($entity = $card->get('field_organization')->entity)) {
      $organization = $escape((string) $entity->label());
    }
    $department = '';
    if ($card->hasField('field_department') && ($entity = $card->get('field_department')->entity)) {
      $department = $escape((string) $entity->label());
    }
    $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . $name];
    if ($organization !== '' || $department !== '') {
      $lines[] = 'ORG:' . $organization . ($department !== '' ? ';' . $department : '');
    }
    if ($job !== '') {
      $lines[] = 'TITLE:' . $job;
    }
    if ($mobile !== '') {
      $lines[] = 'TEL;TYPE=CELL:' . $mobile;
    }
    if ($email !== '') {
      $lines[] = 'EMAIL;TYPE=INTERNET:' . $email;
    }
    if ($card->hasField('field_social_links')) {
      foreach ($card->get('field_social_links')->referencedEntities() as $social) {
        $url = $social->hasField('field_url') ? $this->normalizeExternalUrl((string) $social->get('field_url')->value) : '';
        if ($url !== '') {
          $lines[] = 'URL:' . $escape($url);
        }
      }
    }
    $lines[] = 'END:VCARD';
    return implode("\r\n", $lines) . "\r\n";
  }

  private function atomicWrite(string $destination, string $contents): void {
    $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
    if (file_put_contents($temporary, $contents, LOCK_EX) === FALSE || !@rename($temporary, $destination)) {
      @unlink($temporary);
      throw new \RuntimeException('Unable to write ' . $destination);
    }
  }

}
