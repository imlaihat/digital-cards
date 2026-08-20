(function (Drupal, once) {
  'use strict';

  /**
   * Adds an accessible mobile menu to authenticated portal headers.
   */
  Drupal.behaviors.digitalCardPortalNavigation = {
    attach(context) {
      once('digital-card-portal-navigation', '.dc-portal-header', context).forEach((header) => {
        const toggle = header.querySelector('[data-dc-portal-toggle]');
        const navigation = header.querySelector('[data-dc-portal-nav]');
        if (!toggle || !navigation) {
          return;
        }

        const setOpen = (open) => {
          header.dataset.portalMenuOpen = open ? 'true' : 'false';
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          const openLabel = toggle.dataset.openLabel || Drupal.t('Open navigation');
          const closeLabel = toggle.dataset.closeLabel || Drupal.t('Close navigation');
          toggle.setAttribute('aria-label', open ? closeLabel : openLabel);
        };

        header.dataset.portalNavigationEnhanced = 'true';
        toggle.hidden = false;
        setOpen(false);

        toggle.addEventListener('click', () => {
          setOpen(header.dataset.portalMenuOpen !== 'true');
        });

        navigation.addEventListener('click', (event) => {
          if (event.target.closest('a')) {
            setOpen(false);
          }
        });

        header.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && header.dataset.portalMenuOpen === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });

        window.addEventListener('resize', () => {
          if (window.innerWidth > 900 && header.dataset.portalMenuOpen === 'true') {
            setOpen(false);
          }
        }, {passive: true});
      });
    },
  };
})(Drupal, once);
