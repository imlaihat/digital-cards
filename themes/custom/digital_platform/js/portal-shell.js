(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.ropleonMerchantNavigation = {
    attach(context) {
      once('ropleon-merchant-navigation', '[data-rp-merchant-header]', context).forEach((header) => {
        const toggle = header.querySelector('[data-rp-merchant-toggle]');
        const navigation = header.querySelector('[data-rp-merchant-nav]');
        if (!toggle || !navigation) {
          return;
        }

        const setOpen = (open) => {
          header.dataset.menuOpen = open ? 'true' : 'false';
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        setOpen(false);
        toggle.addEventListener('click', () => {
          setOpen(header.dataset.menuOpen !== 'true');
        });
        navigation.addEventListener('click', (event) => {
          if (event.target.closest('a')) {
            setOpen(false);
          }
        });
        header.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && header.dataset.menuOpen === 'true') {
            setOpen(false);
            toggle.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
