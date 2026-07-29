(function (Drupal) {
  'use strict';

  Drupal.behaviors.ropleonPublicNavigation = {
    attach(context) {
      const header = context.querySelector ? context.querySelector('[data-rp-header]') : null;
      if (!header || header.dataset.rpNavigationReady === 'true') {
        return;
      }

      const button = header.querySelector('[data-rp-nav-toggle]');
      const closeButton = header.querySelector('[data-rp-nav-close]');
      const navigation = header.querySelector('[data-rp-nav]');
      const backdrop = header.querySelector('[data-rp-nav-backdrop]');
      if (!button || !navigation || !backdrop) {
        return;
      }

      header.dataset.rpNavigationReady = 'true';
      let returnFocus = null;
      const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

      const closeNavigation = (restoreFocus = true) => {
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', Drupal.t('Open navigation'));
        navigation.classList.remove('is-open');
        backdrop.hidden = true;
        if (restoreFocus && returnFocus) {
          returnFocus.focus();
        }
      };

      const openNavigation = () => {
        returnFocus = document.activeElement;
        button.setAttribute('aria-expanded', 'true');
        button.setAttribute('aria-label', Drupal.t('Close navigation'));
        navigation.classList.add('is-open');
        backdrop.hidden = true;
        const firstFocusable = navigation.querySelector(focusableSelector);
        if (firstFocusable) {
          firstFocusable.focus();
        }
      };

      button.addEventListener('click', () => {
        if (button.getAttribute('aria-expanded') === 'true') {
          closeNavigation();
        }
        else {
          openNavigation();
        }
      });

      if (closeButton) {
        closeButton.addEventListener('click', () => closeNavigation());
      }
      backdrop.addEventListener('click', () => closeNavigation());
      navigation.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
          closeNavigation(false);
        }
      });

      document.addEventListener('keydown', (event) => {
        if (!navigation.classList.contains('is-open')) {
          return;
        }
        if (event.key === 'Escape') {
          event.preventDefault();
          closeNavigation();
          return;
        }
        if (event.key !== 'Tab') {
          return;
        }
        const focusable = Array.from(navigation.querySelectorAll(focusableSelector))
          .filter((element) => element.offsetParent !== null);
        if (!focusable.length) {
          return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        }
        else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      });

      window.addEventListener('resize', () => {
        if (window.innerWidth > 1140 && navigation.classList.contains('is-open')) {
          closeNavigation(false);
        }
      }, {passive: true});
    },
  };

  Drupal.behaviors.ropleonPublicReveal = {
    attach(context) {
      const items = Array.from(context.querySelectorAll ? context.querySelectorAll('[data-rp-reveal]:not([data-rp-reveal-ready])') : []);
      if (!items.length) {
        return;
      }
      const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (reducedMotion || !('IntersectionObserver' in window)) {
        items.forEach((item) => {
          item.dataset.rpRevealReady = 'true';
          item.classList.add('is-visible');
        });
        return;
      }
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, {rootMargin: '0px 0px -8% 0px', threshold: 0.12});
      items.forEach((item) => {
        item.dataset.rpRevealReady = 'true';
        observer.observe(item);
      });
    },
  };
})(Drupal);
