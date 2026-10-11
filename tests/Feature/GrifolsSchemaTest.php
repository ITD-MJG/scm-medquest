<?php

use App\Models\DocumentSignoff;
use App\Models\IncomingReceipt;
use Illuminate\Support\Facades\Schema;

it('has the grifols columns on incoming_receipts', function () {
    expect(Schema::hasColumns('incoming_receipts', ['variant', 'disposition']))->toBeTrue();
});

it('has the grifols columns on incoming_receipt_lines', function () {
    expect(Schema::hasColumns('incoming_receipt_lines', ['part_no', 'serial_no', 'no_of_cases', 'packaging_observation']))->toBeTrue();
});

it('defaults variant to standard and keeps the plain document type', function () {
    $receipt = IncomingReceipt::factory()->create();

    expect($receipt->refresh()->variant)->toBe('standard')
        ->and($receipt->documentType())->toBe('incoming_receipt');
});

it('uses the grifols document type and filters signoffs by it', function () {
    $grifols = IncomingReceipt::factory()->create(['variant' => 'grifols']);
    $standard = IncomingReceipt::factory()->create();

    DocumentSignoff::create([
        'document_type' => 'incoming_receipt_grifols',
        'document_id' => $grifols->getKey(),
        'step' => 'received_by',
        'role_required' => 'Supply Chain Officer',
        'user_id' => null,
        'signer_name' => 'Test Signer',
        'signed_at' => now(),
    ]);
    DocumentSignoff::create([
        'document_type' => 'incoming_receipt',
        'document_id' => $grifols->getKey(),
        'step' => 'received_and_processed',
        'role_required' => 'Supply Chain Officer',
        'user_id' => null,
        'signer_name' => 'Test Signer',
        'signed_at' => now(),
    ]);

    expect($grifols->documentType())->toBe('incoming_receipt_grifols')
        ->and($grifols->signoffs()->pluck('step')->all())->toBe(['received_by'])
        ->and($standard->documentType())->toBe('incoming_receipt');
});
