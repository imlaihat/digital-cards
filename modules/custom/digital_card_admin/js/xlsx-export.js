(function (window) {
  'use strict';

  const encoder = new TextEncoder();
  const crcTable = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) {
        c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      }
      table[n] = c >>> 0;
    }
    return table;
  })();

  function crc32(bytes) {
    let crc = 0xFFFFFFFF;
    for (const byte of bytes) {
      crc = crcTable[(crc ^ byte) & 0xFF] ^ (crc >>> 8);
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  function little(value, size) {
    const bytes = new Uint8Array(size);
    const view = new DataView(bytes.buffer);
    if (size === 2) {
      view.setUint16(0, value, true);
    }
    else {
      view.setUint32(0, value >>> 0, true);
    }
    return bytes;
  }

  function join(parts) {
    const length = parts.reduce((total, part) => total + part.length, 0);
    const result = new Uint8Array(length);
    let offset = 0;
    parts.forEach((part) => {
      result.set(part, offset);
      offset += part.length;
    });
    return result;
  }

  class ZipWriter {
    constructor() {
      this.files = [];
    }

    add(name, content) {
      const nameBytes = encoder.encode(name);
      const data = typeof content === 'string' ? encoder.encode(content) : content;
      this.files.push({nameBytes, data, crc: crc32(data), offset: 0});
    }

    finish() {
      const localParts = [];
      let offset = 0;
      this.files.forEach((file) => {
        file.offset = offset;
        const header = join([
          little(0x04034B50, 4), little(20, 2), little(0x0800, 2),
          little(0, 2), little(0, 2), little(0, 2), little(file.crc, 4),
          little(file.data.length, 4), little(file.data.length, 4),
          little(file.nameBytes.length, 2), little(0, 2), file.nameBytes
        ]);
        localParts.push(header, file.data);
        offset += header.length + file.data.length;
      });

      const centralOffset = offset;
      const centralParts = [];
      this.files.forEach((file) => {
        const header = join([
          little(0x02014B50, 4), little(20, 2), little(20, 2),
          little(0x0800, 2), little(0, 2), little(0, 2), little(0, 2),
          little(file.crc, 4), little(file.data.length, 4), little(file.data.length, 4),
          little(file.nameBytes.length, 2), little(0, 2), little(0, 2),
          little(0, 2), little(0, 2), little(0, 4), little(file.offset, 4),
          file.nameBytes
        ]);
        centralParts.push(header);
        offset += header.length;
      });

      const centralSize = offset - centralOffset;
      const end = join([
        little(0x06054B50, 4), little(0, 2), little(0, 2),
        little(this.files.length, 2), little(this.files.length, 2),
        little(centralSize, 4), little(centralOffset, 4), little(0, 2)
      ]);
      return join([...localParts, ...centralParts, end]);
    }
  }

  function xml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');
  }

  function columnName(index) {
    let name = '';
    let current = index + 1;
    while (current) {
      const remainder = (current - 1) % 26;
      name = String.fromCharCode(65 + remainder) + name;
      current = Math.floor((current - 1) / 26);
    }
    return name;
  }

  function imageCandidate(cell) {
    const img = cell.querySelector('img');
    if (img) {
      const source = img.currentSrc || img.src || img.dataset.src || img.dataset.lazySrc || img.getAttribute('data-original');
      if (source) {
        return {source, element: img};
      }
    }
    const svg = cell.querySelector('svg');
    if (svg) {
      return {svg};
    }
    const link = Array.from(cell.querySelectorAll('a[href]')).find((item) =>
      /\.(png|jpe?g|gif|webp|svg)(?:\?|#|$)/i.test(item.href)
    );
    if (link) {
      return {source: link.href, element: link};
    }
    const styled = Array.from(cell.querySelectorAll('*')).find((item) =>
      getComputedStyle(item).backgroundImage && getComputedStyle(item).backgroundImage !== 'none'
    );
    if (styled) {
      const match = getComputedStyle(styled).backgroundImage.match(/^url\(["']?(.*?)["']?\)$/);
      if (match?.[1]) {
        return {source: match[1], element: styled};
      }
    }
    return null;
  }

  async function convertToPng(blob) {
    const bitmap = await createImageBitmap(blob);
    const max = 512;
    const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    const png = await new Promise((resolve, reject) =>
      canvas.toBlob((result) => result ? resolve(result) : reject(new Error('PNG conversion failed')), 'image/png')
    );
    return new Uint8Array(await png.arrayBuffer());
  }

  async function readImage(candidate, index) {
    try {
      let blob;
      if (candidate.svg) {
        let markup = new XMLSerializer().serializeToString(candidate.svg);
        if (!markup.includes('xmlns=')) {
          markup = markup.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
        }
        blob = new Blob([markup], {type: 'image/svg+xml'});
      }
      else {
        const response = await fetch(candidate.source, {credentials: 'same-origin', cache: 'force-cache'});
        if (!response.ok) {
          return null;
        }
        blob = await response.blob();
      }
      let extension;
      let bytes;
      if (blob.type.includes('png')) {
        extension = 'png';
        bytes = new Uint8Array(await blob.arrayBuffer());
      }
      else if (blob.type.includes('jpeg') || blob.type.includes('jpg')) {
        extension = 'jpeg';
        bytes = new Uint8Array(await blob.arrayBuffer());
      }
      else {
        extension = 'png';
        bytes = await convertToPng(blob);
      }
      return {
        bytes,
        extension,
        name: 'image' + index + '.' + extension
      };
    }
    catch (error) {
      return null;
    }
  }

  async function exportTable(table, visibleRows, excludedColumns, title) {
    const headerRow = table.tHead?.rows[table.tHead.rows.length - 1] || null;
    const sourceRows = [];
    if (headerRow) {
      sourceRows.push({row: headerRow, header: true});
    }
    visibleRows.forEach((row) => sourceRows.push({row, header: false}));

    const rows = [];
    const images = [];
    let detectedImages = 0;
    let failedImages = 0;
    let imageIndex = 1;

    for (let rowIndex = 0; rowIndex < sourceRows.length; rowIndex++) {
      const source = sourceRows[rowIndex];
      const cells = [];
      for (const sourceCell of Array.from(source.row.cells)) {
        if (excludedColumns.has(sourceCell.cellIndex) ||
          sourceCell.classList.contains('views-field-operations') ||
          /^actions?$/i.test(sourceCell.textContent.trim())) {
          continue;
        }
        const candidate = imageCandidate(sourceCell);
        const cell = {
          text: sourceCell.textContent.replace(/\s+/g, ' ').trim(),
          image: null
        };
        if (candidate) {
          detectedImages++;
          const image = await readImage(candidate, imageIndex);
          if (image) {
            image.row = rowIndex;
            image.col = cells.length;
            cell.image = image;
            images.push(image);
            imageIndex++;
          }
          else {
            failedImages++;
          }
        }
        cells.push(cell);
      }
      rows.push({cells, header: source.header});
    }

    const columnCount = Math.max(1, ...rows.map((row) => row.cells.length));
    const widths = Array.from({length: columnCount}, (_, col) => {
      const maximum = Math.max(10, ...rows.map((row) => (row.cells[col]?.text || '').length));
      return Math.min(45, maximum + 3);
    });
    const lastCell = columnName(columnCount - 1) + Math.max(1, rows.length);

    const sheetRows = rows.map((row, rowIndex) => {
      const cells = row.cells.map((cell, colIndex) => {
        const reference = columnName(colIndex) + (rowIndex + 1);
        return '<c r="' + reference + '" t="inlineStr" s="' + (row.header ? 1 : 0) +
          '"><is><t xml:space="preserve">' + xml(cell.text) + '</t></is></c>';
      }).join('');
      const hasImage = row.cells.some((cell) => cell.image);
      return '<row r="' + (rowIndex + 1) + '"' + (hasImage ? ' ht="64" customHeight="1"' : '') + '>' + cells + '</row>';
    }).join('');

    const drawingTag = images.length ? '<drawing r:id="rId1"/>' : '';
    const worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' +
      'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
      '<dimension ref="A1:' + lastCell + '"/><sheetViews><sheetView workbookViewId="0"/></sheetViews>' +
      '<sheetFormatPr defaultRowHeight="18"/><cols>' +
      widths.map((width, index) => '<col min="' + (index + 1) + '" max="' + (index + 1) + '" width="' + width + '" customWidth="1"/>').join('') +
      '</cols><sheetData>' + sheetRows + '</sheetData><autoFilter ref="A1:' + columnName(columnCount - 1) + '1"/>' +
      drawingTag + '</worksheet>';

    const zip = new ZipWriter();
    const imageDefaults = [
      images.some((image) => image.extension === 'png') ? '<Default Extension="png" ContentType="image/png"/>' : '',
      images.some((image) => image.extension === 'jpeg') ? '<Default Extension="jpeg" ContentType="image/jpeg"/>' : ''
    ].join('');
    zip.add('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>' +
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
      '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
      '<Default Extension="xml" ContentType="application/xml"/>' + imageDefaults +
      '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
      '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' +
      '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' +
      (images.length ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '') +
      '</Types>');
    zip.add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>' +
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' +
      '</Relationships>');
    zip.add('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>' +
      '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
      '<sheets><sheet name="' + xml(String(title || 'Records').slice(0, 31)) + '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    zip.add('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>' +
      '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
      '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' +
      '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' +
      '</Relationships>');
    zip.add('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>' +
      '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
      '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>' +
      '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill></fills>' +
      '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' +
      '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
      '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' +
      '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs>' +
      '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    zip.add('xl/worksheets/sheet1.xml', worksheet);

    if (images.length) {
      zip.add('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>' +
        '</Relationships>');

      const anchors = images.map((image, index) =>
        '<xdr:oneCellAnchor><xdr:from><xdr:col>' + image.col + '</xdr:col><xdr:colOff>95250</xdr:colOff><xdr:row>' + image.row + '</xdr:row><xdr:rowOff>47625</xdr:rowOff></xdr:from>' +
        '<xdr:ext cx="571500" cy="571500"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' + (index + 1) + '" name="Table image ' + (index + 1) + '"/><xdr:cNvPicPr/></xdr:nvPicPr>' +
        '<xdr:blipFill><a:blip r:embed="rId' + (index + 1) + '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>' +
        '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="571500" cy="571500"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>'
      ).join('');
      zip.add('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8"?>' +
        '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
        anchors + '</xdr:wsDr>');
      zip.add('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>' +
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
        images.map((image, index) => '<Relationship Id="rId' + (index + 1) + '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' + image.name + '"/>').join('') +
        '</Relationships>');
      images.forEach((image) => zip.add('xl/media/' + image.name, image.bytes));
    }

    const bytes = zip.finish();
    const blob = new Blob([bytes], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    const fileName = (title || 'records').toLocaleLowerCase().replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'records';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileName + '-' + new Date().toISOString().slice(0, 10) + '.xlsx';
    document.body.appendChild(link);
    link.click();
    const url = link.href;
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    return {images: images.length, detectedImages, failedImages, rows: Math.max(0, rows.length - (headerRow ? 1 : 0))};
  }

  window.DigitalCardXlsx = {exportTable};
})(window);
