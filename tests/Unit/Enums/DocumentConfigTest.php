<?php

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
