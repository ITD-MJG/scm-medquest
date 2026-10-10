<?php

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
