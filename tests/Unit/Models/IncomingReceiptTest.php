<?php

use App\Enums\IncomingType;
use App\Enums\ProductStatus;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

it('derives the receipt total from its line quantities', function () {
    $receipt = IncomingReceipt::factory()->create();

    foreach ([5, 10, 10, 5] as $index => $quantity) {
        IncomingReceiptLine::factory()->for($receipt, 'receipt')->create([
            'line_no' => $index + 1,
            'quantity' => $quantity,
        ]);
    }

    expect($receipt->fresh()->receipt_total)->toBe(30.0);
});

it('derives the receipt total without querying when lines are loaded', function () {
    $receipt = IncomingReceipt::factory()->create();
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create(['quantity' => 4]);
    $receipt->load('lines');

    DB::enableQueryLog();
    $total = $receipt->receipt_total;
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($total)->toBe(4.0)->and($queries)->toBeEmpty();
});

it('derives a zero receipt total when the receipt has no lines', function () {
    $receipt = IncomingReceipt::factory()->create();

    expect($receipt->receipt_total)->toBe(0.0);
});

it('casts the grifols paper parity columns', function () {
    $receipt = IncomingReceipt::factory()->create([
        'registration_label_attached' => 1,
        'duration_of_transportation_hours' => 2.5,
    ]);

    $receipt = $receipt->fresh();

    expect($receipt->registration_label_attached)->toBeTrue()
        ->and($receipt->duration_of_transportation_hours)->toBe('2.50');
});

it('casts the grifols paper parity line columns', function () {
    $line = IncomingReceiptLine::factory()->create([
        'unit_per_case' => '12',
        'isn_or_packing_list' => 0,
        'physical_movement_by' => 'A. SANTOS',
    ]);

    $line = $line->fresh();

    expect($line->unit_per_case)->toBe(12)
        ->and($line->isn_or_packing_list)->toBeFalse()
        ->and($line->physical_movement_by)->toBe('A. SANTOS');
});

it('resolves the user who labeled the line', function () {
    $user = User::factory()->create();
    $line = IncomingReceiptLine::factory()->create(['labeled_by' => $user->id])->fresh();

    expect($line->labeledBy)->toBeInstanceOf(User::class)
        ->and($line->labeledBy->id)->toBe($user->id);
});

it('leaves the labeling user null when the line is unlabeled', function () {
    $line = IncomingReceiptLine::factory()->create(['labeled_by' => null])->fresh();

    expect($line->labeledBy)->toBeNull();
});
