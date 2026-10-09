# Incoming Receipt & Batch Core — Design

**Date:** 2026-10-09
**Status:** Draft — awaiting spec review
**Scope:** Core schema + `Incoming Receipt Inspection` (MJG-FOR-SCM.02-002-02, rev 12) end-to-end.
Forms 2–8 are designed-for but not built.

---

## 1. Purpose

Medquest's SCM controlled documents are currently paper, scanned via ScanSnap. That
means no queryability, no expiry alerting, and no traceability from receipt through
relocation, expiry, rejection and destruction.

This change introduces a **batch-centric core** so that a physical batch of product is
a single durable row that every future form attaches to, plus the first form built on
that core: **Incoming Receipt Inspection**.

The core is deliberately designed once so forms 2–8 (Grifols variant, QC Label,
Relocation, Expired, Rejected, Request for Destruction, Destruction Report) become
mostly form-definition work rather than new architecture.

### Success criteria

1. An Incoming Receipt can be entered through the dashboard with the same fields as
   the paper form, including its repeating line items.
2. Each receipt line creates or links a `Batch` row.
3. The receipt prints to PDF matching the Medquest layout, including the
   doc-no / revision / effective-date block, checkbox glyphs and signature cells.
4. Role sign-offs are recorded in-app with user identity and timestamp, and print as
   `initial + date`, consistent with the existing temperature module.
5. A batch's history is traceable: which receipt created it, and (later) which forms
   acted on it.
6. All access respects the existing location-based and role-based rules.

### Explicit non-goals (this phase)

- Forms 2–8 are **not** implemented. Their schema needs are accounted for in the core
  design, nothing more.
- No ongoing SAP synchronisation. SAP is a one-time bootstrap import only.
- No stock-level accounting beyond receipt quantities. No issue/dispatch/consumption.
- No drawn or uploaded signature images. Sign-off is click-to-sign.
- No rework of the existing Temperature & Humidity or Deviation modules, except where
  the shared sign-off/numbering services can later be adopted by them (out of scope).

---

## 2. Context

### Existing stack (verified)

| Concern | Reality |
|---|---|
| Framework | Laravel 13 (`composer.json`), PHP 8.4 |
| Admin | Filament 5, panel id `dashboard`, path `/dashboard` |
| DB | MariaDB, database `medquest_scm` |
| Permissions | `spatie/laravel-permission` |
| PDF | `spatie/browsershot` (`Browsershot::html()` in `routes/web.php`) |
| Excel | `maatwebsite/excel` |
| Tests | Pest 4 |

> `GEMINI.md` claims Laravel v12 / Filament v4. That file is stale. `composer.json`
> is authoritative.

### Existing patterns to reuse

- **Location scoping** — `App\Traits\HasLocationBasedAccess` provides
  `applyLocationFilter()`, `getAccessibleLocationIds()`, `canAccessLocation()`.
  Already used via `getEloquentQuery()` in `TemperatureDeviationResource`.
  Note the trait has a latent bug: it references role `'QA Supervisor'` which is **not**
  seeded in `RoleSeeder`. See §10.
- **Two-step approval** — `TemperatureHumidity` uses
  `is_reviewed` / `reviewed_by` / `reviewed_at` and
  `is_acknowledged` / `acknowledged_by` / `acknowledged_at`.
  This design generalises that idea rather than copying the columns.
- **Signature as initial + date** — `TemperatureHumidity::formatPicSignature()`
  renders `initial + strtoupper(created_at->format('d M Y'))`, with `Security` role
  showing the full name instead.
- **Print pipeline** — `resources/views/print/header/*.blade.php` and
  `resources/views/print/temperature-deviation.blade.php` are the templates to copy.
  Bootstrap CSS is vendored at `public/assets/bootstrap/css/bootstrap.min.css`.
  Logo at `public/assets/images/LOGO-MEDQUEST-HD.png`.
- **Notifications** — `Filament\Notifications\Notification::sendToDatabase()` used in
  `CreateTemperatureHumidity::afterCreate()`.

### Roles (verified seeded — `database/seeders/RoleSeeder.php`)

`Super Admin`, `Admin`, `Supply Chain Manager`, `Supply Chain Officer`, `QA Manager`,
`QA Staff`, `Security`.

Code additionally references `'Staff'` and `'QA Supervisor'`, neither of which is
seeded. `'Staff'` appears only in `HasLocationBasedAccess`; `'QA Supervisor'` appears in
that trait and in `User::hasAccessToLocation()` / `getAccessibleLocationIds()`.

### What the Incoming Receipt form contains

Read from the scanned form (`1. Incoming.pdf`).

**Header block**
- Receipt No. — `0835/WH/2/PB/MJG/VIII/26`
- Purchase Order No. — `22800393`
- Receipt Date / Time — `05 AUG 2026 / 1050`
- Carrier — `PT. JAYA GUNA JATI`
- Shipper Name — `PT. JAYA GUNA JATI`
- Shipper Address — `TANGERANG`
- Type of Incoming Products — New Inventory (PO) / New Inventory (Non PO) / Return / Others
- Measured by Temperature Devices? — Yes/No, device N/A
- Product temperature inspection results? — value + `°C`, temperature records see
- Storage Requirement — 15–30°C / 15–25°C / 2–8°C / -25 to -10°C / -35 to -15°C
- Unpacking Time — Start (date/time), Finish (date/time); note "for critical temperature
  products, max. 15 minutes per coli"

**Line items (repeating)**

| Column | Example |
|---|---|
| Internal / Principal Code No. | `PAC-N-00008` |
| Product Description | `KARTON LEMBARAN DOUBLE K150/M X 4 UKURAN 48 X 31 CM` |
| Lot / Batch / Serial No. | `N/A` |
| Expiry Date | `N/A` |
| Quantity | `200` |
| UoM | `PCS` |
| Physical and Packing Condition | `BAIK` |
| Registration Label — No. | `N/A` |
| Registration Label — QA Verification | initial |
| Function Test — Result | `N/A` |
| Function Test — Performed by | `N/A` |
| Storage Location Plan | `AREA PACKAGING` |

**Footer block**
- Received and Processed by — Supply Chain Officer
- Checked by — Supply Chain Coordinator
- Inspected by — Quality Assurance Personnel
- Verified by — Quality Assurance Supervisor
- Product Status — Quarantine / Released; Defined by — Quality Assurance Manager
- Acknowledged by — Supply Chain Manager
- Entry data to the SAP System performed by — Supply Chain Administration System Staff
- Remark — free text (`Stock packaging gudang cabang Sidoarjo`)

**Document metadata block:** Doc. No. `MJG-FOR-SCM.02-002-02`, Effective Date
`16 OCT 2025`, Revision No. `12`, Page `01 of 01`.

### SAP

SAP ECC / S4HANA on HANA, read access available. Consumption model chosen:
**one-time bootstrap import, then local manual management** — because some incoming items
are never uploaded to SAP.

Consequences:

- Local tables are **authoritative and user-editable**.
- Provenance is tracked per product: `source` (`sap` | `manual`) and nullable `sap_code`.
- Re-running the import must never clobber locally edited rows.
- The import is an **adapter with a config-driven table/view mapping**, left unset until
  the SAP table list is supplied. Nothing else blocks on it.

**Deployment prerequisite (needs sign-off):** HANA over ODBC requires the SAP HANA ODBC
client installed at system level. PHP has `PDO_ODBC` but **not** `pdo_sqlsrv`. This is a
server install, not a composer dependency. GEMINI.md gates dependencies on approval.

---

## 3. Approaches considered

**1. Batch-centric core + generic sign-offs — CHOSEN.**
One `Batch` row per physical batch; forms attach to it. Generic `document_signoffs` and
`document_sequences` serve all 8 forms. Costs ~1 week of core before the first form ships,
but forms 2–8 then cost days each instead of ~3 days each with no compounding benefit.

**2. Form-centric, per-form tables — rejected.**
Fastest single form (~3–4 days), mirrors the existing `TemperatureHumidity` style. But
there is no stable batch entity, so Expired → Request → Report cannot reference anything
and product data gets re-keyed per form. Sign-off roles would be hardcoded 8 times.
Since 8 forms are known to be coming, this guarantees rework.

**3. Single table + JSON lines — rejected.**
~1–2 days, but not queryable at line level, no expiry alerting, no traceability, and does
not carry the other forms. Only defensible if the goal were "stop losing the paper".

---

## 4. Architecture

Three layers, each independently understandable and testable.

```
┌─────────────────────────────────────────────────────────────┐
│ Presentation — Filament resources & pages                   │
│  ProductResource, PrincipalResource, SupplierResource,      │
│  CarrierResource, StorageAreaResource, BatchResource,       │
│  IncomingReceiptResource                                    │
├─────────────────────────────────────────────────────────────┤
│ Domain services — no Filament, no HTTP                      │
│  DocumentNumberService   issues receipt numbers             │
│  BatchResolver           create-or-merge a batch            │
│  SignOffService          record sign-offs, advance status   │
│  Sap\ProductImportService  one-time bootstrap (adapter)     │
├─────────────────────────────────────────────────────────────┤
│ Persistence — Eloquent models + migrations                  │
│  Product Principal Supplier Carrier StorageArea Batch       │
│  IncomingReceipt IncomingReceiptLine                        │
│  DocumentSequence DocumentSignoff                           │
└─────────────────────────────────────────────────────────────┘
```

Rationale for the split: the services hold the rules that are easy to get wrong and
expensive to get wrong — number uniqueness, batch merging, sign-off ordering. Keeping them
free of Filament means they can be unit-tested directly, which the existing
`TemperatureDeviationNotificationService` already demonstrates as the house pattern.

### Data flow — creating a receipt

```
User submits CreateIncomingReceipt form
  → validate header + line items
  → DB::transaction
      → DocumentNumberService::issue('incoming_receipt', location, period)
      → for each line:
          → BatchResolver::resolve(product, lot_no, expiry_date, qty, uom, storage_area)
              → existing batch with same (product_id, lot_no, expiry_date)
                  → append quantity, update storage area if provided, return batch
              → else create batch (status = quarantine), return batch
          → create IncomingReceiptLine(receipt_id, batch_id, ...line fields)
      → SignOffService::record(receipt, 'received_and_processed')
  → redirect to view page
```

`document_signoffs` rows are created lazily — a row appears only when someone signs.
Un-signed steps simply have no row. This keeps the table honest about what actually
happened rather than pre-seeding aspirational rows.

---

## 5. Data model

Conventions follow the existing schema: `id` auto-increment PK, a UUID business column
where the existing tables have one, `timestamps()`, and `cascadeOnDelete`/`cascadeOnUpdate`
on foreign keys. Existing tables use camelCase UUID columns (`temperatureId`,
`serialNumberId`); new tables use a consistent `<model>Id` form.

### `principals`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| principalId | uuid unique | |
| code | string unique | e.g. `DIASPECT`, `303330/1GI` |
| name | string | |
| is_active | boolean default true | |
| timestamps | | |

### `products`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| productId | uuid unique | |
| internal_code | string unique | e.g. `DIS-T-00003`, `PAC-N-00008`, `GHL-T-00021` |
| principal_id | FK → principals, nullable | principal may be unknown at import |
| principal_code | string nullable | part/principal code from SAP, e.g. `303330/1GI` |
| description | text | |
| part_no | string nullable | |
| uom | string | `PCS`, `KIT`, `EA`, `BOX` … |
| uom_source | string | `sap` \| `manual`, default `sap`; scoped to the UoM column so import can refresh it while respecting a local override |
| storage_requirement | string nullable | enum-backed, see §5.1 |
| source | string | `sap` \| `manual`, default `manual` |
| sap_code | string nullable | original SAP key, for re-import matching |
| is_active | boolean default true | |
| timestamps | | |

Indexes: unique on `internal_code`, unique on `sap_code` (nullable), index on `principal_id`.

### `suppliers`

Shipper and manufacturer parties. The form has "Shipper Name" + "Shipper Address", and the
Grifols variant shows a shipper distinct from the carrier.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| supplierId | uuid unique | |
| name | string | |
| address | text nullable | |
| is_active | boolean default true | |
| timestamps | | |

### `carriers`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| carrierId | uuid unique | |
| name | string | |
| is_active | boolean default true | |
| timestamps | | |

### `storage_areas`

The form's "Storage Location Plan" (`AREA PACKAGING`), and the Relocation form's
Warehouse / Area / Rack triple. Modelled as a flat named area with optional components now
so the Relocation form needs no migration later.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| storageAreaId | uuid unique | |
| location_id | FK → locations | |
| warehouse | string nullable | for the Relocation form |
| area | string | e.g. `AREA PACKAGING` |
| rack | string nullable | for the Relocation form |
| is_active | boolean default true | |
| timestamps | | |

Display label = the non-null parts of `warehouse / area / rack` joined with `/`.

### `batches`

The spine of the whole feature. One row per physical batch.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| batchId | uuid unique | |
| product_id | FK → products | |
| lot_no | string nullable | `N/A` and blank both normalise to null |
| expiry_date | date nullable | |
| quantity | decimal(12,2) default 0 | running total across receipts |
| uom | string | copied from product, overridable per receipt |
| status | string | enum-backed, see §5.2 |
| storage_area_id | FK → storage_areas, nullable | current location |
| location_id | FK → locations | denormalised for scoping; derived from storage area / receipt |
| timestamps | | |

Unique index on `(product_id, lot_no, expiry_date)` — **but see the null caveat below.**
Index on `status`, index on `location_id`, index on `expiry_date`.

> **MySQL/MariaDB unique-index null caveat.** MySQL treats `NULL` values as distinct in a
> unique index, so `(1, NULL, NULL)` can be inserted repeatedly. A large share of lines on
> the sample form have `Lot/Batch/Serial No. = N/A` and `Expiry Date = N/A`. To make the
> merge rule actually work, both columns are stored as **non-null with sentinel values**:
> `lot_no` defaults to `''` (empty string) and `expiry_date` defaults to `'9999-12-31'`.
> A model accessor/mutator presents `''` and the sentinel date as `N/A` in the UI and in
> the printed form. This is a deliberate trade: correctness of merging over storage
> purity. Documented here because it is non-obvious.
>
> **Why a sentinel and not a generated `batch_key` column.** A generated key is the
> tidier fix, but MySQL/MariaDB do not support generated columns in a unique index for
> this shape without a stored expression, and a trigger-based key would fight Eloquent.
> The sentinel keeps the index a plain unique index that behaves identically on MySQL and
> SQLite (the test database), so the merge rule is unit-testable. See §10 item 4 for the
> one case where it is unsafe.

### `incoming_receipts`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| receiptId | uuid unique | |
| receipt_no | string unique | issued by `DocumentNumberService` |
| purchase_order_no | string nullable | |
| receipt_date | date | |
| receipt_time | time nullable | form shows `1050` |
| carrier_id | FK → carriers, nullable | |
| supplier_id | FK → suppliers, nullable | shipper |
| shipper_address | text nullable | free text fallback when supplier has none |
| incoming_type | string | enum-backed, see §5.3 |
| measured_by_device | boolean nullable | |
| product_temp_result | decimal(5,2) nullable | |
| product_temp_record_ref | string nullable | "Temperature records see" |
| storage_requirement | string nullable | enum-backed, see §5.1 |
| unpacking_start_at | datetime nullable | |
| unpacking_finish_at | datetime nullable | |
| product_status | string | `quarantine` \| `released`, see §5.4 |
| remark | text nullable | |
| location_id | FK → locations | |
| created_by | FK → users, nullable | |
| timestamps | | |

Indexes: unique `receipt_no`, index `location_id`, index `receipt_date`.

### `incoming_receipt_lines`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| incoming_receipt_id | FK → incoming_receipts, cascade | |
| batch_id | FK → batches | the batch this line received |
| line_no | unsigned int | preserves form row order |
| internal_code | string | snapshot at receipt time |
| product_description | text | snapshot |
| lot_no | string | snapshot |
| expiry_date | date | snapshot |
| quantity | decimal(12,2) | |
| uom | string | |
| physical_packing_condition | string nullable | `BAIK` etc. |
| registration_label_no | string nullable | |
| registration_label_qa_by | FK → users, nullable | |
| function_test_result | string nullable | |
| function_test_by | string nullable | free text on the form |
| storage_area_id | FK → storage_areas, nullable | |
| timestamps | | |

Line fields are **snapshots**, not joins. A product description edited next year must not
retroactively change a signed 2026 receipt. The `batch_id` link provides traceability; the
copied text provides fidelity.

### `document_sequences`

Backs `DocumentNumberService`.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| document_type | string | e.g. `incoming_receipt` |
| location_id | FK → locations, nullable | |
| period | string | e.g. `2026-08` |
| last_number | unsigned int default 0 | |
| timestamps | | |

Unique index on `(document_type, location_id, period)`.

### `document_signoffs`

Generic across all 8 forms.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| document_type | string | e.g. `incoming_receipt` |
| document_id | bigint | |
| step | string | e.g. `verified_by` |
| role_required | string | role name from config |
| user_id | FK → users, nullable | null if a Super Admin override |
| signer_name | string | denormalised display name at signing time |
| signed_at | datetime | |
| timestamps | | |

Index on `(document_type, document_id)`, index on `(document_type, document_id, step)`.

Polymorphic via `document_type` + `document_id` rather than a `morphTo`, because the
document types are a closed, known set and string keys are far easier to query and report
on than morph maps. A `documentSignoffs()` accessor on each document model provides the
same ergonomics.

### 5.1 `storage_requirement` values

`15_30` (15°C to 30°C), `15_25`, `2_8`, `minus25_minus10`, `minus35_minus15`.
Backed by a PHP enum `App\Enums\StorageRequirement` with a `label()` method.

### 5.2 `BatchStatus` values

`quarantine` → `released`, and the branches that forms 2–8 will drive:
`expired`, `rejected`, `destruction_requested`, `destroyed`.

Backed by `App\Enums\BatchStatus`. Only `quarantine` and `released` are reachable in this
phase; the rest exist so the enum does not need changing when the other forms land.

### 5.3 `IncomingType` values

`new_inventory_po`, `new_inventory_non_po`, `return`, `other`.

### 5.4 `ProductStatus` values

`quarantine`, `released`. Mirrors the batch status for the receipt's overall disposition,
but is stored on the receipt because the form captures it as a header-level decision
signed by the QA Manager.

### 5.5 Sign-off steps for Incoming Receipt

Ordered. `role_required` is the primary role; `Super Admin` may always sign.

| Order | Step key | Form label | Role required |
|---|---|---|---|
| 1 | `received_and_processed` | Received and Processed by | Supply Chain Officer |
| 2 | `checked_by` | Checked by | Supply Chain Manager |
| 3 | `inspected_by` | Inspected by | QA Staff |
| 4 | `verified_by` | Verified by | QA Manager |
| 5 | `status_defined_by` | Defined by (product status) | QA Manager |
| 6 | `acknowledged_by` | Acknowledged by | Supply Chain Manager |
| 7 | `sap_entry_by` | Entry data to the SAP System | Supply Chain Officer |

The form's printed job titles ("Supply Chain Coordinator", "Quality Assurance Personnel",
"Quality Assurance Supervisor", "Supply Chain Administration System Staff") do **not**
exist as seeded roles. They are mapped onto the closest seeded roles above. **This mapping
needs business confirmation** — it is the single most likely thing to be wrong in this
design.

---

## 6. Domain services

### `DocumentNumberService`

```php
public function issue(string $documentType, ?int $locationId, CarbonInterface $date): string
```

- Derives `period` from `$date` as `Y-m`.
- Inside a transaction: `SELECT ... FOR UPDATE` on the `document_sequences` row for
  `(document_type, location_id, period)`, creating it with `last_number = 0` if absent.
- Increments `last_number`, saves, formats, returns.
- Format templates live in `config/documents.php` keyed by `document_type`, e.g.
  `incoming_receipt` → `{seq:4}/{location_code}/PB/MJG/{roman_month}/{yy}`.

The row lock is the whole point. Without it two concurrent submissions produce the same
receipt number, which on a controlled document is a compliance defect. This is why the
service takes a transaction and is not merely a counter helper.

**Open item:** the exact format. The sample is `0835/WH/2/PB/MJG/VIII/26`. The `WH` and `2`
segments are unexplained (warehouse? location? division?) and `0835` is a 4-digit sequence
that looks far too large to be a monthly counter. Needs the client to confirm before
implementation — see §10.

### `BatchResolver`

```php
public function resolve(
    Product $product,
    ?string $lotNo,
    ?CarbonInterface $expiryDate,
    float $quantity,
    string $uom,
    ?StorageArea $storageArea,
    int $locationId,
): Batch
```

- Normalises `$lotNo` (`null`, `''`, `'N/A'`, `'-'` → `''`) and `$expiryDate`
  (`null` → sentinel `9999-12-31`) so the unique index behaves.
- Finds `Batch` by `(product_id, lot_no, expiry_date)`.
- **Found:** adds `$quantity` to the existing batch, sets `storage_area_id` only if
  currently null and a new one is provided, returns it. Quantity is appended because the
  Expired and Relocation forms assume one row per physical batch.
- **Not found:** creates the batch with `status = quarantine`.
- Always runs inside the caller's transaction.

### `SignOffService`

```php
public function record(Model $document, string $step): DocumentSignoff
public function canSign(Model $document, string $step, User $user): bool
public function nextPendingStep(Model $document): ?string
public function isComplete(Model $document): bool
```

- `record()` refuses if the step is already signed, if the step's prerequisites
  (earlier steps) are not signed, or if `canSign()` is false. Throws a domain exception
  the Filament action converts to a validation error.
- `canSign()` passes when the user has the step's `role_required`, or has `Super Admin`,
  and the document is in a state where that step is reachable.
- `signer_name` is built the same way the temperature module builds it:
  `initial + strtoupper(now('Asia/Jakarta')->format('d M Y'))`, except `Security` which
  shows the full name. Note this deliberately differs from the temperature module's
  `formatPicSignature()`, which stamps `$this->created_at` (the record's date) rather than
  the signing date — on a receipt signed over several days, that would print the wrong
  date. This module uses the actual signing time. Extracted into a small
  `SignatureFormatter` helper so both the new module and any future adoption by the
  temperature module share one implementation.
- Steps and their roles come from `config/documents.php`, not from hardcoded `hasRole()`
  strings in resource files.

### `Sap\ProductImportService`

```php
public function import(): SapImportResult
```

- Adapter over a configured SAP connection; the table/view mapping lives in
  `config/sap.php` and is **empty until the client supplies the table list**.
- Upserts `products` and `principals` matched on `sap_code`.
- **Never overwrites** a row where `source = 'manual'`. On a row where `source = 'sap'`,
  `description` is refreshed from SAP, but `uom` is refreshed only when
  `uom_source = 'sap'` — so a user who corrected a UoM keeps their correction on
  re-import. `--force` overrides both.
- Invoked by an artisan command `sap:import-products` (manual run), not a schedule.
- Returns counts created/updated/skipped so the command can report meaningfully.

Because the mapping is unset, this service is written against an interface and its
concrete query is the last thing implemented. It does not block anything else.

---

## 7. Presentation layer

### Filament resources

New resources under `app/Filament/Resources/`, each following the existing
`Resource` + `Pages/` + `Schemas/` + `Tables/` split seen in `NotificationSettings`:

| Resource | Group | Notes |
|---|---|---|
| `Products` | Master Data | CRUD, `source` badge, SAP import action |
| `Principals` | Master Data | CRUD |
| `Suppliers` | Master Data | CRUD |
| `Carriers` | Master Data | CRUD |
| `StorageAreas` | Master Data | CRUD, nested under location |
| `Batches` | Inventory | read-mostly, status filter, expiry filter |
| `IncomingReceipts` | Incoming | the main form |

`IncomingReceiptResource` uses the full page set, mirroring
`TemperatureHumidities`: `List`, `Create`, `View`, `Edit`.

### Form construction

- Header `Section`s: receipt identity, parties, inspection, unpacking.
- Line items: a Filament `Repeater` with `->reorderable()` to preserve form row order and
  `->addActionLabel('Add line')`. The repeater rows carry `internal_code`,
  `product_description`, `lot_no`, `expiry_date`, `quantity`, `uom`,
  `physical_packing_condition`, `registration_label_no`, `registration_label_qa_by`,
  `function_test_result`, `function_test_by`, `storage_area_id`.
- Selecting a `Product` autofills `internal_code`, `product_description`, `uom` — but the
  fields stay editable, because the form is a record of what was physically observed, not
  a projection of the catalogue.
- `receipt_no` is read-only and issued on create; never typed.
- Signature block: a `Section` of read-only text entries showing each step's
  `signer_name` + `signed_at`, plus a `Sign` action visible only when
  `SignOffService::canSign()` is true for the current user.

### Access control

- Every new resource's `getEloquentQuery()` applies
  `HasLocationBasedAccess::applyLocationFilter()`.
- Creating/editing master data is limited to `Super Admin`, `Admin`.
- Creating a receipt requires `Supply Chain Officer` or above, consistent with the
  existing temperature module's create permission.
- Signing is governed entirely by `SignOffService`, not by resource-level
  `canViewAny`/`canEdit`.

### PDF

`GET /incoming-receipts/{record}/print` → `resources/views/print/incoming-receipt.blade.php`
rendered through `Browsershot::html()`, matching the existing routes in `routes/web.php`:
A4, landscape for the multi-column layout, margins 3mm, `showBackground()`.

The blade replicates the paper form: blue logo cell, `FORM` / `INCOMING RECEIPT
INSPECTION` titles, the doc-no block (`MJG-FOR-SCM.02-002-02`, effective `16 OCT 2025`,
rev `12`, page `01 of 01`), the header field grid, the line-item table, and the footer
signature grid with `signer_name` values rendered into the signature cells.

Checkbox glyphs are rendered as `☑` / `☐` based on the stored enum/boolean values.

A `PrintTest` renders the view against a factory-made receipt and asserts the presence of
the doc number, revision, a line-item description, and each signer name — mirroring
`tests/Feature/TemperatureDeviationPrintTest.php`.

---

## 8. Error handling

- **Number issuance failure** — the whole receipt transaction rolls back; the user sees a
  validation error. No receipt row, no sequence increment.
- **Batch merge conflict** — unique index violation on `(product_id, lot_no, expiry_date)`
  under concurrency is caught and retried once inside the transaction.
- **Sign-off out of order / wrong role** — domain exception converted to a Filament
  validation notification naming the missing prerequisite step.
- **Edit after signing** — once any sign-off exists, editing the receipt is blocked for
  everyone except `Super Admin`. `Super Admin` may edit, and the edit is recorded in
  `document_signoffs` as an override entry naming the edited step. Without the activity-log
  dependency (see §10 item 7) there is no field-level before/after record — only the fact
  that an override happened.
- **SAP import failure** — the command exits non-zero with the driver error; nothing
  partial is committed (single transaction, or chunked with per-chunk transactions and a
  reported failure count).
- **Storage area / product deleted while referenced** — foreign keys use
  `cascadeOnDelete` for lines but `restrict` semantics are preferred for
  `batches.product_id` so a product with history cannot be deleted; the resource offers
  deactivation (`is_active = false`) instead.

---

## 9. Testing strategy

Following the existing Pest layout (`tests/Unit/Services/`, `tests/Feature/`).

**Unit**
- `DocumentNumberServiceTest` — sequential issuance, per-period reset, per-location
  isolation, concurrent issuance via two connections yields distinct numbers.
- `BatchResolverTest` — creates when absent; merges quantity when present; normalises
  `N/A`/null/`-` into the sentinel; does not overwrite a non-null storage area.
- `SignOffServiceTest` — rejects wrong role; rejects out-of-order; rejects double-sign;
  `Super Admin` bypasses role but not order; `isComplete()` only when all steps signed.
- `SignatureFormatterTest` — `initial + date` format; `Security` shows full name.

**Feature**
- `IncomingReceiptCrudTest` — create via Livewire with two line items produces one receipt,
  two lines, and the expected batches; editing snapshots does not mutate the batch.
- `IncomingReceiptNumberTest` — two receipts in the same month get consecutive numbers.
- `IncomingReceiptAccessTest` — a location-scoped user sees only their location's receipts;
  a user without a location sees none.
- `IncomingReceiptPrintTest` — the rendered blade contains the doc number, revision, line
  descriptions and signer names.
- `ProductImportTest` — imports from a fake source; does not overwrite `source = 'manual'`
  rows.

Factories needed: `ProductFactory`, `PrincipalFactory`, `SupplierFactory`,
`CarrierFactory`, `StorageAreaFactory`, `BatchFactory`, `IncomingReceiptFactory`.

---

## 10. Open items requiring client confirmation

These are the places the design is most likely to be wrong. They are listed so they can be
answered before or during implementation rather than discovered late.

1. **Sign-off role mapping (§5.5).** The printed job titles are not seeded roles. My
   mapping to `Supply Chain Officer` / `Supply Chain Manager` / `QA Staff` / `QA Manager`
   is a guess. Needs confirmation, and `RoleSeeder` likely needs new roles.
2. **Receipt number format (§6).** `0835/WH/2/PB/MJG/VIII/26` — the `0835` sequence width
   and the `WH` / `2` segments are unexplained. The service's format template cannot be
   finalised without this.
3. **Batch merge semantics.** Appending quantity assumes one physical batch per
   `(product, lot, expiry)`. If the same lot can be received as genuinely separate batches
   that must stay separate, the fix is to add a nullable `batch_discriminator` column
   (defaulted to `''`, set only when a user explicitly chooses "keep separate") and widen
   the unique index to `(product_id, lot_no, expiry_date, batch_discriminator)`. That is a
   migration plus a `BatchResolver` lookup change, not a redesign — which is part of why
   the core is worth building first.
4. **The `N/A` sentinel (§5).** Storing `''` and `9999-12-31` instead of `NULL` is a
   correctness trade. If a real batch can legitimately expire on 9999-12-31, this is
   unsafe. Flagged for confirmation.
5. **SAP table/view list.** Promised, not yet supplied. Blocks only
   `ProductImportService`'s concrete query.
6. **SAP HANA ODBC client install.** System-level prerequisite needing approval.
7. **Audit trail.** `openspec/project.md` lists "Audit Trails" as a constraint, and these
   are signed controlled documents. This design records sign-offs but does **not** log
   every field edit. The obvious fit is `spatie/laravel-activitylog`, which is a new
   dependency and therefore needs explicit approval under GEMINI.md. Recommend adding it;
   without it, an edit after signing leaves no trail.
8. **`HasLocationBasedAccess` bug.** The trait references a `'QA Supervisor'` role that is
   not seeded, so the `hasRole([...])` call silently excludes it. `'Staff'` is likewise
   referenced and not seeded. Not introduced by this change, but this change multiplies
   the number of resources relying on that trait, so it should be fixed here or explicitly
   deferred.
9. **Print page count.** The form is `01 of 01`. A receipt with many line items will
   overflow a single page. Decide whether the print view paginates (`01 of 02`) or the
   form is restricted to what fits.

---

## 11. Delivery shape

Implementation plan will follow via the writing-plans skill. Expected order:

1. Enums + config (`documents.php`, `sap.php`), migrations, models, factories.
2. `SignatureFormatter`, `DocumentNumberService`, `BatchResolver`, `SignOffService` + unit
   tests.
3. Master-data Filament resources (Products, Principals, Suppliers, Carriers, StorageAreas).
4. `IncomingReceipt` model + `IncomingReceiptResource` + create/view/edit pages.
5. Print blade + route + print test.
6. `ProductImportService` + `sap:import-products` command (pending the table list).
7. Seeders for master data derived from the sample forms, so the module is demonstrable.
