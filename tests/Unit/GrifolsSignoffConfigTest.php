<?php

use App\Services\SignOffService;

it('defines the grifols sign-off steps in exact order with exact labels', function () {
    $steps = config('documents.signoff_steps.incoming_receipt_grifols');

    expect(array_keys($steps))->toBe([
        'received_by',
        'inspected_by',
        'verified_by',
        'checked_by_scm',
        'disposition',
        'sap_entry_by',
        'verified_by_whm',
    ]);

    $labels = [];
    foreach ($steps as $key => $step) {
        $labels[$key] = $step['label'];
    }

    expect($labels)->toBe([
        'received_by' => 'Received by',
        'inspected_by' => 'Inspected by',
        'verified_by' => 'Verified by',
        'checked_by_scm' => 'Checked by',
        'disposition' => 'Disposition (by Grifols SCM)',
        'sap_entry_by' => 'Entry data to the SAP System (performed by)',
        'verified_by_whm' => 'Verified by (Warehouse Manager)',
    ]);
});

it('exposes the grifols steps through SignOffService', function () {
    $steps = app(SignOffService::class)->stepsFor('incoming_receipt_grifols');

    expect(array_keys($steps))->toBe([
        'received_by',
        'inspected_by',
        'verified_by',
        'checked_by_scm',
        'disposition',
        'sap_entry_by',
        'verified_by_whm',
    ])
        ->and($steps['received_by']['role'])->toBe('Supply Chain Officer')
        ->and($steps['verified_by']['role'])->toBe('QA Supervisor')
        ->and($steps['sap_entry_by']['role'])->toBe('Supply Chain Administration System Staff')
        ->and($steps['verified_by_whm']['role'])->toBe('Warehouse Manager');
});

it('has grifols form metadata', function () {
    $meta = config('documents.form_metadata.incoming_receipt_grifols');

    expect($meta['doc_no'])->toBe('MJG-FOR-SCM.02-002-08')
        ->and($meta['effective_date'])->toBe('16 OCT 2025')
        ->and($meta['revision'])->toBe('06');
});
