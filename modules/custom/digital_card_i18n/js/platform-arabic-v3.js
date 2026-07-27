(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.digitalCardArabicRuntime = {
    attach(context) {
      const configuredLabels = drupalSettings.digitalCardI18n?.labels || {};
      const tr = (source) => configuredLabels[source] || Drupal.t(source);
      const interfaceLabels = [
        ['a.skip-link', 'Skip to main content'],
        ['a[data-toolbar-escape-admin]', 'Back to site'],
        ['#toolbar-item-administration', 'Manage'],
        ['#toolbar-item-shortcuts', 'Shortcuts'],
        ['a.language-link[hreflang="ar"]', 'Arabic'],
      ];
      interfaceLabels.forEach(([selector, source]) => {
        once('digital-card-arabic-' + source.toLowerCase().replace(/\s+/g, '-'), selector, context).forEach((element) => {
          element.textContent = tr(source);
          if (element.hasAttribute('title')) {
            element.setAttribute('title', tr(source));
          }
        });
      });

      once('digital-card-arabic-toolbar-accessibility', '#toolbar-administration', context).forEach((toolbar) => {
        toolbar.setAttribute('aria-label', tr('Site administration toolbar'));
      });
      once('digital-card-arabic-site-header', '#header[role="banner"]', context).forEach((header) => {
        header.setAttribute('aria-label', tr('Site header'));
      });
      once('digital-card-arabic-toggle-navigation', '.navbar-toggler[aria-label]', context).forEach((button) => {
        button.setAttribute('aria-label', tr('Toggle navigation'));
      });
      once('digital-card-arabic-back-to-site-title', 'a[data-toolbar-escape-admin]', context).forEach((link) => {
        link.setAttribute('title', tr('Return to site content'));
      });

      const tabLabels = { Members: tr('Members'), Nodes: tr('Nodes') };
      once('digital-card-arabic-group-tabs-v2', 'nav a, .tabs a', context).forEach((link) => {
        const source = link.textContent.trim();
        if (tabLabels[source]) {
          link.textContent = tabLabels[source];
        }
      });

      const groupLabels = {
        'Add member': tr('Add member'),
        'Add new content': tr('Add new content'),
        'Employee': tr('Employee'),
        'Org Admin': tr('Org Admin'),
        'Joined': tr('Joined'),
        'Operations': tr('Operations'),
        'List additional actions': tr('List additional actions'),
        '- Any -': tr('- Any -'),
        'Type': tr('Type'),
        'Article': tr('Article'),
        'Basic page': tr('Basic page'),
        'Digital Business Card': tr('Digital Business Card'),
        'Organization Subscription': tr('Organization Subscription'),
        'Subscription Plan': tr('Subscription Plan'),
        'Title': tr('Title'),
        'Content type': tr('Content type'),
        'Current state': tr('Current state'),
        'Sort ascending': tr('Sort ascending'),
        'Arabic': tr('Arabic'),
      };
      Object.entries(configuredLabels).forEach(([source, translated]) => {
        if (source && translated) {
          groupLabels[source] = translated;
        }
      });
      once('digital-card-arabic-group-ui-v2', 'main a, main button, main th, main td, main li, main label, main option, main span', context).forEach((element) => {
        if (element.children.length > 0) {
          return;
        }
        const source = element.textContent.trim();
        if (groupLabels[source]) {
          element.textContent = groupLabels[source];
        }
        else if (/^\d+\s+records?$/.test(source)) {
          const count = Number.parseInt(source, 10);
          element.textContent = Drupal.formatPlural(count, '1 record', '@count records');
        }
        else if (/^Page\s+\d+$/.test(source)) {
          const page = source.match(/\d+/)?.[0] || '';
          element.textContent = tr('Page @number').replace('@number', page);
        }
        else if (/^card_workflow_rejected$/i.test(source)) {
          element.textContent = tr('Rejected');
        }
        else if (source === 'تعديل node') {
          element.textContent = tr('Edit');
        }
        else if (source === 'عرض member') {
          element.textContent = tr('View');
        }
      });
      once('digital-card-arabic-toolbar-ui-v3', '#toolbar-administration a, #toolbar-administration button, #toolbar-administration span', context).forEach((element) => {
        if (element.children.length > 0) {
          return;
        }
        const source = element.textContent.trim();
        if (groupLabels[source]) {
          element.textContent = groupLabels[source];
        }
      });
      once('digital-card-arabic-runtime-attributes-v3', 'main [title], main [aria-label], main input[placeholder], #toolbar-administration [title], #toolbar-administration [aria-label]', context).forEach((element) => {
        ['title', 'aria-label', 'placeholder'].forEach((attribute) => {
          const source = (element.getAttribute(attribute) || '').trim();
          if (source && groupLabels[source]) {
            element.setAttribute(attribute, groupLabels[source]);
          }
        });
      });
      once('digital-card-arabic-group-search-v2', 'main input[placeholder]', context).forEach((input) => {
        let placeholder = input.getAttribute('placeholder') || '';
        Object.entries(groupLabels).forEach(([source, translated]) => {
          placeholder = placeholder.replace(source, translated);
        });
        input.setAttribute('placeholder', placeholder);
        if (input.getAttribute('aria-label')) {
          let ariaLabel = input.getAttribute('aria-label');
          Object.entries(groupLabels).forEach(([source, translated]) => {
            ariaLabel = ariaLabel.replace(source, translated);
          });
          input.setAttribute('aria-label', ariaLabel);
        }
      });

      once('digital-card-arabic-close', 'button[aria-label="Close"]', context).forEach((button) => {
        button.setAttribute('aria-label', tr('Close'));
      });

      once('digital-card-arabic-delete-node', 'li.delete-node a', context).forEach((link) => {
        link.textContent = tr('Delete');
      });

      const dynamicPasswordLabels = [
        ['.password-strength__title', 'Password strength:'],
        ['.password-confirm-message', 'Passwords match:'],
      ];
      dynamicPasswordLabels.forEach(([selector, source]) => {
        once('digital-card-arabic-' + source.toLowerCase().replace(/\W+/g, '-'), selector, context).forEach((element) => {
          if (element.firstChild && element.firstChild.nodeType === Node.TEXT_NODE) {
            element.firstChild.nodeValue = tr(source) + ' ';
          }
        });
      });

      once('digital-card-arabic-account-language-description', '#edit-preferred-langcode--description', context).forEach((description) => {
        description.textContent = tr('Choose the language used for account notifications and portal pages.');
      });

      once('digital-card-arabic-empty-option', 'select option[value="_none"]', context).forEach((option) => {
        option.textContent = tr('- None -');
      });

      once('digital-card-arabic-internal-links', 'main a[href]', context).forEach((link) => {
        try {
          const url = new URL(link.href, window.location.origin);
          if (url.origin === window.location.origin && /\/en\//.test(url.pathname)) {
            const cardAlias = url.pathname.match(/\/en\/org\/[^/]+\/card\/(\d+)\/?$/i);
            url.pathname = cardAlias
              ? url.pathname.replace(/\/en\/org\/[^/]+\/card\/\d+\/?$/i, '/ar/node/' + cardAlias[1])
              : url.pathname.replace('/en/', '/ar/');
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
        document.title = tr('Add Digital Card') + ' | ' + tr('Ropleon Cards');
      }
      else if (document.documentElement.lang === 'ar') {
        const titleRules = [
          [/\/group\/\d+\/members\/?$/i, 'Members'],
          [/\/group\/\d+\/nodes\/?$/i, 'Content'],
          [/\/group\/\d+\/content\/add\/group_membership\/?$/i, 'Add member'],
        ];
        const matchedRule = titleRules.find(([pattern]) => pattern.test(window.location.pathname));
        if (matchedRule) {
          const suffix = document.title.includes('|')
            ? ' | ' + document.title.split('|').slice(1).join('|').trim()
            : '';
          document.title = tr(matchedRule[1]) + suffix;
        }
      }
    },
  };
})(Drupal, once, drupalSettings);
