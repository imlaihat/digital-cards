(function (Drupal, once) {
  'use strict';

  const menuSelector = '.view table .card-actions-menu, .view table .organization-actions, .view table .platform-actions, .view table .dropdown';
  let openMenu = null;

  /**
   * Closes a currently open table action menu.
   */
  const closeMenu = (wrapper, restoreFocus = false) => {
    if (!wrapper) {
      return;
    }
    const toggle = wrapper.querySelector('[data-rp-action-toggle]');
    const menu = wrapper.querySelector('.dropdown-menu');
    wrapper.classList.remove('rp-action-menu-open');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', Drupal.t('Open actions'));
    }
    if (menu) {
      menu.style.removeProperty('--rp-action-menu-left');
      menu.style.removeProperty('--rp-action-menu-top');
      menu.style.removeProperty('--rp-action-menu-width');
      menu.style.removeProperty('--rp-action-menu-max-height');
    }
    if (restoreFocus && toggle) {
      toggle.focus();
    }
    if (openMenu === wrapper) {
      openMenu = null;
    }
  };

  /**
   * Positions a menu inside the visual viewport without table clipping.
   */
  const positionMenu = (wrapper) => {
    const toggle = wrapper.querySelector('[data-rp-action-toggle]');
    const menu = wrapper.querySelector('.dropdown-menu');
    if (!toggle || !menu) {
      return;
    }

    const viewportWidth = document.documentElement.clientWidth;
    const viewportHeight = document.documentElement.clientHeight;
    const toggleRect = toggle.getBoundingClientRect();
    const menuWidth = Math.min(280, viewportWidth - 24);
    const estimatedHeight = Math.min(menu.scrollHeight || 320, Math.max(180, viewportHeight - 32));
    const rtl = document.documentElement.dir === 'rtl';
    let left = rtl ? toggleRect.left : toggleRect.right - menuWidth;
    left = Math.max(12, Math.min(left, viewportWidth - menuWidth - 12));

    const roomBelow = viewportHeight - toggleRect.bottom - 16;
    const top = roomBelow >= Math.min(estimatedHeight, 260)
      ? toggleRect.bottom + 8
      : Math.max(12, toggleRect.top - estimatedHeight - 8);

    menu.style.setProperty('--rp-action-menu-left', `${left}px`);
    menu.style.setProperty('--rp-action-menu-top', `${top}px`);
    menu.style.setProperty('--rp-action-menu-width', `${menuWidth}px`);
    menu.style.setProperty('--rp-action-menu-max-height', `${Math.max(180, viewportHeight - top - 16)}px`);
  };

  /**
   * Opens a table action menu for mouse, keyboard, and touch input.
   */
  const showMenu = (wrapper) => {
    if (openMenu && openMenu !== wrapper) {
      closeMenu(openMenu);
    }
    const toggle = wrapper.querySelector('[data-rp-action-toggle]');
    wrapper.classList.add('rp-action-menu-open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', Drupal.t('Close actions'));
    openMenu = wrapper;
    positionMenu(wrapper);
  };

  Drupal.behaviors.ropleonResponsiveTables = {
    attach(context) {
      once('ropleon-responsive-table', '.rp-portal-shell__main table:not(.sticky-header)', context).forEach((table) => {
        let scrollRegion = table.closest('.table-responsive, .rp-table-scroll');
        if (!scrollRegion) {
          scrollRegion = document.createElement('div');
          scrollRegion.className = 'rp-table-scroll';
          table.parentNode.insertBefore(scrollRegion, table);
          scrollRegion.appendChild(table);
        }
        else {
          scrollRegion.classList.add('rp-table-scroll');
        }

        table.classList.add('rp-responsive-table');
        scrollRegion.setAttribute('tabindex', '0');
        scrollRegion.setAttribute('role', 'region');
        scrollRegion.setAttribute('aria-label', Drupal.t('Scrollable data table'));

        const hint = document.createElement('p');
        hint.className = 'rp-table-scroll-hint';
        hint.innerHTML = '<span aria-hidden="true">↔</span> ' + Drupal.t('Swipe horizontally to view all columns');
        scrollRegion.parentNode.insertBefore(hint, scrollRegion);
      });

      once('ropleon-table-actions', menuSelector, context).forEach((wrapper, index) => {
        const menu = wrapper.querySelector('.dropdown-menu');
        if (!menu) {
          return;
        }

        wrapper.classList.add('rp-action-menu');
        const actionCell = wrapper.closest('td, th');
        if (actionCell) {
          actionCell.classList.add('rp-table-action-cell');
          const table = actionCell.closest('table');
          const index = actionCell.cellIndex;
          table?.querySelectorAll('thead tr').forEach((row) => {
            if (row.cells[index]) {
              row.cells[index].classList.add('rp-table-action-cell');
            }
          });
        }
        let toggle = wrapper.querySelector('button.dropdown-toggle, a.dropdown-toggle, [data-bs-toggle="dropdown"]');
        if (!toggle) {
          // Older Views configurations output a bare "Actions" text node.
          Array.from(wrapper.childNodes).forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
              node.remove();
            }
          });
          toggle = document.createElement('button');
          toggle.type = 'button';
          toggle.className = 'btn btn-sm btn-outline-secondary dropdown-toggle rp-action-menu__toggle';
          toggle.textContent = Drupal.t('Actions');
          wrapper.insertBefore(toggle, menu);
        }

        // Use one deterministic controller instead of mixing hover and
        // Bootstrap's dropdown listener, which is unreliable inside scrollers.
        toggle.removeAttribute('data-bs-toggle');
        toggle.setAttribute('data-rp-action-toggle', '');
        toggle.setAttribute('aria-haspopup', 'menu');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', Drupal.t('Open actions'));
        if (!menu.id) {
          menu.id = `rp-table-actions-${index}-${Math.random().toString(36).slice(2, 8)}`;
        }
        toggle.setAttribute('aria-controls', menu.id);
        menu.setAttribute('role', 'menu');

        toggle.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (wrapper.classList.contains('rp-action-menu-open')) {
            closeMenu(wrapper, true);
          }
          else {
            showMenu(wrapper);
          }
        });

        menu.addEventListener('click', (event) => {
          if (event.target.closest('a, button')) {
            closeMenu(wrapper);
          }
        });

        wrapper.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && wrapper.classList.contains('rp-action-menu-open')) {
            event.preventDefault();
            closeMenu(wrapper, true);
          }
        });
      });

      if (!document.documentElement.dataset.rpTableActionsReady) {
        document.documentElement.dataset.rpTableActionsReady = 'true';
        document.addEventListener('click', (event) => {
          if (openMenu && !event.target.closest('.rp-action-menu')) {
            closeMenu(openMenu);
          }
        });
        window.addEventListener('resize', () => closeMenu(openMenu), {passive: true});
        window.addEventListener('scroll', (event) => {
          if (openMenu && event.target instanceof Node && openMenu.contains(event.target)) {
            return;
          }
          closeMenu(openMenu);
        }, {passive: true, capture: true});
      }
    },
  };
})(Drupal, once);
