# Ropleon static-card reference presets

These three presets (`default`, `executive`, and `minimal`) come from the
approved Ropleon production brand handoff. They are reference inputs for the
existing organization-specific card theme feature; they are intentionally not
activated globally during deployment.

Why they are not copied directly into generated cards:

- The live generator already renders all Digital Business Card fields,
  organization branding, social links, QR/NFC behavior, scanner context,
  offers, and loyalty data.
- Replacing that generated markup with the starter HTML would remove product
  functionality.
- Platform administrators can use the CSS values here when configuring an
  organization's optional custom CSS, then regenerate that organization's
  approved cards.

The production generator remains the source of truth for output and keeps the
supporting `Powered by Ropleon Cards` line required by the brand system.
