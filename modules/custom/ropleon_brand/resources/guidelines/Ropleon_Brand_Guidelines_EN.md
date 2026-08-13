# Ropleon Brand Guidelines — Drupal 10 Production Handoff
Version v1.0.0 · 2026-08-10

## 1. Brand architecture
**Master / corporate:** Ropleon Technologies  
**Corporate signature:** Ropleon approved master + “Technologies” descriptor  
**Corporate tagline:** Technology. Connected.

**Primary product:** Ropleon Cards  
**Product signature:** Ropleon approved master + “Cards” descriptor  
**Product tagline:** Your Identity. Always Connected.

Use the corporate signature on company, legal, careers, partnerships and group-level pages.
Use Ropleon Cards on product marketing, dashboard login, digital-card management and NFC/QR product materials.

## 2. Approved master
The approved Ropleon emblem and wordmark supplied for this handoff must not be redrawn, stretched, recolored arbitrarily, rotated, outlined, shadowed or separated into new geometry.

The color SVGs in this package are self-contained and independent of external fonts. Because the approved source supplied for the master artwork is PNG, the exact-color SVG preserves that approved artwork internally rather than inventing a new vector reconstruction.

## 3. Clear space
Minimum clear space around a signature: the approximate height of the uppercase “R” stem in the wordmark. For UI headers, never allow the logo to touch container edges; minimum practical padding 16 px mobile / 24 px desktop.

## 4. Minimum size
Corporate/product horizontal signatures:
- Digital: 180 px wide minimum where tagline is visible.
- Below 180 px, use a no-tagline or icon-led treatment.
- Favicon/app icon: use the emblem only.

## 5. Color
Primary:
- Deep Navy #00184A
- Brand Navy #00297E
- Digital Blue #007BFF
- Cyan Highlight #00BEFF

Neutrals:
- Ink #101828
- Muted #667085
- Surface #F8FAFC
- Border #D0D5DD

Do not use cyan as body text on white. Use navy for primary links and buttons.

## 6. Typography
Latin UI: Inter.
Arabic UI: Noto Sans Arabic.
Fallbacks are defined in design tokens.
All logo SVGs are independent of external fonts. Website typography remains font-based for performance and accessibility.

## 7. Iconography
Use 24x24 line icons, 1.75 px stroke, rounded caps/joins, `currentColor`.
Directional icons may mirror in RTL. Brand mark, QR, NFC symbol, social logos and non-directional icons do not mirror.

## 8. Photography/illustration
Prefer clean professional technology/business imagery with uncluttered backgrounds and realistic use cases. Avoid generic futuristic HUD effects that reduce credibility.

## 9. Buttons
Primary: Navy background / white label.
Secondary: white/transparent background / navy border or text.
Destructive: danger semantic token.
Min visual height 44 px; loading state must preserve width.

## 10. Cards and surfaces
Use white or surface backgrounds, 10–16 px radius, modest shadows. Dashboard information hierarchy should rely on spacing, typography and borders rather than heavy gradients.

## 11. Ropleon Cards public digital card
Profile identity is primary; platform branding is supporting.
Keep user name/job/title high contrast and immediately visible.
NFC/QR interactions must never be the only path to contact actions.
Generated static HTML must remain usable without JavaScript for core contact data.

## 12. Arabic / RTL
Arabic pages use `dir="rtl"` and logical CSS properties.
Ropleon and Ropleon Cards names remain in their registered Latin form when displayed as brand marks.
Arabic copy may transliterate the names in prose.
Mixed URLs, email addresses and phone numbers should be direction-isolated.

## 13. Accessibility
WCAG 2.2 AA target. Follow the dedicated accessibility document in this handoff.
Do not place essential copy inside raster artwork.
All social-sharing artwork may contain brand text because it is promotional imagery; corresponding web pages still need real HTML headings.

## 14. File selection
SVG: preferred for website logos and UI icons.
PNG: social artwork, application stores, legacy integrations and raster fallback.
ICO: browser fallback favicon.
JSON/CSS tokens: design-system source of truth.
PO/JSON copy: translation/content seed.

## 15. Governance
Any new product follows: `Ropleon + one short functional English word`.
Do not create a new Ropleon symbol for each product.
Product differentiation should come from descriptor, messaging, page system and controlled accent use.

## 16. Drupal implementation
Brand assets must be referenced from the custom theme, never hotlinked.
Use Drupal libraries for CSS/JS, Twig templates for semantic markup, and interface translation for UI strings.
Static `/c/{NFC_ID}` output should reuse the tokenized card-theme CSS and preserve accessibility/RTL behavior.
