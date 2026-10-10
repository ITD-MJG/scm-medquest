<?php

use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use Illuminate\Database\QueryException;

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
        ->toThrow(QueryException::class);
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
