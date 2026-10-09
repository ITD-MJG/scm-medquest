<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document Number Format
    |--------------------------------------------------------------------------
    |
    | Format templates keyed by document type. Placeholders:
    | {seq} sequence number (zero-padded to `sequence_padding`),
    | {location_code} location code, {roman_month} roman numeral month,
    | {yy} two-digit year, {mm} two-digit month, {yyyy} four-digit year.
    |
    */

    'formats' => [
        'incoming_receipt' => '{seq:4}/{location_code}/PB/MJG/{roman_month}/{yy}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence Padding
    |--------------------------------------------------------------------------
    */

    'sequence_padding' => 4,

    /*
    |--------------------------------------------------------------------------
    | Form Metadata
    |--------------------------------------------------------------------------
    |
    | Printed header block for each controlled form.
    |
    */

    'form_metadata' => [
        'incoming_receipt' => [
            'doc_no' => 'MJG-FOR-SCM.02-002-02',
            'effective_date' => '16 OCT 2025',
            'revision' => '12',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sign-off Steps
    |--------------------------------------------------------------------------
    |
    | Ordered sign-off steps per document type. `role` is the primary role
    | required; Super Admin may always sign. Role names must be read from
    | here, never hardcoded in resources.
    |
    */

    'signoff_steps' => [
        'incoming_receipt' => [
            'received_and_processed' => [
                'label' => 'Received and Processed by',
                'role' => 'Supply Chain Officer',
            ],
            'checked_by' => [
                'label' => 'Checked by',
                'role' => 'Supply Chain Manager',
            ],
            'inspected_by' => [
                'label' => 'Inspected by',
                'role' => 'QA Staff',
            ],
            'verified_by' => [
                'label' => 'Verified by',
                'role' => 'QA Manager',
            ],
            'status_defined_by' => [
                'label' => 'Defined by (product status)',
                'role' => 'QA Manager',
            ],
            'acknowledged_by' => [
                'label' => 'Acknowledged by',
                'role' => 'Supply Chain Manager',
            ],
            'sap_entry_by' => [
                'label' => 'Entry data to the SAP System',
                'role' => 'Supply Chain Officer',
            ],
        ],
    ],

];
