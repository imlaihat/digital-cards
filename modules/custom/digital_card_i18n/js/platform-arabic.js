(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.digitalCardArabicRuntime = {
    attach(context) {
      const interfaceLabels = [
        ['a.skip-link', 'Skip to main content'],
        ['#toolbar-item-administration', 'Manage'],
        ['#toolbar-item-shortcuts', 'Shortcuts'],
      ];
      interfaceLabels.forEach(([selector, source]) => {
        once('digital-card-arabic-' + source.toLowerCase().replace(/\s+/g, '-'), selector, context).forEach((element) => {
          element.textContent = Drupal.t(source);
          if (element.hasAttribute('title')) {
            element.setAttribute('title', Drupal.t(source));
          }
        });
      });

      const tabLabels = { Members: Drupal.t('Members'), Nodes: Drupal.t('Nodes') };
      once('digital-card-arabic-group-tabs', 'nav a, .tabs a', context).forEach((link) => {
        const source = link.textContent.trim();
        if (tabLabels[source]) {
          link.textContent = tabLabels[source];
        }
      });

      once('digital-card-arabic-close', 'button[aria-label="Close"]', context).forEach((button) => {
        button.setAttribute('aria-label', Drupal.t('Close'));
      });

      once('digital-card-arabic-empty-option', 'select option[value="_none"]', context).forEach((option) => {
        option.textContent = Drupal.t('- None -');
      });

      // Entity-reference formatters can inherit the referenced entity's source
      // language. Keep internal content links inside the active Arabic portal;
      // the explicit language switcher is outside <main> and is not changed.
      once('digital-card-arabic-internal-links', 'main a[href]', context).forEach((link) => {
        try {
          const url = new URL(link.href, window.location.origin);
          if (url.origin === window.location.origin && /\/en\//.test(url.pathname)) {
            url.pathname = url.pathname.replace('/en/', '/ar/');
            link.href = url.toString();
          }
        }
        catch (error) {
          // Ignore non-URL href values such as JavaScript-managed fragments.
        }
      });

      if (
        document.documentElement.lang === 'ar'
        && /\/group\/\d+\/content\/create\/group_node(?::|%3A)digital_business_card/i.test(window.location.pathname)
      ) {
        document.title = Drupal.t('Add Digital Card') + ' | ' + Drupal.t('Digital Card Platform');
      }
    },
  };
})(Drupal, once);
