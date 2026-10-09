# Incoming Receipt & Batch Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a batch-centric inventory core and the Incoming Receipt Inspection form (MJG-FOR-SCM.02-002-02) to the Medquest SCM dashboard, with role-gated sign-offs and an exact-layout printable PDF.

**Architecture:** Three layers. Eloquent models + migrations for persistence; four Filament-free domain services (`DocumentNumberService`, `BatchResolver`, `SignOffService`, `Sap\ProductImportService`) holding the rules that are expensive to get wrong; Filament 5 resources for presentation. Forms 2–8 attach to the same `batches` spine later.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 5, MariaDB, Spatie Permission, Browsershot, Pest 4.

**Spec:** `docs/superpowers/specs/2026-10-09-incoming-receipt-batch-core-design.md`

## Global Constraints

- Laravel `^13.0`, Filament `^5.0`. `GEMINI.md` claims v12/v4 — it is stale, `composer.json` wins.
- **No new composer dependencies without explicit approval.** In particular `spatie/laravel-activitylog` is *not* approved; do not add it.
- **New `app/` subfolders require approval.** `GEMINI.md` forbids creating base folders without it. This plan creates four: `app/Enums`, `app/Exceptions`, `app/Contracts`, `app/Support`. They are conventional Laravel locations and each holds at least two files, but **confirm with the human partner before Task 1**. If declined, the fallback is to place the enums in `app/Models/Enums` and the exception/contract in `app/Services` — say so rather than inventing a third option. Existing `app/Services` and `app/Filament/Resources` are used as-is.
- All new Filament resources apply `App\Traits\HasLocationBasedAccess::applyLocationFilter()` in `getEloquentQuery()`.
- Timestamps and date formatting use `Asia/Jakarta`.
- Follow existing conventions: `$guarded = ['id']`, UUID business column named `<model>Id`, `Str::orderedUuid()`, `timestamps()`.
- Sign-off role names must be read from `config/documents.php`, never hardcoded `hasRole('...')` literals in resource files.
- **⚠ Test suite hazard:** `phpunit.xml` has `DB_CONNECTION=sqlite` **commented out**, so `RefreshDatabase` executes `migrate:fresh` against the real `medquest_scm` MariaDB database. Running `./vendor/bin/pest` **destroys local development data**. Confirm with the human partner before the first full-suite run. Run new tests with `--filter` where possible.
- **⚠ Pre-existing red baseline:** `./vendor/bin/pest` is 16 failed / 4 passed before any work here. Two causes: `App\Models\TemperatureHumidity::factory()` does not exist, and several tests insert non-UUID strings (e.g. `'JKT001'`) into `uuid` columns. Do **not** fix these in this plan — they are out of scope. Any new failure is attributable to this plan's tasks; existing failures are not.
- Run `./vendor/bin/pint --dirty` before each commit.

## Review Focus

Inputs and conditions the spec implies but whose tests live in a specific task. Each line names the condition and the behaviour a reasonable person expects.

1. **Lot/expiry values that mean "none"** (`N/A`, `-`, empty, null) must collapse to the same batch key, or every receipt line creates a duplicate batch row. Owned by Task 5 (`BatchResolver`).
2. **Two receipts submitted concurrently in the same month** must not receive the same number. A duplicate on a controlled document is a compliance defect. Owned by Task 4 (`DocumentNumberService`).
3. **A receipt submitted with zero line items** must be rejected — an empty receipt is not a valid record. Owned by Task 9 (create page).
4. **Signing out of order or with the wrong role** must be refused, and a `Super Admin` bypassing the role must still not bypass the order. Owned by Task 6 (`SignOffService`).
5. **A product whose `source = 'manual'`** must survive a SAP re-import unchanged, including its user-corrected `uom`. Owned by Task 12 (`ProductImportService`).

---

### Task 1: Enums and document configuration

**Files:**
- Create: `app/Enums/StorageRequirement.php`
- Create: `app/Enums/BatchStatus.php`
- Create: `app/Enums/IncomingType.php`
- Create: `app/Enums/ProductStatus.php`
- Create: `app/Enums/ProductSource.php`
- Create: `config/documents.php`
- Test: `tests/Unit/Enums/DocumentConfigTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `StorageRequirement`, `BatchStatus`, `IncomingType`, `ProductStatus`, `ProductSource` (all `string`-backed, each with `label(): string`). `config('documents.signoff_steps.incoming_receipt')` → `array<string, array{label: string, role: string}>` in signing order. `config('documents.formats.incoming_receipt')` → format string. `config('documents.sequence_padding')` → `4`. `config('documents.form_metadata.incoming_receipt')` → `array{doc_no: string, effective_date: string, revision: string}`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Enums/DocumentConfigTest.php
use App\Enums\BatchStatus;
use App\Enums\IncomingType;
use App\Enums\ProductSource;
use App\Enums\StorageRequirement;

it('backs every storage requirement case with a spec value', function () {
    expect(array_column(StorageRequirement::cases(), 'value'))->toBe([
        'minus35_minus15', 'minus25_minus10', '2_8', '15_25', '15_30',
    ]);
});

it('reaches only quarantine and released in this phase', function () {
    expect(BatchStatus::QUARANTINE->value)->toBe('quarantine')
        ->and(BatchStatus::RELEASED->value)->toBe('released')
        ->and(BatchStatus::DESTROYED->value)->toBe('destroyed');
});

it('exposes the four incoming types', function () {
    expect(array_column(IncomingType::cases(), 'value'))->toBe([
        'new_inventory_po', 'new_inventory_non_po', 'return', 'other',
    ]);
});

it('exposes the two product sources', function () {
    expect(array_column(ProductSource::cases(), 'value'))->toBe(['sap', 'manual']);
});

it('defines the seven incoming receipt sign-off steps in order', function () {
    $steps = config('documents.signoff_steps.incoming_receipt');

    expect(array_keys($steps))->toBe([
        'received_and_processed', 'checked_by', 'inspected_by',
        'verified_by', 'status_defined_by', 'acknowledged_by', 'sap_entry_by',
    ])->and($steps['received_and_processed']['role'])->toBe('Supply Chain Officer');
});

it('carries the incoming receipt form metadata', function () {
    expect(config('documents.form_metadata.incoming_receipt'))->toBe([
        'doc_no' => 'MJG-FOR-SCM.02-002-02',
        'effective_date' => '16 OCT 2025',
        'revision' => '12',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Enums/DocumentConfigTest.php`
Expected: FAIL — `Class "App\Enums\StorageRequirement" not found`.

- [ ] **Step 3: Implement the enums and config**

Each enum is a `string`-backed enum in `App\Enums` with a `label(): string` method. `BatchStatus` additionally gets `isTerminal(): bool` returning true for `EXPIRED`, `REJECTED`, `DESTROYED`.

`config/documents.php` returns the three keys described in Interfaces. The sign-off role mapping is the spec's §5.5 table verbatim.

> The role values are the spec's *guess* at the client's job titles (spec §10 item 1). Implement them as written; they are one-line edits when the client confirms.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Enums/DocumentConfigTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Enums config/documents.php tests/Unit/Enums/DocumentConfigTest.php
git commit -m "feat: add inventory enums and document config"
```

---

### Task 2: Master data schema, models and factories

**Files:**
- Create: `database/migrations/2026_10_09_100001_create_principals_table.php`
- Create: `database/migrations/2026_10_09_100002_create_products_table.php`
- Create: `database/migrations/2026_10_09_100003_create_suppliers_table.php`
- Create: `database/migrations/2026_10_09_100004_create_carriers_table.php`
- Create: `app/Models/Principal.php`, `Product.php`, `Supplier.php`, `Carrier.php`
- Create: `database/factories/PrincipalFactory.php`, `ProductFactory.php`, `SupplierFactory.php`, `CarrierFactory.php`
- Test: `tests/Unit/Models/MasterDataTest.php`

**Interfaces:**
- Consumes: `App\Enums\StorageRequirement`, `ProductSource` (Task 1).
- Produces: models `Principal`, `Product`, `Supplier`, `Carrier` with `$guarded = ['id']` and `HasFactory`. `Product` casts `storage_requirement` → `StorageRequirement`, `source` → `ProductSource`; exposes `principal(): BelongsTo` and `batches(): HasMany`. Factories take no required arguments and use `Str::orderedUuid()` for the UUID column.
- Reuses: the existing `Location` and `Room` models unchanged for physical storage location (see the spec's 'Physical location' section). `Room` already exposes `location(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Models/MasterDataTest.php
use App\Enums\ProductSource;
use App\Enums\StorageRequirement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Room;

it('casts a product storage requirement to the enum', function () {
    $product = Product::factory()->create(['storage_requirement' => '2_8']);

    expect($product->fresh()->storage_requirement)->toBe(StorageRequirement::TWO_TO_EIGHT);
});

it('defaults a product source to manual', function () {
    expect(Product::factory()->create()->source)->toBe(ProductSource::MANUAL);
});

it('reuses a room as the storage area of a location', function () {
    $location = Location::factory()->create(['location_name' => 'Bizpark 1']);
    $room = Room::factory()->for($location)->create(['room_name' => 'Cold Storage 4']);

    expect($room->location->location_name)->toBe('Bizpark 1')
        ->and($room->room_name)->toBe('Cold Storage 4');
});

it('rejects a duplicate product internal code', function () {
    Product::factory()->create(['internal_code' => 'DIS-T-00003']);

    expect(fn () => Product::factory()->create(['internal_code' => 'DIS-T-00003']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/MasterDataTest.php`
Expected: FAIL — `Class "App\Models\Product" not found`.

- [ ] **Step 3: Implement migrations, models and factories**

Columns are the spec's §5 tables. Points that are easy to get wrong:

- `products.source` defaults to `'manual'`; `products.uom_source` defaults to `'sap'`. Both are `string(16)`, not enum columns — the enum cast lives on the model so values stay readable in SQL.
- `products.sap_code` is `string` nullable and **unique**. The spec (§5) mandates unique; an earlier draft of this plan claimed a plain index was needed because of repeated NULLs, but that reasoning was wrong — verified on this MariaDB that multiple NULLs are accepted under a UNIQUE index. Unique is both correct and what the spec requires.
- **No `storage_areas` table.** Physical location reuses `locations` and `rooms`, which already exist and are unchanged. Do not add columns to either.
- `products.principal_id` is nullable with `nullOnDelete()` — a product may be imported before its principal is known.
- `is_active` boolean defaults true on every master table.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/MasterDataTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations database/factories app/Models tests/Unit/Models/MasterDataTest.php
git commit -m "feat: add inventory master data schema and models"
```

---

### Task 3: Batch schema, model and factory

**Files:**
- Create: `database/migrations/2026_10_09_100006_create_batches_table.php`
- Create: `app/Models/Batch.php`
- Create: `database/factories/BatchFactory.php`
- Test: `tests/Unit/Models/BatchTest.php`

**Interfaces:**
- Consumes: `Product` (Task 2), `Location`/`Room` (existing), `BatchStatus` (Task 1).
- Produces: `Batch` model with public const `EXPIRY_SENTINEL = '9999-12-31'`; casts `status` → `BatchStatus`, `expiry_date` → `date`; relations `product(): BelongsTo`, `room(): BelongsTo`, `location(): BelongsTo`, `receiptLines(): HasMany`. Accessors: `expiryDateLabel(): string` returning `'N/A'` for the sentinel and `d M Y` otherwise; `lotNoLabel(): string` returning `'N/A'` when `lot_no === ''`. `BatchFactory` requires nothing.

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Models/BatchTest.php
use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Product;

it('defaults a new batch to quarantine', function () {
    expect(Batch::factory()->create()->status)->toBe(BatchStatus::QUARANTINE);
});

it('rejects two batches with the same product, lot and expiry', function () {
    $product = Product::factory()->create();

    Batch::factory()->for($product)->create(['lot_no' => '471000', 'expiry_date' => '2025-12-31']);

    expect(fn () => Batch::factory()->for($product)->create(['lot_no' => '471000', 'expiry_date' => '2025-12-31']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('rejects a second batch with an empty lot and sentinel expiry', function () {
    $product = Product::factory()->create();

    Batch::factory()->for($product)->create(['lot_no' => '', 'expiry_date' => Batch::EXPIRY_SENTINEL]);

    expect(fn () => Batch::factory()->for($product)->create(['lot_no' => '', 'expiry_date' => Batch::EXPIRY_SENTINEL]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('prints the sentinel expiry as N/A', function () {
    $batch = Batch::factory()->create(['expiry_date' => Batch::EXPIRY_SENTINEL]);

    expect($batch->expiryDateLabel())->toBe('N/A');
});

it('prints an empty lot as N/A', function () {
    $batch = Batch::factory()->create(['lot_no' => '']);

    expect($batch->lotNoLabel())->toBe('N/A');
});

it('prints a real expiry in d M Y', function () {
    $batch = Batch::factory()->create(['expiry_date' => '2027-08-15']);

    expect($batch->expiryDateLabel())->toBe('15 Aug 2027');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/BatchTest.php`
Expected: FAIL — `Class "App\Models\Batch" not found`.

- [ ] **Step 3: Implement the migration, model and factory**

- `lot_no` is `string` **NOT NULL DEFAULT ''**; `expiry_date` is `date` **NOT NULL DEFAULT '9999-12-31'`. This is the spec's §5 sentinel trade — read the spec's callout box before changing it. `NULL` would break the unique index on MySQL.
- Unique index `batches_product_lot_expiry_unique` on `(product_id, lot_no, expiry_date)`.
- Plain indexes on `status`, `location_id`, `room_id`, `expiry_date`.
- `quantity` is `decimal(12,2)` default 0. `uom` is `string(16)`.
- `product_id` FK uses `restrictOnDelete()` — a product with batch history must not be deletable (spec §8). `location_id` FK uses `cascadeOnDelete()->cascadeOnUpdate()`. `room_id` FK is nullable with `nullOnDelete()`.
- The model declares `protected $casts = ['expiry_date' => 'date', 'status' => BatchStatus::class, 'quantity' => 'decimal:2']`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/BatchTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_10_09_100006_create_batches_table.php app/Models/Batch.php database/factories/BatchFactory.php tests/Unit/Models/BatchTest.php
git commit -m "feat: add batch spine table and model"
```

---

### Task 4: `SignatureFormatter` and `DocumentNumberService`

**Files:**
- Create: `database/migrations/2026_10_09_100000_create_document_sequences_table.php`
- Create: `database/migrations/2026_10_09_100010_add_location_code_to_locations_table.php`
- Create: `app/Models/DocumentSequence.php`
- Create: `app/Support/SignatureFormatter.php`
- Create: `app/Services/DocumentNumberService.php`
- Create: `tests/Unit/Services/SignatureFormatterTest.php`
- Create: `tests/Unit/Services/DocumentNumberServiceTest.php`

**Interfaces:**
- Consumes: `config('documents.formats')`, `config('documents.sequence_padding')` (Task 1); `Location` model.
- Consumes: `config('documents.formats')`, `config('documents.sequence_padding')` (Task 1); `Location` model.
- Produces:
  - `SignatureFormatter::format(App\Models\User $user, Carbon\CarbonInterface $signedAt): string`
  - `DocumentSequence` model on the `document_sequences` table (`document_type`, `location_id`, `period`, `last_number`), with a unique index on the first three.
  - `DocumentNumberService::issue(string $documentType, ?int $locationId, Carbon\CarbonInterface $date): string`
  - `Location` gains a nullable `location_code` column.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Unit/Services/SignatureFormatterTest.php
use App\Models\User;
use App\Support\SignatureFormatter;
use Carbon\Carbon;

it('formats a signature as initial plus signing date', function () {
    $user = User::factory()->create(['initial' => 'IAN']);
    $user->assignRole('Supply Chain Officer');

    expect(SignatureFormatter::format($user, Carbon::parse('2026-08-05 10:50:00')))
        ->toBe('IAN 05 AUG 2026');
});

it('shows the full name for a Security user', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso', 'initial' => 'BS']);
    $user->assignRole('Security');

    expect(SignatureFormatter::format($user, Carbon::parse('2026-08-05 10:50:00')))
        ->toBe('Budi Santoso');
});

it('uses the signing date and not the record date', function () {
    $user = User::factory()->create(['initial' => 'IAN']);
    $user->assignRole('QA Staff');

    // Spec §6: formatPicSignature() stamps created_at; this must not.
    expect(SignatureFormatter::format($user, Carbon::parse('2026-09-01 08:00:00')))
        ->toBe('IAN 01 SEP 2026');
});
```

```php
// tests/Unit/Services/DocumentNumberServiceTest.php
use App\Models\Location;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
// The issued number is opaque; assert on its trailing zero-padded sequence only.
function sequenceSuffix(string $number): string
{
    preg_match('/(\d{4})$/', $number, $m);

    return $m[1] ?? '';
}
it('issues sequential numbers within a period', function () {
    $location = Location::factory()->create();
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $first = $service->issue('incoming_receipt', $location->id, $date);
    $second = $service->issue('incoming_receipt', $location->id, $date);

    expect($second)->not->toBe($first)
        ->and(sequenceSuffix($first))->toBe('0001')
        ->and(sequenceSuffix($second))->toBe('0002');
});

it('resets the sequence in a new period', function () {
    $location = Location::factory()->create();
    $service = app(DocumentNumberService::class);

    $service->issue('incoming_receipt', $location->id, Carbon::parse('2026-08-31'));

    expect(sequenceSuffix($service->issue('incoming_receipt', $location->id, Carbon::parse('2026-09-01'))))
        ->toBe('0001');
});

it('isolates sequences per location', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $service->issue('incoming_receipt', $a->id, $date);

    expect(sequenceSuffix($service->issue('incoming_receipt', $b->id, $date)))->toBe('0001');
});

it('does not reuse a number when two transactions race', function () {
    $location = Location::factory()->create();
    $date = Carbon::parse('2026-08-05');

    // Two callers in separate transactions. The row lock in issue() is what keeps
    // these distinct; without lockForUpdate both would read last_number = 0.
    $first = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));
    $second = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));

    expect($second)->not->toBe($first)
        ->and(sequenceSuffix($second))->toBe('0002');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Services/SignatureFormatterTest.php tests/Unit/Services/DocumentNumberServiceTest.php`
Expected: FAIL — `Class "App\Support\SignatureFormatter" not found`.

- [ ] **Step 3: Implement `SignatureFormatter` and `DocumentNumberService`**

`document_sequences` migration: `document_type` string, `location_id` FK nullable `cascadeOnDelete()`, `period` string(7), `last_number` unsignedInteger default 0, `timestamps()`. Unique index on `(document_type, location_id, period)`. The model is `$guarded = ['id']` with no relations needed.

`add_location_code_to_locations_table` adds `location_code` string(16) nullable after `location_name`.

`SignatureFormatter::format()`: if `$user->hasRole('Security')` return `$user->name`; otherwise return `"{$user->initial} " . strtoupper($signedAt->format('d M Y'))`. Deliberately takes the signing time as a parameter rather than reading `$document->created_at` — that is the spec §6 divergence and the third test pins it.

`DocumentNumberService::issue()`:
1. Derive `period = $date->format('Y-m')`.
2. Inside `DB::transaction`: `DocumentSequence::query()->where(...)->lockForUpdate()->first()`, or `firstOrCreate` with `last_number = 0` when absent. Use `lockForUpdate()` on the read — that is the whole mechanism (spec §6).
3. Increment, save, then format via the template in `config('documents.formats')`.
4. Template tokens: `{seq:N}` zero-padded to N, `{location_code}`, `{roman_month}`, `{yy}`, `{yyyy}`, `{mm}`.

`location_code` falls back to `$location->id` when null, so numbering works before the client confirms the format. The spec notes the sample number's `WH` and `2` segments are unexplained (spec §10 item 2) — that is why `config/documents.php` ships a provisional format and the tests assert only on the trailing sequence via `sequenceSuffix()`.

The four `issue()` tests use `Location::factory()` and `Product::factory()`; the existing `LocationFactory` is unchanged apart from the new nullable column.
- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/SignatureFormatterTest.php tests/Unit/Services/DocumentNumberServiceTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support app/Services/DocumentNumberService.php app/Models/DocumentSequence.php database/migrations tests/Unit/Services
git commit -m "feat: add signature formatter and document number service"
```

---

### Task 5: `BatchResolver`

**Files:**
- Create: `app/Services/BatchResolver.php`
- Test: `tests/Unit/Services/BatchResolverTest.php`

**Interfaces:**
- Consumes: `Batch` (Task 3), `Product` (Task 2), `Room` (existing).
- Produces:
  - `BatchResolver::normalizeLotNo(?string $lotNo): string` — static; maps `null`, `''`, `'N/A'`, `'n/a'`, `'-'`, and whitespace-only to `''`.
  - `BatchResolver::normalizeExpiryDate(null|string|CarbonInterface $expiryDate): string` — static; maps `null`/`''`/`'N/A'`/`'-'` to `Batch::EXPIRY_SENTINEL`, else `Y-m-d`.
  - `BatchResolver::resolve(Product $product, ?string $lotNo, null|string|CarbonInterface $expiryDate, float $quantity, string $uom, ?Room $room, int $locationId): Batch`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Services/BatchResolverTest.php
use App\Models\Batch;
use App\Models\Product;
use App\Services\BatchResolver;

it('normalises every none-like lot value to the empty string', function () {
    foreach ([null, '', '   ', 'N/A', 'n/a', '-'] as $input) {
        expect(BatchResolver::normalizeLotNo($input))->toBe('');
    }
});

it('keeps a real lot number', function () {
    expect(BatchResolver::normalizeLotNo(' 471000 '))->toBe('471000');
});

it('normalises every none-like expiry value to the sentinel', function () {
    foreach ([null, '', 'N/A', '-'] as $input) {
        expect(BatchResolver::normalizeExpiryDate($input))->toBe(Batch::EXPIRY_SENTINEL);
    }
});

it('creates a batch in quarantine when none matches', function () {
    $product = Product::factory()->create();

    $batch = app(BatchResolver::class)->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);

    expect($batch->exists)->toBeTrue()
        ->and($batch->status->value)->toBe('quarantine')
        ->and($batch->quantity)->toBe('5.00');
});

it('merges quantity into an existing batch', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $first = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $second = $resolver->resolve($product, '471000', '2025-12-31', 10.0, 'KIT', null, 1);

    expect($second->id)->toBe($first->id)
        ->and($second->fresh()->quantity)->toBe('15.00');
});

it('merges two N/A lot lines into one batch', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $first = $resolver->resolve($product, 'N/A', 'N/A', 200.0, 'PCS', null, 1);
    $second = $resolver->resolve($product, null, null, 50.0, 'PCS', null, 1);

    expect($second->id)->toBe($first->id)
        ->and(Batch::count())->toBe(1);
});

it('does not overwrite a room that is already set', function () {
    $product = Product::factory()->create();
    $original = Room::factory()->create();
    $replacement = Room::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $original, 1);
    $batch = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $replacement, 1);

    expect($batch->fresh()->room_id)->toBe($original->id);
});

it('sets the room when the batch has none', function () {
    $product = Product::factory()->create();
    $room = Room::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $batch = $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', $room, 1);

    expect($batch->fresh()->room_id)->toBe($room->id);
});

it('keeps separate batches for different lots of one product', function () {
    $product = Product::factory()->create();
    $resolver = app(BatchResolver::class);

    $resolver->resolve($product, '471000', '2025-12-31', 5.0, 'KIT', null, 1);
    $resolver->resolve($product, '464000', '2025-12-31', 5.0, 'KIT', null, 1);

    expect(Batch::count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/BatchResolverTest.php`
Expected: FAIL — `Class "App\Services\BatchResolver" not found`.

- [ ] **Step 3: Implement `BatchResolver`**

Both normalisers are static and pure so they are cheap to test. `resolve()` looks up `Batch` by the three normalised key columns, then either appends quantity or creates. Set `room_id` on an existing batch **only when it is currently null** — the Expired/Relocation forms assume the first-recorded location wins (spec §6). `location_id` is always set from the argument on create and never changed on merge.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/BatchResolverTest.php`
Expected: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/BatchResolver.php tests/Unit/Services/BatchResolverTest.php
git commit -m "feat: add batch resolver with create-or-merge semantics"
```

---

### Task 6: `SignOffService` and the sign-off table

**Files:**
- Create: `database/migrations/2026_10_09_100007_create_document_signoffs_table.php`
- Create: `app/Models/DocumentSignoff.php`
- Create: `app/Contracts/HasSignOffs.php`
- Create: `app/Exceptions/SignOffException.php`
- Create: `app/Services/SignOffService.php`
- Create: `database/factories/DocumentSignoffFactory.php`
- Test: `tests/Unit/Services/SignOffServiceTest.php`

**Interfaces:**
- Consumes: `config('documents.signoff_steps')` (Task 1), `SignatureFormatter` (Task 4).
- Produces:
  - `interface HasSignOffs { public function documentType(): string; public function signoffs(): HasMany; }`
  - `class SignOffException extends \RuntimeException` with static constructors `alreadySigned(string $step)`, `outOfOrder(string $step, string $prerequisite)`, `notPermitted(string $step, string $role)`.
  - `SignOffService::record(HasSignOffs $document, string $step, ?User $actor = null): DocumentSignoff`
  - `SignOffService::canSign(HasSignOffs $document, string $step, User $user): bool`
  - `SignOffService::nextPendingStep(HasSignOffs $document): ?string`
  - `SignOffService::isComplete(HasSignOffs $document): bool`
  - `SignOffService::stepsFor(string $documentType): array` — the ordered config array.

- [ ] **Step 1: Write the failing test**

The test needs a document to sign. Use a small test double in `tests/Fixtures/SignableDocument.php` implementing `HasSignOffs` over a real `document_signoffs` table row set — do **not** stub the table, the ordering logic reads real rows.

```php
// tests/Unit/Services/SignOffServiceTest.php
use App\Exceptions\SignOffException;
use App\Models\User;
use App\Services\SignOffService;
use Tests\Fixtures\SignableDocument;

beforeEach(function () {
    $this->document = SignableDocument::create(['document_type' => 'incoming_receipt']);
    $this->service = app(SignOffService::class);
});

it('records the first step for the correct role', function () {
    $officer = User::factory()->create(['initial' => 'IAN'])->assignRole('Supply Chain Officer');

    $signoff = $this->service->record($this->document, 'received_and_processed', $officer);

    expect($signoff->step)->toBe('received_and_processed')
        ->and($signoff->signer_name)->toBe('IAN ' . strtoupper(now('Asia/Jakarta')->format('d M Y')))
        ->and($signoff->user_id)->toBe($officer->id);
});

it('refuses a step signed by the wrong role', function () {
    $staff = User::factory()->create()->assignRole('QA Staff');

    expect(fn () => $this->service->record($this->document, 'received_and_processed', $staff))
        ->toThrow(SignOffException::class);
});

it('refuses a step whose prerequisite is unsigned', function () {
    $manager = User::factory()->create()->assignRole('Supply Chain Manager');

    // checked_by is step 2; received_and_processed (step 1) is unsigned.
    expect(fn () => $this->service->record($this->document, 'checked_by', $manager))
        ->toThrow(SignOffException::class);
});

it('refuses a double sign of the same step', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');
    $this->service->record($this->document, 'received_and_processed', $officer);

    expect(fn () => $this->service->record($this->document, 'received_and_processed', $officer))
        ->toThrow(SignOffException::class);
});

it('lets a super admin bypass the role but not the order', function () {
    $admin = User::factory()->create()->assignRole('Super Admin');

    expect($this->service->canSign($this->document, 'received_and_processed', $admin))->toBeTrue()
        ->and(fn () => $this->service->record($this->document, 'checked_by', $admin))
        ->toThrow(SignOffException::class);
});

it('reports the next pending step', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    expect($this->service->nextPendingStep($this->document))->toBe('received_and_processed');

    $this->service->record($this->document, 'received_and_processed', $officer);

    expect($this->service->nextPendingStep($this->document))->toBe('checked_by');
});

it('is complete only when every step is signed', function () {
    expect($this->service->isComplete($this->document))->toBeFalse();

    foreach (['received_and_processed', 'checked_by', 'inspected_by', 'verified_by', 'status_defined_by', 'acknowledged_by', 'sap_entry_by'] as $step) {
        $admin = User::factory()->create()->assignRole('Super Admin');
        $this->service->record($this->document, $step, $admin);
    }

    expect($this->service->isComplete($this->document))->toBeTrue()
        ->and($this->service->nextPendingStep($this->document))->toBeNull();
});

it('stores the role required at signing time', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $signoff = $this->service->record($this->document, 'received_and_processed', $officer);

    expect($signoff->role_required)->toBe('Supply Chain Officer');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/SignOffServiceTest.php`
Expected: FAIL — `Interface "App\Contracts\HasSignOffs" not found`.

- [ ] **Step 3: Implement the contract, exception, model, migration and service**

- `document_signoffs` columns per spec §5: `document_type` string, `document_id` unsignedBigInteger, `step` string, `role_required` string, `user_id` FK nullable `nullOnDelete()`, `signer_name` string, `signed_at` datetime. Index on `(document_type, document_id)` and on `(document_type, document_id, step)`.
- The polymorphic link is `document_type` + `document_id` strings, **not** `morphTo` — spec §5 explains why (closed known set, easier reporting).
- `record()` order of checks: (1) step already signed → `alreadySigned`; (2) every earlier configured step signed → else `outOfOrder`; (3) `canSign()` → else `notPermitted`. Then create the row using `SignatureFormatter::format($actor, now('Asia/Jakarta'))` and `signed_at = now('Asia/Jakarta')`.
- `$actor` defaults to `auth()->user()`; throw if null.
- `canSign()` returns true when the user has `role_required` **or** `Super Admin`, and the step's prerequisites are met. It must not require `$actor` for the prerequisite check.
- `SignableDocument` fixture: a plain Eloquent model on the `document_signoffs` table's sibling — give it its own tiny table in the test, or implement `signoffs()` as a `hasMany(DocumentSignoff::class, 'document_id')->where('document_type', $this->document_type)`. Use the latter; no extra migration.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/SignOffServiceTest.php`
Expected: PASS, 8 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Contracts app/Exceptions app/Services/SignOffService.php app/Models/DocumentSignoff.php database/migrations database/factories/DocumentSignoffFactory.php tests/Fixtures tests/Unit/Services/SignOffServiceTest.php
git commit -m "feat: add ordered role-gated sign-off service"
```

---

### Task 7: Incoming receipt schema, models and factories

**Files:**
- Create: `database/migrations/2026_10_09_100008_create_incoming_receipts_table.php`
- Create: `database/migrations/2026_10_09_100009_create_incoming_receipt_lines_table.php`
- Create: `app/Models/IncomingReceipt.php`, `IncomingReceiptLine.php`
- Create: `database/factories/IncomingReceiptFactory.php`, `IncomingReceiptLineFactory.php`
- Test: `tests/Unit/Models/IncomingReceiptTest.php`

**Interfaces:**
- Consumes: `Batch` (Task 3), `Product`/`Supplier`/`Carrier` (Task 2), `Room` (existing), `IncomingType`/`ProductStatus`/`StorageRequirement` (Task 1), `HasSignOffs` (Task 6).
- Produces: `IncomingReceipt` implementing `HasSignOffs`, with `documentType(): string` returning `'incoming_receipt'`; casts `incoming_type` → `IncomingType`, `product_status` → `ProductStatus`, `storage_requirement` → `StorageRequirement`, `receipt_date` → `date`, `unpacking_start_at`/`unpacking_finish_at` → `datetime`; relations `lines(): HasMany`, `location()`, `supplier()`, `carrier()`. `IncomingReceiptLine` with relations `receipt(): BelongsTo`, `batch(): BelongsTo`, `room(): BelongsTo`. Factories require nothing.

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Models/IncomingReceiptTest.php
use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;

it('reports its sign-off document type', function () {
    expect(IncomingReceipt::factory()->create()->documentType())->toBe('incoming_receipt');
});

it('casts the incoming type and product status', function () {
    $receipt = IncomingReceipt::factory()->create([
        'incoming_type' => 'new_inventory_po',
        'product_status' => 'quarantine',
    ]);

    expect($receipt->fresh()->incoming_type)->toBe(IncomingType::NEW_INVENTORY_PO)
        ->and($receipt->fresh()->product_status)->toBe(ProductStatus::QUARANTINE);
});

it('rejects a duplicate receipt number', function () {
    IncomingReceipt::factory()->create(['receipt_no' => '0001/WH/2/PB/MJG/VIII/26']);

    expect(fn () => IncomingReceipt::factory()->create(['receipt_no' => '0001/WH/2/PB/MJG/VIII/26']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('cascades line deletion with the receipt', function () {
    $receipt = IncomingReceipt::factory()->create();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $receipt->delete();

    expect(IncomingReceiptLine::count())->toBe(0);
});

it('keeps line text as a snapshot when the product changes', function () {
    $line = IncomingReceiptLine::factory()->create(['product_description' => 'ORIGINAL TEXT']);

    $line->batch->product->update(['description' => 'CHANGED LATER']);

    expect($line->fresh()->product_description)->toBe('ORIGINAL TEXT');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/IncomingReceiptTest.php`
Expected: FAIL — `Class "App\Models\IncomingReceipt" not found`.

- [ ] **Step 3: Implement the migrations, models and factories**

Columns are spec §5's `incoming_receipts` and `incoming_receipt_lines` tables. Points that are easy to get wrong:

- `receipt_no` is `string` unique. It is **not** nullable — `CreateIncomingReceipt` issues it before insert.
- `incoming_receipt_lines` cascades on `incoming_receipt_id`, but `batch_id` uses `restrictOnDelete()` — a line must not silently lose its batch.
- Line columns `internal_code`, `product_description`, `lot_no`, `expiry_date`, `quantity`, `uom` are **snapshots**, deliberately duplicated from the batch/product. The last test pins this.
- `expiry_date` on lines is `date` nullable (a line may legitimately record no expiry) — the sentinel only applies to `batches`, because only there does it drive a unique index.
- `registration_label_qa_by` FK → users, nullable, `nullOnDelete()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/IncomingReceiptTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/IncomingReceipt.php app/Models/IncomingReceiptLine.php database/factories tests/Unit/Models/IncomingReceiptTest.php
git commit -m "feat: add incoming receipt schema and models"
```

---

### Task 8: Master data Filament resources

**Files:**
- Create: `app/Filament/Resources/Principals/{PrincipalResource.php,Pages/{List,Create,Edit}Principal.php}`
- Create: `app/Filament/Resources/Products/{ProductResource.php,Pages/{List,Create,Edit}Product.php}`
- Create: `app/Filament/Resources/Suppliers/…`, `Carriers/…`
- Test: `tests/Feature/MasterDataAccessTest.php`

**Interfaces:**
- Consumes: `Product`, `Principal`, `Supplier`, `Carrier` (Task 2); `HasLocationBasedAccess`.
- Produces: four Filament resources under the `Master Data` navigation group, each with `index`/`create`/`edit` pages. `canViewAny()` restricted to `['Super Admin', 'Admin']`. `LocationResource` and `RoomResource` already exist and are **not** modified.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/MasterDataAccessTest.php
use App\Models\User;

it('shows the products list to an admin', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    $this->actingAs($admin)->get('/dashboard/products')->assertSuccessful();
});

it('hides the products list from a supply chain officer', function () {
    $officer = User::factory()->create()->assignRole('Supply Chain Officer');

    $this->actingAs($officer)->get('/dashboard/products')->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/MasterDataAccessTest.php`
Expected: FAIL — 404, route `/dashboard/products` does not exist.

- [ ] **Step 3: Implement the four resources**

Follow `SerialNumberResource`'s shape — a single `form()`/`table()` on the resource, `getPages()` returning `index`/`create`/`edit`, `canViewAny()` gated on `['Super Admin', 'Admin']`, `->header(view('filament.tables.top-bottom-pagination-tables', ...))` on the table to match house style.

`ProductResource` additionally shows a `source` badge column and disables the `internal_code` field on edit (it is the stable key the SAP import matches on).

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/MasterDataAccessTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources tests/Feature/MasterDataAccessTest.php
git commit -m "feat: add master data Filament resources"
```

---

### Task 9: `IncomingReceiptResource` and the create page

**Files:**
- Create: `app/Filament/Resources/IncomingReceipts/IncomingReceiptResource.php`
- Create: `app/Filament/Resources/IncomingReceipts/Schemas/IncomingReceiptForm.php`
- Create: `app/Filament/Resources/IncomingReceipts/Tables/IncomingReceiptsTable.php`
- Create: `app/Filament/Resources/IncomingReceipts/Pages/{List,Create,Edit,View}IncomingReceipt.php`
- Test: `tests/Feature/IncomingReceiptCreateTest.php`

**Interfaces:**
- Consumes: `DocumentNumberService` (Task 4), `BatchResolver` (Task 5), `SignOffService` (Task 6), `IncomingReceipt`/`IncomingReceiptLine` (Task 7).
- Produces: route `/dashboard/incoming-receipts`. `CreateIncomingReceipt::handleRecordCreation(array $data): IncomingReceipt` performs number issuance, batch resolution and first sign-off in one transaction.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/IncomingReceiptCreateTest.php
use App\Models\Batch;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;
use App\Filament\Resources\IncomingReceipts\Pages\CreateIncomingReceipt;

beforeEach(function () {
    $this->location = Location::factory()->create();
    $this->officer = User::factory()->create(['location_id' => $this->location->id])
        ->assignRole('Supply Chain Officer');
    $this->actingAs($this->officer);
});

function receiptPayload(Location $location, Product $product, array $overrides = []): array
{
    return array_merge([
        'location_id' => $location->id,
        'receipt_date' => '2026-08-05',
        'receipt_time' => '10:50',
        'incoming_type' => 'new_inventory_po',
        'product_status' => 'quarantine',
        'remark' => 'Stock packaging gudang cabang Sidoarjo',
        'lines' => [[
            'product_id' => $product->id,
            'internal_code' => $product->internal_code,
            'product_description' => $product->description,
            'lot_no' => '471000',
            'expiry_date' => '2025-12-31',
            'quantity' => 5,
            'uom' => 'KIT',
        ]],
    ], $overrides);
}

it('creates a receipt with one line and one batch', function () {
    $product = Product::factory()->create();

    Livewire::test(CreateIncomingReceipt::class)
        ->fillForm(receiptPayload($this->location, $product))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(IncomingReceipt::count())->toBe(1)
        ->and(IncomingReceiptLine::count())->toBe(1)
        ->and(Batch::count())->toBe(1)
        ->and(IncomingReceipt::first()->receipt_no)->not->toBeEmpty();
});

it('records the first sign-off on the creating officer', function () {
    $product = Product::factory()->create();

    Livewire::test(CreateIncomingReceipt::class)
        ->fillForm(receiptPayload($this->location, $product))
        ->call('create');

    $signoff = IncomingReceipt::first()->signoffs()->first();

    expect($signoff->step)->toBe('received_and_processed')
        ->and($signoff->user_id)->toBe($this->officer->id);
});

it('merges a repeated lot into the same batch', function () {
    $product = Product::factory()->create();
    $line = receiptPayload($this->location, $product)['lines'][0];

    Livewire::test(CreateIncomingReceipt::class)
        ->fillForm(receiptPayload($this->location, $product, ['lines' => [$line, $line]]))
        ->call('create');

    expect(Batch::count())->toBe(1)
        ->and(Batch::first()->quantity)->toBe('10.00')
        ->and(IncomingReceiptLine::count())->toBe(2);
});

it('rejects a receipt with no line items', function () {
    Livewire::test(CreateIncomingReceipt::class)
        ->fillForm(receiptPayload($this->location, Product::factory()->create(), ['lines' => []]))
        ->call('create')
        ->assertHasFormErrors(['lines']);

    expect(IncomingReceipt::count())->toBe(0);
});

it('does not issue a number when the receipt fails validation', function () {
    Livewire::test(CreateIncomingReceipt::class)
        ->fillForm(receiptPayload($this->location, Product::factory()->create(), ['lines' => [], 'receipt_date' => null]))
        ->call('create');

    expect(Illuminate\Support\Facades\DB::table('document_sequences')->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptCreateTest.php`
Expected: FAIL — `Class "App\Filament\Resources\IncomingReceipts\Pages\CreateIncomingReceipt" not found`.

- [ ] **Step 3: Implement the resource, form, table and pages**

The form is spec §7's construction: header `Section`s for receipt identity, parties, inspection and unpacking, then a `Repeater::make('lines')->reorderable()->minItems(1)->addActionLabel('Add line')` whose row schema carries the twelve line fields. `receipt_no` is a read-only `TextInput` populated on create, never typed.

`CreateIncomingReceipt` overrides `handleRecordCreation(array $data)` instead of `mutateFormDataBeforeCreate`, because the line items must be resolved to batches inside the same transaction as the number issuance:

```
DB::transaction:
  $number = app(DocumentNumberService::class)->issue('incoming_receipt', $data['location_id'], $data['receipt_date'])
  $receipt = IncomingReceipt::create([...header..., 'receiptId' => Str::orderedUuid(), 'receipt_no' => $number, 'created_by' => auth()->id()])
  foreach ($data['lines'] as $i => $line):
      $batch = app(BatchResolver::class)->resolve(Product::find($line['product_id']), $line['lot_no'], $line['expiry_date'], $line['quantity'], $line['uom'], $line['room_id'] ? Room::find($line['room_id']) : null, $data['location_id'])
      $receipt->lines()->create([...snapshot fields..., 'batch_id' => $batch->id, 'line_no' => $i + 1])
  app(SignOffService::class)->record($receipt, 'received_and_processed')
  return $receipt
```

The `minItems(1)` on the Repeater is what makes Review Focus item 3 hold; the last test pins that a failed submission leaves no sequence row behind.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptCreateTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/IncomingReceipts tests/Feature/IncomingReceiptCreateTest.php
git commit -m "feat: add incoming receipt resource and transactional create"
```

---

### Task 10: View page, sign-off actions and location scoping

**Files:**
- Modify: `app/Filament/Resources/IncomingReceipts/Pages/ViewIncomingReceipt.php`
- Modify: `app/Filament/Resources/IncomingReceipts/IncomingReceiptResource.php`
- Test: `tests/Feature/IncomingReceiptSignOffTest.php`
- Test: `tests/Feature/IncomingReceiptAccessTest.php`

**Interfaces:**
- Consumes: `SignOffService` (Task 6), `HasLocationBasedAccess`.
- Produces: a `Sign` action on the view page, one per unsigned step, visible only when `SignOffService::canSign()`; `IncomingReceiptResource::getEloquentQuery()` applying the location filter.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Feature/IncomingReceiptSignOffTest.php
use App\Models\IncomingReceipt;
use App\Models\User;
use App\Filament\Resources\IncomingReceipts\Pages\ViewIncomingReceipt;
use Livewire\Livewire;

it('lets the correct role sign the next step', function () {
    $receipt = IncomingReceipt::factory()->create();
    $manager = User::factory()->create(['initial' => 'SUP'])->assignRole('Supply Chain Manager');
    $this->actingAs($manager);

    Livewire::test(ViewIncomingReceipt::class, ['record' => $receipt->getKey()])
        ->callAction('sign_checked_by');

    expect($receipt->fresh()->signoffs()->where('step', 'checked_by')->exists())->toBeTrue();
});

it('hides the sign action from the wrong role', function () {
    $receipt = IncomingReceipt::factory()->create();
    $staff = User::factory()->create()->assignRole('QA Staff');
    $this->actingAs($staff);

    Livewire::test(ViewIncomingReceipt::class, ['record' => $receipt->getKey()])
        ->assertActionHidden('sign_checked_by');
});
```

```php
// tests/Feature/IncomingReceiptAccessTest.php
use App\Models\IncomingReceipt;
use App\Models\Location;
use App\Models\User;

it('shows an officer only their own location receipts', function () {
    $mine = Location::factory()->create();
    $theirs = Location::factory()->create();
    $officer = User::factory()->create(['location_id' => $mine->id])->assignRole('Supply Chain Officer');

    IncomingReceipt::factory()->create(['location_id' => $mine->id, 'receipt_no' => 'MINE/1']);
    IncomingReceipt::factory()->create(['location_id' => $theirs->id, 'receipt_no' => 'THEIRS/1']);

    $this->actingAs($officer)->get('/dashboard/incoming-receipts')
        ->assertSuccessful()
        ->assertSee('MINE/1')
        ->assertDontSee('THEIRS/1');
});

it('shows a manager every location', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();
    $manager = User::factory()->create()->assignRole('Supply Chain Manager');

    IncomingReceipt::factory()->create(['location_id' => $a->id, 'receipt_no' => 'A/1']);
    IncomingReceipt::factory()->create(['location_id' => $b->id, 'receipt_no' => 'B/1']);

    $this->actingAs($manager)->get('/dashboard/incoming-receipts')
        ->assertSuccessful()
        ->assertSee('A/1')
        ->assertSee('B/1');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptSignOffTest.php tests/Feature/IncomingReceiptAccessTest.php`
Expected: FAIL — no `sign_checked_by` action; the access tests see all receipts.

- [ ] **Step 3: Implement the view page and scoping**

- `ViewIncomingReceipt` builds one `Action::make("sign_{$step}")` per configured step, `->visible(fn () => app(SignOffService::class)->canSign($record, $step, auth()->user()))`, `->requiresConfirmation()`, and an action that calls `SignOffService::record()` inside a try/catch converting `SignOffException` to a `Filament\Notifications\Notification::make()->danger()`.
- The view page renders the signed steps as read-only `TextEntry`s showing `signer_name` and `signed_at`, plus the line items as a read-only repeater.
- `IncomingReceiptResource::getEloquentQuery()` returns `static::applyLocationFilter(parent::getEloquentQuery())`.
- The `Edit` action is visible only when `signoffs()->count() === 0` or the user is `Super Admin` (spec §8).

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptSignOffTest.php tests/Feature/IncomingReceiptAccessTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/IncomingReceipts tests/Feature/IncomingReceiptSignOffTest.php tests/Feature/IncomingReceiptAccessTest.php
git commit -m "feat: add sign-off actions and location scoping to incoming receipts"
```

---

### Task 11: Print view and PDF route

**Files:**
- Create: `resources/views/print/incoming-receipt.blade.php`
- Modify: `routes/web.php` — add the print route
- Test: `tests/Feature/IncomingReceiptPrintTest.php`

**Interfaces:**
- Consumes: `IncomingReceipt` with `lines`, `signoffs` (Tasks 7, 6); `config('documents.form_metadata.incoming_receipt')` (Task 1).
- Produces: named route `incoming-receipts.print`, `GET /incoming-receipts/{record}/print`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/IncomingReceiptPrintTest.php
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;

it('renders the controlled document metadata', function () {
    $receipt = IncomingReceipt::factory()->create();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create(['product_description' => 'DIASPECT HEMOGLOBIN CUVETTES']);

    $html = view('print.incoming-receipt', ['receipt' => $receipt->load('lines', 'signoffs', 'location', 'supplier', 'carrier')])->render();

    expect($html)->toContain('MJG-FOR-SCM.02-002-02')
        ->toContain('16 OCT 2025')
        ->toContain('INCOMING RECEIPT INSPECTION')
        ->toContain('DIASPECT HEMOGLOBIN CUVETTES');
});

it('renders each signer name into the footer', function () {
    $receipt = IncomingReceipt::factory()->create();
    $receipt->signoffs()->create([
        'document_type' => 'incoming_receipt',
        'step' => 'received_and_processed',
        'role_required' => 'Supply Chain Officer',
        'signer_name' => 'IAN 05 AUG 2026',
        'signed_at' => now(),
    ]);

    $html = view('print.incoming-receipt', ['receipt' => $receipt->load('lines', 'signoffs')])->render();

    expect($html)->toContain('IAN 05 AUG 2026');
});

it('prints N/A for a sentinel expiry', function () {
    $receipt = IncomingReceipt::factory()->create();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create(['expiry_date' => null, 'lot_no' => 'N/A']);

    $html = view('print.incoming-receipt', ['receipt' => $receipt->load('lines', 'signoffs')])->render();

    expect($html)->toContain('N/A');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptPrintTest.php`
Expected: FAIL — `View [print.incoming-receipt] not found`.

- [ ] **Step 3: Implement the blade and the route**

Copy the structure of `resources/views/print/header/temperature-deviation.blade.php`: Bootstrap from `asset('assets/bootstrap/css/bootstrap.min.css')`, the blue logo cell (`#0E0F97`) with `LOGO-MEDQUEST-HD.png`, the `FORM` / `INCOMING RECEIPT INSPECTION` title rows, and the four-line doc-no block fed from `config('documents.form_metadata.incoming_receipt')`.

Then the spec §2 field grid, the line-item table, and the footer signature grid with each step's `signer_name` from `$receipt->signoffs`.

Checkbox cells render `☑` / `☐` from the enum and boolean values.

Route, added beside the existing Browsershot routes in `routes/web.php`:

```php
Route::get('/incoming-receipts/{record}/print', function (IncomingReceipt $record) {
    $html = view('print.incoming-receipt', [
        'receipt' => $record->load('lines.batch.product', 'signoffs', 'location', 'supplier', 'carrier'),
    ]);

    $pdf = Browsershot::html($html)->format('A4')->margins(3, 3, 3, 3)->landscape()->showBackground()->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="IncomingReceipt_'.$record->receipt_no.'.pdf"');
})->name('incoming-receipts.print')->middleware(['auth']);
```

The three tests render the view directly rather than through Browsershot, matching `TemperatureDeviationPrintTest` — do not invoke Chrome in the suite.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/IncomingReceiptPrintTest.php`
Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add resources/views/print/incoming-receipt.blade.php routes/web.php tests/Feature/IncomingReceiptPrintTest.php
git commit -m "feat: add incoming receipt print view and pdf route"
```

---

### Task 12: SAP product import

**Files:**
- Create: `config/sap.php`
- Create: `app/Services/Sap/ProductImportService.php`
- Create: `app/Services/Sap/SapImportResult.php`
- Create: `app/Console/Commands/ImportSapProducts.php`
- Test: `tests/Feature/SapProductImportTest.php`

**Interfaces:**
- Consumes: `Product`, `Principal` (Task 2), `ProductSource` (Task 1).
- Produces: `SapImportResult` readonly class with `int $created`, `int $updated`, `int $skipped`. `ProductImportService::import(bool $force = false): SapImportResult`. Artisan command signature `sap:import-products {--force}`.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/SapProductImportTest.php
use App\Enums\ProductSource;
use App\Models\Product;
use App\Services\Sap\ProductImportService;

beforeEach(function () {
    config()->set('sap.enabled', true);
    config()->set('sap.rows', [
        ['sap_code' => 'SAP-001', 'internal_code' => 'DIS-T-00003', 'description' => 'DIASPECT CUVETTES', 'uom' => 'PCS', 'principal_code' => 'DIASPECT'],
    ]);
});

it('creates a product from a SAP row', function () {
    $result = app(ProductImportService::class)->import();

    expect($result->created)->toBe(1)
        ->and(Product::where('internal_code', 'DIS-T-00003')->first()->source)->toBe(ProductSource::SAP);
});

it('never overwrites a manually created product', function () {
    Product::factory()->create([
        'internal_code' => 'DIS-T-00003',
        'sap_code' => 'SAP-001',
        'description' => 'LOCAL EDIT',
        'source' => 'manual',
    ]);

    app(ProductImportService::class)->import();

    expect(Product::first()->description)->toBe('LOCAL EDIT');
});

it('keeps a user-corrected uom on re-import', function () {
    app(ProductImportService::class)->import();

    Product::where('internal_code', 'DIS-T-00003')->update(['uom' => 'BOX', 'uom_source' => 'manual']);

    app(ProductImportService::class)->import();

    expect(Product::first()->uom)->toBe('BOX');
});

it('refreshes a sap-sourced uom on re-import', function () {
    app(ProductImportService::class)->import();

    Product::where('internal_code', 'DIS-T-00003')->update(['uom_source' => 'sap']);
    config()->set('sap.rows', [['sap_code' => 'SAP-001', 'internal_code' => 'DIS-T-00003', 'description' => 'DIASPECT CUVETTES', 'uom' => 'EA', 'principal_code' => 'DIASPECT']]);

    app(ProductImportService::class)->import();

    expect(Product::first()->uom)->toBe('EA');
});

it('does nothing when the import is disabled', function () {
    config()->set('sap.enabled', false);

    $result = app(ProductImportService::class)->import();

    expect($result->created)->toBe(0)
        ->and(Product::count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/SapProductImportTest.php`
Expected: FAIL — `Class "App\Services\Sap\ProductImportService" not found`.

- [ ] **Step 3: Implement the config, result, service and command**

`config/sap.php` holds the connection name, an `enabled` flag defaulting to `false`, and a `rows` key. The client's SAP table list is still outstanding (spec §10 item 5), so `rows` is the injection point: in production it is populated by querying the configured connection using a mapping array that is **left empty** with a comment naming the outstanding decision; in tests it is set directly. `import()` reads `config('sap.rows')` when the mapping is empty.

`import()` logic:
1. Return an empty result immediately if `! config('sap.enabled')`.
2. For each row: find a `Product` by `sap_code`, or by `internal_code` when `sap_code` is null.
3. **Not found** → create with `source = 'sap'`, `uom_source = 'sap'`; count as created.
4. **Found and `source === MANUAL`** → skip entirely, count as skipped. This is Review Focus item 5.
5. **Found and `source === SAP`** → update `description` and `principal_code`; update `uom` only when `uom_source === 'sap'`. `--force` overrides both guards.
6. Resolve or create the `Principal` from `principal_code`.

Wrap the whole run in a transaction; on driver failure nothing commits (spec §8).

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/SapProductImportTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
git add config/sap.php app/Services/Sap app/Console/Commands/ImportSapProducts.php tests/Feature/SapProductImportTest.php
git commit -m "feat: add one-time SAP product bootstrap import"
```

---

### Task 13: Demonstration seeders

**Files:**
- Create: `database/seeders/InventoryMasterDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` — call the new seeder
- Test: `tests/Feature/InventorySeederTest.php`

**Interfaces:**
- Consumes: every model from Tasks 2, 3, 7.
- Produces: idempotent seeding of the master data visible in the sample forms.

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/InventorySeederTest.php
use App\Models\Product;
use Database\Seeders\InventoryMasterDataSeeder;

it('seeds the sample form products idempotently', function () {
    $this->seed(InventoryMasterDataSeeder::class);
    $this->seed(InventoryMasterDataSeeder::class);

    expect(Product::where('internal_code', 'DIS-T-00003')->count())->toBe(1)
        ->and(Product::where('internal_code', 'PAC-N-00008')->count())->toBe(1);
});

it('does not seed rooms that already exist', function () {
    $location = Location::factory()->create();
    Room::factory()->for($location)->create(['room_name' => 'Cold Storage 4']);
    $before = Room::count();

    $this->seed(InventoryMasterDataSeeder::class);

    expect(Room::where('room_name', 'Cold Storage 4')->count())->toBe(1)
        ->and(Room::count())->toBeGreaterThanOrEqual($before);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/InventorySeederTest.php`
Expected: FAIL — `Class "Database\Seeders\InventoryMasterDataSeeder" not found`.

- [ ] **Step 3: Implement the seeder**

Use `updateOrCreate` keyed on the stable identifier (`internal_code` for products, `room_name` within a location for rooms) so re-running is safe — the first test pins idempotency.

Seed the values actually read off the forms. Products `DIS-T-00003` (9DC.0001, Diaspect Hemoglobin Cuvettes, DIASPECT), `DIS-T-00007`, `MDI-T-00001`, `DIA-T-00076`, `DIA-T-00077`, `PAC-N-00008` (KARTON LEMBARAN DOUBLE K150/M X 4 UKURAN 48 X 31 CM), and the Grifols set `GHL-T-00021`…`GHL-T-00027`. Principals `DIASPECT`. Rooms: the existing `RoomSeeder` already covers `Cold Storage 1–4`, `Air Cond Storage 1–3` and `Gudang 1–3 Bizpark 2`. Only add rooms the forms name that are missing — `AREA PACKAGING` — via `firstOrCreate` on `(location_id, room_name)` so the seeder stays idempotent and does not duplicate `RoomSeeder`'s rows. Carriers `PT. JAYA GUNA JATI`, `IOL LOGISTICS`. Suppliers `PT. JAYA GUNA JATI`, `GRIFOLS - SINGAPORE`, `PT. MEDQUEST JAYA GLOBAL`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/InventorySeederTest.php`
Expected: PASS, 2 tests.

- [ ] **Step 5: Commit**

```bash
git add database/seeders tests/Feature/InventorySeederTest.php
git commit -m "feat: seed inventory master data from sample forms"
```

---

## Plan Self-Review

**Spec coverage.** §1 success criteria → Tasks 9 (entry + batches), 11 (print), 6/10 (sign-offs), 10 (traceability, location access). §2 context patterns → reused in Tasks 6, 10, 11. §4 architecture → Tasks 1–7, 9. §5 data model → Tasks 2, 3, 6, 7. §5.1–5.4 enums → Task 1. §5.5 steps → Tasks 1, 6. §6 services → Tasks 4, 5, 6, 12. §7 presentation → Tasks 8, 9, 10, 11. §8 error handling → Tasks 4 (rollback), 6 (exceptions), 9 (transaction), 12 (manual guard). §9 testing → every task. §11 delivery order → matches Tasks 1–13, except the spec's step 7 (seeders) which is Task 13.

Spec §10's open items are **not** implementable tasks — they are client decisions. Items 1 and 2 are absorbed as configurable defaults (Tasks 1, 4) with comments naming the decision. Item 7 (audit trail) is deliberately not implemented; the Global Constraints forbid the dependency. Items 8 and 9 are out of scope and noted in Global Constraints.

**Type consistency.** `normalizeLotNo`/`normalizeExpiryDate` are static on `BatchResolver` and called as such in Task 5's tests. `Batch::EXPIRY_SENTINEL` is defined in Task 3 and referenced in Task 5. `documentType()` is declared on `HasSignOffs` in Task 6 and implemented on `IncomingReceipt` in Task 7. `signoffs()` is declared in Task 6 and used in Tasks 10, 11. `SignOffService::record` takes `HasSignOffs`, and every caller passes a model implementing it. `ProductSource`/`BatchStatus` values used in tests match Task 1.

**Proportion.** 13 tasks against a 752-line spec. Code blocks are test bodies and one pseudo-code transaction sketch; production bodies are left to the implementer with exact signatures.

**Review Focus.** All five lines have an owning task with a test: 1 → Task 5, 2 → Task 4, 3 → Task 9, 4 → Task 6, 5 → Task 12.
