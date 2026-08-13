(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.digitalCardThemeColorInputs = {
    attach(context) {
      once('digital-card-theme-color-input', '[data-dc-theme-color-control]', context).forEach((control) => {
        const hex = control.querySelector('[data-dc-theme-color-hex]');
        const picker = control.querySelector('[data-dc-theme-color-picker]');

        if (!hex || !picker) {
          return;
        }

        const normalize = (value) => {
          const cleaned = value.trim().replace(/^#/, '');
          return /^[0-9a-f]{6}$/i.test(cleaned) ? `#${cleaned.toUpperCase()}` : '';
        };

        const updatePicker = () => {
          const value = normalize(hex.value);
          if (value) {
            picker.value = value.toLowerCase();
          }
        };

        hex.addEventListener('input', updatePicker);
        hex.addEventListener('paste', () => window.setTimeout(updatePicker, 0));
        hex.addEventListener('blur', () => {
          const value = normalize(hex.value);
          if (value) {
            hex.value = value;
            picker.value = value.toLowerCase();
          }
        });

        picker.addEventListener('input', () => {
          hex.value = picker.value.toUpperCase();
          hex.dispatchEvent(new Event('input', { bubbles: true }));
        });

        updatePicker();
      });
    },
  };
})(Drupal, once);

