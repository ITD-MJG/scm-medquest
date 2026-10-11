<?php

use App\Filament\Resources\IncomingReceiptGrifols\GrifolsReceiptResource;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\CreateGrifolsReceipt;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\EditGrifolsReceipt;
use App\Filament\Resources\IncomingReceiptGrifols\Pages\ListGrifolsReceipts;
use App\Filament\Resources\IncomingReceipts\Pages\ListIncomingReceipts;
use App\Models\Batch;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\SignOffService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->location = Location::factory()->create();
    $this->officer = User::factory()->create(['location_id' => $this->location->id])
        ->assignRole('Supply Chain Officer');
    $this->actingAs($this->officer);
});

function grifolsPayload(Location $location, Product $product, array $overrides = []): array
{
    return array_merge([
        'receipt_no' => '0013/WH/1/PB/GFL/MJG/IV/26',
        'location_id' => $location->id,
        'receipt_date' => '2026-08-05',
        'receipt_time' => '10:50',
        'incoming_type' => 'new_inventory_po',
        'product_status' => 'quarantine',
        'remark' => 'Grifols receipt',
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

it('creates a grifols receipt with typed receipt_no and auto-signs received_by', function () {
    $product = Product::factory()->create();

    Livewire::test(CreateGrifolsReceipt::class)
        ->fillForm(grifolsPayload($this->location, $product))
        ->call('create')
        ->assertHasNoFormErrors();

    $receipt = IncomingReceipt::first();

    expect($receipt->variant)->toBe('grifols')
        ->and($receipt->receipt_no)->toBe('0013/WH/1/PB/GFL/MJG/IV/26')
        ->and(IncomingReceiptLine::count())->toBe(1)
        ->and(Batch::count())->toBe(1);

    $signoff = $receipt->signoffs()->first();
    expect($signoff->step)->toBe('received_by')
        ->and($signoff->document_type)->toBe('incoming_receipt_grifols')
        ->and($signoff->user_id)->toBe($this->officer->id);
});

it('rejects a duplicate grifols receipt_no', function () {
    $product = Product::factory()->create();

    IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'grifols',
        'receipt_no' => '0013/WH/1/PB/GFL/MJG/IV/26',
    ]);

    Livewire::test(CreateGrifolsReceipt::class)
        ->fillForm(grifolsPayload($this->location, $product))
        ->call('create')
        ->assertHasFormErrors(['receipt_no']);

    expect(IncomingReceipt::count())->toBe(1);
});

it('lists only grifols variant receipts', function () {
    $product = Product::factory()->create();

    IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'standard',
        'receipt_no' => 'STD/1',
    ]);

    Livewire::test(CreateGrifolsReceipt::class)
        ->fillForm(grifolsPayload($this->location, $product, ['receipt_no' => 'GFL/1']))
        ->call('create');

    expect(IncomingReceipt::query()->where('variant', 'standard')->count())->toBe(1)
        ->and(IncomingReceipt::query()->where('variant', 'grifols')->count())->toBe(1);

    Livewire::test(ListGrifolsReceipts::class);

    expect(GrifolsReceiptResource::getEloquentQuery()->pluck('receipt_no')->all())
        ->toBe(['GFL/1']);
});

it('excludes grifols rows from the standard list', function () {
    $product = Product::factory()->create();

    IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'standard',
        'receipt_no' => 'STD/1',
    ]);

    IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'grifols',
        'receipt_no' => 'GFL/1',
    ]);

    Livewire::test(ListIncomingReceipts::class);

    expect(IncomingReceipt::query()->where('variant', 'grifols')->count())->toBe(1)
        ->and(IncomingReceipt::query()->where('variant', 'standard')->pluck('receipt_no')->all())->toContain('STD/1');

    // The grifols row must not render inside the standard list table.
    $this->actingAs($this->officer)
        ->get('/dashboard/incoming-receipts')
        ->assertSuccessful()
        ->assertDontSee('GFL/1')
        ->assertSee('STD/1');
});

it('locks editing a signed grifols receipt for officers but not super admins', function () {
    $product = Product::factory()->create();
    $receipt = IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'grifols',
    ]);

    app(SignOffService::class)->record($receipt, 'received_by', $this->officer);

    expect(GrifolsReceiptResource::canEdit($receipt))->toBeFalse();

    $admin = User::factory()->create()->assignRole('Super Admin');
    $this->actingAs($admin);

    expect(GrifolsReceiptResource::canEdit($receipt))->toBeTrue();
});

it('records one edit_override when a super admin saves a signed grifols receipt', function () {
    $product = Product::factory()->create();
    $receipt = IncomingReceipt::factory()->create([
        'location_id' => $this->location->id,
        'variant' => 'grifols',
        'remark' => 'original',
    ]);

    app(SignOffService::class)->record($receipt, 'received_by', $this->officer);

    $admin = User::factory()->create()->assignRole('Super Admin');

    Livewire::actingAs($admin)->test(EditGrifolsReceipt::class, ['record' => $receipt->getKey()])
        ->fillForm([
            'remark' => 'edited under override',
            'lines' => [[
                'product_id' => $product->getKey(),
                'internal_code' => $product->internal_code,
                'product_description' => $product->description,
                'lot_no' => 'LOT-1',
                'quantity' => 5,
                'uom' => $product->uom,
            ]],
        ])
        ->call('save');

    $receipt->refresh();

    expect($receipt->signoffs()->where('step', 'edit_override')->count())->toBe(1)
        ->and($receipt->signoffs()->where('step', 'edit_override')->first()->role_required)->toBe('Super Admin')
        ->and($receipt->signoffs()->where('step', 'edit_override')->first()->user_id)->toBe($admin->id);
});

it('scopes a grifols officer to their own location only', function () {
    $mine = $this->location;
    $theirs = Location::factory()->create();

    IncomingReceipt::factory()->create([
        'location_id' => $mine->id,
        'variant' => 'grifols',
        'receipt_no' => 'MINE-GFL/1',
    ]);
    IncomingReceipt::factory()->create([
        'location_id' => $theirs->id,
        'variant' => 'grifols',
        'receipt_no' => 'THEIRS-GFL/1',
    ]);

    $this->actingAs($this->officer)->get('/dashboard/incoming-receipts-grifols')
        ->assertSuccessful()
        ->assertSee('MINE-GFL/1')
        ->assertDontSee('THEIRS-GFL/1');
});

it('does not touch the standard document sequence on grifols create', function () {
    $product = Product::factory()->create();

    Livewire::test(CreateGrifolsReceipt::class)
        ->fillForm(grifolsPayload($this->location, $product))
        ->call('create');

    expect(DB::table('document_sequences')->count())->toBe(0);
});
