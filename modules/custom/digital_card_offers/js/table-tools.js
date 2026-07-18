(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.digitalCardTableTools = {
    attach(context) {
      once('digital-card-table-tools', '.dco-table, .dc-management-table, .view table', context).forEach((table) => {
        if (!table.tBodies.length || table.dataset.noTableTools === 'true') {
          return;
        }

        const host = table.closest('.dco-table-card, .dc-table-card, .view-content, .table-responsive') || table.parentElement;
        if (!host || host.querySelector(':scope > .dc-table-tools')) {
          return;
        }

        const rows = Array.from(table.tBodies).flatMap((body) => Array.from(body.rows));
        const excludedColumns = new Set();
        const columnInputs = [];

        if (table.tHead && table.tHead.rows.length) {
          const headingRow = table.tHead.rows[0];
          Array.from(headingRow.cells).forEach((cell) => {
            const heading = cell.textContent.trim();
            if (/^(actions?|الإجراءات)$/i.test(heading) || cell.classList.contains('views-field-operations') || cell.classList.contains('views-field-nothing')) {
              excludedColumns.add(cell.cellIndex);
            }
          });

          const filterRow = document.createElement('tr');
          filterRow.className = 'dc-column-filters';
          Array.from(headingRow.cells).forEach((heading, index) => {
            const cell = document.createElement('th');
            const headingText = heading.textContent.trim();
            if (!excludedColumns.has(index) && headingText) {
              const columnInput = document.createElement('input');
              columnInput.type = 'search';
              columnInput.autocomplete = 'off';
              columnInput.dataset.columnIndex = String(index);
              columnInput.placeholder = Drupal.t('Filter @column', {'@column': headingText});
              columnInput.setAttribute('aria-label', Drupal.t('Search @column', {'@column': headingText}));
              cell.appendChild(columnInput);
              columnInputs.push(columnInput);
            }
            filterRow.appendChild(cell);
          });
          table.tHead.appendChild(filterRow);
        }

        const tools = document.createElement('div');
        tools.className = 'dc-table-tools dc-table-tools--column-only';
        tools.innerHTML = [
          '<div class="dc-table-tools__actions">',
          '<span class="dc-table-count" aria-live="polite"></span>',
          '<button type="button" class="dc-table-clear-filters" hidden></button>',
          '<button type="button" class="dc-excel-button"><span aria-hidden="true">&#8681;</span></button>',
          '</div>'
        ].join('');

        const clearFilters = tools.querySelector('.dc-table-clear-filters');
        const count = tools.querySelector('.dc-table-count');
        const exportButton = tools.querySelector('.dc-excel-button');
        const tableLabel = document.querySelector('h1')?.textContent.trim() || Drupal.t('Records');

        clearFilters.textContent = Drupal.t('Clear filters');
        clearFilters.setAttribute('aria-label', Drupal.t('Clear all column filters'));
        exportButton.lastChild.textContent = ' ' + Drupal.t('Export to Excel');

        const updateCount = () => {
          const visible = rows.filter((row) => !row.hidden).length;
          count.textContent = Drupal.formatPlural(visible, '1 record', '@count records');
        };

        const filter = () => {
          rows.forEach((row) => {
            row.hidden = !columnInputs.every((columnInput) => {
              const terms = columnInput.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
              if (!terms.length) {
                return true;
              }
              const index = Number(columnInput.dataset.columnIndex);
              const value = (row.cells[index]?.textContent || '').toLocaleLowerCase().replace(/\s+/g, ' ');
              return terms.every((term) => value.includes(term));
            });
          });
          clearFilters.hidden = columnInputs.every((columnInput) => columnInput.value.length === 0);
          updateCount();
        };

        columnInputs.forEach((columnInput) => columnInput.addEventListener('input', filter));
        clearFilters.addEventListener('click', () => {
          columnInputs.forEach((columnInput) => { columnInput.value = ''; });
          filter();
          columnInputs[0]?.focus();
        });

        exportButton.addEventListener('click', async () => {
          if (!window.DigitalCardXlsx) {
            return;
          }
          const original = exportButton.innerHTML;
          exportButton.disabled = true;
          exportButton.innerHTML = '<span class="dc-export-spinner" aria-hidden="true"></span> ' + Drupal.t('Preparing XLSX…');
          try {
            const result = await window.DigitalCardXlsx.exportTable(
              table,
              rows.filter((row) => !row.hidden),
              excludedColumns,
              tableLabel
            );
            exportButton.innerHTML = result.detectedImages > 0
              ? Drupal.t('Exported @rows rows · @images images', {'@rows': result.rows, '@images': result.images})
              : Drupal.t('Exported @rows rows · no images detected', {'@rows': result.rows});
            window.setTimeout(() => {
              exportButton.innerHTML = original;
              exportButton.disabled = false;
            }, 1600);
          }
          catch (error) {
            exportButton.innerHTML = Drupal.t('Export failed');
            window.setTimeout(() => {
              exportButton.innerHTML = original;
              exportButton.disabled = false;
            }, 2200);
          }
        });

        host.parentNode.insertBefore(tools, host);
        updateCount();
      });
    }
  };
})(Drupal, once);
