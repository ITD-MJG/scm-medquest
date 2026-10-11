# Grifols Incoming Receipt — Implementation Plan

Branch: `feat/incoming-receipt-batch-core` in
`~/www/projects/mjg-scm/.worktrees/feat-incoming-receipt-batch-core`.
Spec: `docs/superpowers/specs/2026-10-11-grifols-incoming-receipt-design.md`.
Test command: `php vendor/bin/pest <path>`. Baseline: 16 failed / 89 passed —
must not worsen. Pint before every commit: `./vendor/bin/pint --dirty`.

House patterns to copy from (form 1):
- Resource dir: `app/Filament/Resources/IncomingReceipts/`
- Create transaction: `Pages/CreateIncomingReceipt.php` (`handleRecordCreation`)
- Sign-off actions: `Pages/ViewIncomingReceipt.php` (`getSignOffActions`)
- Edit lock + override: `IncomingReceiptResource::canEdit/canDelete` +
  `Pages/EditIncomingReceipt.php` (`recordEditOverride`)
- Print: `resources/views/print/incoming-receipt.blade.php`, route in `routes/web.php`
- Tests: `tests/Feature/IncomingReceiptAccessTest.php`, `IncomingReceiptPrintTest.php`,
  `tests/Unit/...` as needed. Role seeding: `beforeEach(fn () => $this->seed(RoleSeeder::class))`.

---

### Task 1: Schema — variant + Grifols line columns

**Files:**
- Create: `database/migrations/2026_10_11_100001_add_variant_to_incoming_receipts_table.php`
- Create: `database/migrations/2026_10_11_100002_add_grifols_columns_to_incoming_receipt_lines_table.php`
- Create: `database/migrations/2026_10_11_100003_add_disposition_to_incoming_receipts_table.php`
- Modify: `app/Models/IncomingReceipt.php` (fillable + `disposition` cast if any)
- Modify: `app/Models/IncomingReceiptLine.php` (fillable)
- Test: `tests/Feature/GrifolsSchemaTest.php`

Migration 1: `$table->string('variant')->default('standard')->index()` after `receipt_no`.
Migration 2 (on lines): `part_no` string nullable, `serial_no` string nullable,
`no_of_cases` unsignedInteger nullable, `packaging_observation` text nullable.
Migration 3: `disposition` string nullable on receipts (default null — the form
default is the PRINT fallback `N/A`, not a stored value).

Model: add `variant` to fillable; add accessor:

```php
public function documentType(): string
{
    return $this->variant === 'grifols' ? 'incoming_receipt_grifols' : 'incoming_receipt';
}
```

(Rename/replace the existing `documentType()` if it is hardcoded — verify first;
`signoffs()` and SignOffService already call `$document->documentType()`.)

Tests: migrate fresh in test env; assert columns exist with correct types/nullability;
old row (create with no variant) reads back `standard`.

**Commit:** `feat: add grifols variant columns to incoming receipts`

---

### Task 2: Config + roles

**Files:**
- Modify: `config/documents.php` — `form_metadata['incoming_receipt_grifols']`
  (doc_no `MJG-FOR-SCM.02-002-08`, effective `16 OCT 2025`, revision `06`);
  `signoff_steps['incoming_receipt_grifols']`:

| key | label (printed) | role |
|---|---|---|
| `received_by` | Received by | Supply Chain Officer |
| `inspected_by` | Inspected by | QA Staff |
| `verified_by` | Verified by | QA Supervisor |
| `checked_by_scm` | Checked by | Supply Chain Manager |
| `disposition` | Disposition (by Grifols SCM) | Supply Chain Manager |
| `sap_entry_by` | Entry data to the SAP System | Supply Chain Administration System Staff |
| `verified_by_whm` | Verified by | Warehouse Manager |

- Modify: `database/seeders/RoleSeeder.php` — add `QA Supervisor`,
  `Supply Chain Administration System Staff`, `Warehouse Manager` (create-first,
  idempotent).
- Test: `tests/Unit/GrifolsSignoffConfigTest.php` — steps exist in order, labels exact.

No SignOffService change (verified config-driven; strict ordering; last step
unsigned blocks nothing).

**Commit:** `feat: add grifols sign-off chain and roles`

---

### Task 3: Grifols resource — form, create, table

**Files:**
- Create: `app/Filament/Resources/IncomingReceiptGrifols/GrifolsReceiptResource.php`
- Create: `.../Schemas/GrifolsReceiptForm.php`
- Create: `.../Pages/{ListGrifolsReceipts,CreateGrifolsReceipt,EditGrifolsReceipt,ViewGrifolsReceipt}.php`
- Create: `.../Tables/GrifolsReceiptsTable.php`
- Test: `tests/Feature/GrifolsReceiptResourceTest.php`

Resource: model `IncomingReceipt`, nav group `Incoming`, label
`Incoming Receipts Grifols`, slug `incoming-receipts-grifols`,
`getNavigationBadge` none. `canViewAny` same roles as form 1 resource.
`getEloquentQuery`: `->where('variant', 'grifols')` + `HasLocationBasedAccess`
scope (copy from form 1 resource). `canEdit`/`canDelete` same signed-lock rules.

Form: copy `IncomingReceiptForm`, changes:
- `receipt_no`: TextInput, required, `unique` rule (table-wide; add
  `unique(ignoreRecord: true)` on edit) — NO `disabled`/placeholder.
- Receipt line repeater adds: `part_no` (TextInput nullable), `serial_no`,
  `no_of_cases` (numeric nullable), `packaging_observation` (Textarea nullable).
- `dehydrated` variant hidden field not needed — create page sets it.

Create page: copy `CreateIncomingReceipt`; in `handleRecordCreation`:
- REMOVE `DocumentNumberService::issue(...)` call — `receipt_no` comes from data.
- `data['variant'] = 'grifols'` before create.
- KEEP batch resolution + 1062 retry + auto-sign `received_by` (form 1
  auto-signs its first step `received_and_processed` — mirror that).
- Keep `EditGrifolsReceipt` with `recordEditOverride` (same override row rules).

Table: copy form 1's, add `part_no`/`serial_no`/`no_of_cases` columns on the
lines relation (or drop them from the table — lines detail lives on View page;
match form 1's table and only vary receipt-level columns).

Tests (mirror form-1 suite):
- officer create with typed `receipt_no` → persisted, `variant=grifols`,
  `received_by` sign-off row exists, batches created
- duplicate `receipt_no` rejected (validation error, no rows)
- officer list shows only grifols receipts; standard receipts invisible here
- form 1 list shows only `standard` receipts
- edit lock: signed receipt, officer denied, Super Admin allowed + edit_override row

**Commit:** `feat: add Grifols incoming receipt resource`

---

### Task 4: View page + sign-off actions

**Files:**
- Modify: `app/Filament/Resources/IncomingReceiptGrifols/Pages/ViewGrifolsReceipt.php`
- Test: extend `tests/Feature/GrifolsReceiptResourceTest.php`

Copy `ViewIncomingReceipt`; `getSignOffActions` reads
`config('documents.signoff_steps.incoming_receipt_grifols')`. Infolist adds
line columns `part_no`, `serial_no`, `no_of_cases`, `packaging_observation`
and receipt `disposition` (display `N/A` when null).

Tests: each step signs in order by a user with the configured role; wrong role
denied (danger notification); out-of-order denied; `disposition` step by SCM.

**Commit:** `feat: grifols view page with sign-off actions`

---

### Task 5: Print view + route

**Files:**
- Create: `resources/views/print/incoming-grifols.blade.php`
- Modify: `routes/web.php` — print route (filename sanitized, location check,
  same pattern as form 1)
- Test: `tests/Feature/GrifolsReceiptPrintTest.php`

Layout: copy `incoming-receipt.blade.php`; header doc_no
`MJG-FOR-SCM.02-002-08`, revision `06`, title `INCOMING RECEIPT INSPECTION FOR
GRIFOLS DIAGNOSTICS PRODUCT`. Line table gains Part No., Serial No., No. of
Cases, Observation of Packaging Product columns. Sign-off footer renders the
7 grifols steps (two visual rows matching the paper form: receipt chain row 1,
disposition block row 2 — but data comes from the config order). Disposition
value printed as `{{ $receipt->disposition ?? 'N/A' }}`. All output escaped
(`{{ }}`), `&ensp;` fallbacks like form 1. Print route denial: 403 via
location check (converted to logout.403 redirect by middleware).

Tests: renders doc_no + revision + title; line fields appear when set, `-`
fallback absent; `N/A` when disposition null; officer cannot print another
location's receipt (assertRedirect logout.403).

**Commit:** `feat: add grifols receipt print view`

---

### Task 6: Whole-branch verify + review

- Full suite: 16 failed / N passed, no new failures beyond baseline.
- `./vendor/bin/pint --dirty` clean.
- Dispatch reviewer subagent over the task diffs (spec compliance +
  cross-cutting: two resources cannot see each other's rows; sign-off config
  correct; print matches paper form fields from `/tmp/grifols-ocr.txt`).
- Fix round if Important findings; re-review.
