# Ropleon Drupal 10 Responsive Page Specifications

## Breakpoints
- XS: 0–575 px
- SM: 576–767 px
- MD: 768–991 px
- LG: 992–1199 px
- XL: 1200–1439 px
- XXL: 1440 px+

## Global shell
Desktop: max content width 1200–1280 px, 24–32 px page gutters, 72–80 px header.
Mobile: 16 px gutters, 60–64 px header, one-column flow, touch targets >= 44x44 CSS px.

## Corporate home
Desktop hero: two-column 7/5 split. Content first, product visual second.
Mobile: single column; heading 36–40 px, body 18 px; CTA buttons stack below 420 px.
Sections: Hero → Products → Capabilities → Trust/CTA → Footer.

## Ropleon Cards product page
Desktop hero: 6/6 split; product value proposition + card visual.
Feature grid: 3 columns LG+, 2 columns MD, 1 column <768.
Pricing cards: 3 columns XL, horizontal scroll is NOT allowed; stack instead.
Primary CTA remains visible after feature proof, not fixed over content.

## Platform dashboard
Desktop: sidebar 248 px + main flexible area.
Tablet: sidebar collapses to icon rail or drawer.
Mobile: off-canvas nav; KPI cards one column <576, two columns 576–767.
Tables: priority columns remain visible; secondary data moves to expandable row details.

## Forms
Max input line length: 680 px. Labels always visible. Do not rely on placeholders as labels.
Buttons: primary on right in LTR and left in RTL only when the visual order still matches DOM order.

## Digital business card public page
Max card width 460 px on desktop, width:calc(100% - 24px) on mobile.
Profile image 112–128 px, primary action buttons >=48 px height.
Social links in 2-column grid on narrow screens when labels are shown.
No horizontal scrolling at 320 CSS px.
