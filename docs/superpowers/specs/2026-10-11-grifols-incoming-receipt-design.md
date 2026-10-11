# Incoming Receipt for Grifols Diagnostics Product — Design

## Intent

Medquest receives Grifols Diagnostics products on a separate controlled form
(MJG-FOR-SCM.02-002-08, "Incoming Receipt Inspection for Grifols Diagnostics
Product") with extra inspection fields (serial no, cases, packaging
observation, cold-chain bands) and a different sign-off chain. This design
adds that form as a **variant** of the existing Incoming Receipt feature
(branch `feat-incoming-receipt-batch-core`), reusing the batch-centric core
(DocumentNumberService, BatchResolver, SignOffService, location scoping) with
zero changes to it.

Why: the whole point of the batch core built in the previous plan was that
forms 2–8 become form-definition work. This is the first proof.

## Goals

- Two Filament resources in the `Incoming` nav group:
  - `Incoming Receipts` (existing resource, standard variant)
  - `Incoming Receipts Grifols` (new resource, grifols variant)
- Grifols receipts store extra per-line data: serial no, no. of cases,
  packaging observation.
- Grifols receipt numbers are **typed by the user** (unique still enforced,
  sample pattern `0013/WH/1/PB/GFL/MJG/IV/26`). Standard receipts stay
  auto-numbered.
- First step `received_by` is auto-signed on create (same as form 1).
- A `disposition` field (Released/Quarantine), nullable, printed fallback
  `N/A`; visible to Supply Chain roles.
- Grifols sign-off chain per the paper form, printed with the form's own
  wording.
- Printable PDF matching the Grifols paper layout, same pipeline as form 1
  (Browsershot, A4 landscape).
- Every Grifols line still resolves to a Batch — same core semantics.

## Non-goals

- No changes to form 1 behaviour, numbering, or print output.
- No changes to `BatchResolver`, `SignOffService`, `DocumentNumberService`.
- No new roles beyond what seeding already provides (mapped, see below).
- No SAP integration (still the placeholder import).
- No new migration of historical data.

## Constraints

- Laravel 13, Filament 5, PHP 8.4, MariaDB. Work continues on branch
  `feat/incoming-receipt-batch-core` in
  `~/www/projects/mjg-scm/.worktrees/feat-incoming-receipt-batch-core`.
- Pre-existing test baseline: 16 failed / 89 passed; must not worsen.
- Role names in `RoleSeeder` are the authority for authorization; printed
  labels come from `config/documents.php`, which may differ (deliberate).

## Data model

### Migration 1 — `incoming_receipts.variant`

- Add `variant` string column, default `'standard'`, after `receipt_no`.
  Values: `standard`, `grifols`. String + backfill, not an enum — same
  reasoning as the existing `incoming_type` string column.
- Index `(variant)` for resource scoping.
- Existing rows keep `standard`.

### Migration 2 — `incoming_receipt_lines` Grifols columns

All nullable (form 1 lines never set them):

- `part_no` string nullable — Grifols part number (form 2 "Part No." column).
- `serial_no` string nullable — Grifols kit serial (form 2 column "Serial
  No."). Same scope as other line fields: one per line.
- `no_of_cases` unsignedInteger nullable — form 2 column "No. of Cases".
- `packaging_observation` text nullable — free-text observation of packaging
  (cold-chain colour indicators, odor, etc.).

Principal code needs **no new column**: the line already snapshots
`internal_code` and the receipt/product joins to `principals`; form 2's
"Principal" column prints the product's principal name, same as form 1's
existing principal display.

### DocumentNumberService

No change. Grifols receipts bypass it: `CreateGrifolsReceipt` does not call
`issue()`; the form validates `receipt_no` required + unique on
`incoming_receipts.receipt_no`. The `document_sequences` table simply gains no
`grifols_receipt` row.

## Config — `config/documents.php`

### form_metadata

```php
'incoming_receipt_grifols' => [
    'doc_no' => 'MJG-FOR-SCM.02-002-08',
    'effective_date' => '16 OCT 2025',
    'revision' => '06',
],
```

### signoff_steps — new key `incoming_receipt_grifols`

Order per the paper form; `label` uses the template's wording (printed
exactly), `role` maps to existing seeded roles:

| step key | printed label | role |
|---|---|---|
| `received_by` | Received by | Supply Chain Officer |
| `inspected_by` | Inspected by (QA personnel) | QA Staff |
| `verified_by` | Verified by (QA Supervisor) | QA Supervisor |
| `checked_by_scm` | Checked by (Supply Chain Manager) | Supply Chain Manager |
| `disposition` | Disposition (by Grifols SCM) | Supply Chain Manager |
| `sap_entry_by` | Entry data to the SAP System | Supply Chain Administration System Staff |
| `verified_by_whm` | Verified by (Warehouse Manager) | Warehouse Manager |

Steps after `checked_by_scm` belong to the paper form's DISPOSITION block.
They follow the same ordered signing; `verified_by_whm` may remain unsigned
without blocking (the paper form leaves it open too). New roles seeded:
`Supply Chain Administration System Staff`, `Warehouse Manager`.

`SignOffService` is config-driven; the new key needs no code change. Roles
`QA Supervisor` and `Supply Chain Staff` must exist in `RoleSeeder` (add if
missing — additive, no effect on existing users).

Number format: none (manual). `formats` gains no entry.

## Services / domain

Zero changes. `HasSignOffs` on `IncomingReceipt` reads
`config('documents.signoff_steps')` by `document_type`; the model's document
type becomes `incoming_receipt` for standard and `incoming_receipt_grifols`
for grifols (a model accessor keyed off `variant` — one attribute, two config
keys, no second model).

## Filament — two resources, one model

### `IncomingReceiptResource` (existing)

- Query: where `variant = 'standard'`.
- Nav unchanged (`Incoming` group, existing icon/label).
- Create page unchanged (auto numbering).
- Sign-off actions already config-driven; nothing to change.

### `GrifolsReceiptResource` (new)

Same directory shape as the existing resource
(`app/Filament/Resources/IncomingReceiptGrifols/`):

- Resource class over `IncomingReceipt` model, `canViewAny` same role list,
  `canEdit`/`canDelete` mirroring the existing signed-receipt lock (Super
  Admin override, edit_override sign-off recorded — same rules).
- Nav group `Incoming`, label `Incoming Receipts Grifols`, distinct icon.
- `getEloquentQuery` applies `HasLocationBasedAccess` scoping (same trait).
- Form (`GrifolsReceiptForm`):
  - Receipt Identity: `receipt_no` **TextInput, required, unique** (user
    typed), location, date, time, incoming type, PO no.
  - Parties: supplier (Grifols defaults seeded), carrier, shipper address.
  - Inspection: measured_by_device, temp result, record ref, storage
    requirement (help text: cold-chain bands 2–8°C / 16–25°C as printed on
    the form), product status, remark.
  - Lines Repeater: product select, **part_no**, lot, expiry, quantity, packing
    condition, **serial_no**, **no_of_cases**, **packaging_observation**, registration
    label + QA by. Batch resolution identical to form 1 (same
    `handleRecordCreation` transaction shape).
- Create page: same transactional shape as `CreateIncomingReceipt` (number
  issuance step removed), sets `variant = 'grifols'`.
- View page: sign-off header actions generated from the grifols config key;
  infolist includes the extra line columns.
- Table: same columns as form 1 plus serial/cases; receipt_no column shows
  the typed number.

Shared behaviour (edit lock, sign-off actions, location scope) is copied with
adaptation, not abstracted — the two forms are expected to diverge (YAGNI on
a shared base class until form 3 arrives).

## Print

`resources/views/print/incoming-grifols.blade.php` + route
`/dashboard/incoming-receipts-grifols/{record}/print`:

- Header: Medquest block + `MJG-FOR-SCM.02-002-08`, title "INCOMING RECEIPT
  INSPECTION FOR GRIFOLS DIAGNOSTICS PRODUCT".
- Same paper layout approach as form 1 (fixed table cells, sign-off footer
  grid from config steps, `&ensp;` fallbacks, escaped output).
- Line table adds Part No., Serial No., No. of Cases, Observation of
  Packaging Product columns. Disposition block prints `disposition ?? 'N/A'`.
- Filename from receipt_no, sanitized identically to form 1.

## Error handling

- Duplicate typed receipt_no → Filament unique validation, no write.
- Batch merge conflict → same retry-once-on-1062 as form 1.
- Sign-off out of order / wrong role → `SignOffException` → danger
  notification (existing behaviour, config-driven).
- Edit after signing → same `canEdit` lock + edit_override row.

## Testing

- Migration: column presence + default (`standard` for old rows).
- `GrifolsReceiptResource` feature tests (mirror the form-1 suite):
  - officer can create (typed receipt_no unique; duplicate rejected)
  - lines create/merge batches identically (BatchResolver invoked with the
    same args)
  - sign-off order per grifols config; wrong role denied
  - signed receipt edit lock + edit_override row
  - location scoping (officer sees own location only)
  - print route renders doc_no MJG-FOR-SCM.02-002-08 and the extra columns
- Existing form-1 suite untouched and still green.

## Open decisions made by the builder (flagged for the human)

1. **`document_type` via accessor** keyed off `variant` rather than a second
   model — one table, one model, two config identities.
2. **Copy-not-abstract** for the second resource's pages — abstraction waits
   for form 3.
3. **`QA Supervisor` / `Supply Chain Staff`** seeded additively if absent.
4. Serial number is **one string per line** (form shows one serial column;
   multi-serial kits would be separate lines).
