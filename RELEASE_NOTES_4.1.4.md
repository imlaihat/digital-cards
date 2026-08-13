# Ropleon Cards 4.1.4 - Workflow Form Recovery

This hotfix restores Digital Business Card create, edit, and translation forms
after legacy Workflow state IDs caused the contributed Workflow widget to load a
missing state.

## Fixes

- Repairs `card_workflow_pending` to the configured **Waiting Approval** state.
- Repairs `card_workflow_rejected` to the configured **Draft** state.
- Repairs any other non-empty obsolete card state to the configured creation
  state while leaving valid states unchanged.
- Repairs matching current and revision field rows so historical card forms do
  not retain an unusable state reference.
- Replaces unbounded Form API recursion with bounded options-widget handling.
- Treats Drupal's source language as immutable after the first card save.
- Keeps organization defaults and the per-card override responsible for source
  language selection only while creating a new card.
- Does not modify the contributed Workflow module.

## Install

1. Back up the database and `modules/custom/digital_card_delivery`.
2. Extract the ZIP at the Drupal root and allow it to merge/replace the module.
3. While signed in as an administrator, open `/update.php` and run pending
   updates. This executes `digital_card_delivery_update_10005()`.
4. Rebuild caches.
5. Test create, edit, and Translate forms for Digital Business Cards.

On this Windows/UniServerZ environment, use `/update.php` when Drush `updb`
fails because `sh` is unavailable. Cache rebuild can still be run with:

```powershell
& $php .\vendor\drush\drush\drush.php cr
```

## Expected repair on the audited local database

- Card 37: Pending (obsolete) -> Waiting Approval.
- Card 42: Rejected (obsolete) -> Draft.

No valid card Workflow status, node ID, translation, organization relationship,
or static-card URL is changed.
