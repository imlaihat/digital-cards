# Accessibility and RTL Requirements

Target: WCAG 2.2 AA for public pages and authenticated platform UI.

## Accessibility
- Text contrast >= 4.5:1; large text >= 3:1; non-text controls/borders >= 3:1 where required.
- All interactive controls must be keyboard reachable and have a visible focus indicator.
- Minimum target size: 24x24 CSS px under WCAG 2.2; Ropleon UI standard is 44x44 px where practical.
- Every form control has a programmatic label; errors are associated with aria-describedby.
- Do not use color as the only state indicator.
- Modal/dialog components trap focus correctly and return focus to the triggering element.
- Skip link targets main content.
- Decorative images use empty alt. Informative images have concise alt.
- Respect prefers-reduced-motion.
- Status changes that occur without navigation use aria-live where appropriate.
- SVG UI icons use currentColor and must have aria-hidden="true" when a visible text label exists.

## RTL / Arabic
- Set html dir="rtl" lang="ar" for Arabic pages.
- Use CSS logical properties: margin-inline, padding-inline, inset-inline, border-inline.
- Do not flip brand logos, QR codes, media, phone numbers, email addresses, or Latin product names.
- Direction-isolate mixed strings with <bdi> or CSS unicode-bidi:isolate.
- Numbers remain readable in their source direction.
- Chevron/back/forward icons may mirror; neutral icons do not.
- Tables align text by language while numeric columns remain consistent.
- Form validation icons must not overlap field text in RTL.
- Avoid hard-coded left/right except for genuinely physical direction.
