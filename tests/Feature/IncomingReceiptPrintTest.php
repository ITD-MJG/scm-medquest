<?php

use App\Models\Carrier;
use App\Models\IncomingReceipt;
use App\Models\IncomingReceiptLine;
use App\Models\Supplier;

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

it('prints the real po, supplier, carrier, unpacking times and temp result', function () {
    $supplier = Supplier::factory()->create(['name' => 'PT Sumber Sehat']);
    $carrier = Carrier::factory()->create(['name' => 'Ekspres Cargo']);

    $receipt = IncomingReceipt::factory()->create([
        'purchase_order_no' => 'PO-2026-0417',
        'product_temp_result' => 5.20,
        'unpacking_start_at' => '2026-02-03 08:15:00',
        'unpacking_finish_at' => '2026-02-03 09:45:00',
        'supplier_id' => $supplier->id,
        'carrier_id' => $carrier->id,
    ]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = view('print.incoming-receipt', ['receipt' => $receipt->load('lines', 'signoffs', 'supplier', 'carrier', 'location')])->render();

    expect($html)->toContain('PO-2026-0417')
        ->toContain('PT Sumber Sehat')
        ->toContain('Ekspres Cargo')
        ->toContain('5.2');
});

it('does not print the missing-value fallback when header values exist', function () {
    $receipt = IncomingReceipt::factory()->create([
        'purchase_order_no' => 'PO-2026-0417',
        'product_temp_result' => 5.20,
        'measured_by_device' => false,
        'unpacking_start_at' => '2026-02-03 08:15:00',
        'unpacking_finish_at' => '2026-02-03 09:45:00',
    ]);
    IncomingReceiptLine::factory()->for($receipt, 'receipt')->create();

    $html = view('print.incoming-receipt', ['receipt' => $receipt->load('lines', 'signoffs', 'supplier', 'carrier', 'location')])->render();

    // Labels and values are two parallel columns: find the value slot by
    // counting the same number of ':&ensp;' cells after the label column.
    $pinned = [
        1 => 'PO-2026-0417',   // PO No. is the 2nd row
        7 => '2026-02-03',     // Unpacking Start / Finish Time is the 8th row
    ];

    $firstCol = strpos($html, 'Receipt No.');

    foreach ($pinned as $rowIndex => $value) {
        $after = substr($html, (int) $firstCol);
        $valuePos = 0;

        for ($i = 0; $i <= $rowIndex; $i++) {
            $valuePos = strpos($after, ':&ensp;', $valuePos + 1);
        }

        expect($valuePos)->toBeInt();
        expect(substr($after, (int) $valuePos, 24))->toContain($value);
    }

    // The '-' fallback must not appear on any pinned field row.
    foreach (['PO-2026-0417', '2026-02-03'] as $value) {
        $row = substr($html, (int) strpos($html, $value) - 40, 120);

        expect($row)->not->toContain(':&ensp;-');
    }
});
