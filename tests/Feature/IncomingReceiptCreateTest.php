<?php

use App\Filament\Resources\IncomingReceipts\Pages\CreateIncomingReceipt;
use App\Models\Batch;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
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

    expect(DB::table('document_sequences')->count())->toBe(0);
});
