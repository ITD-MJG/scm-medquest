<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incoming Receipt Inspection PDF</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: white;
            margin: 0;
            padding: 0;
        }

        .table {
            font-size: 9px;
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.3rem;
            vertical-align: middle;
            border: 1px solid black !important;
            word-wrap: break-word;
        }

        .table th {
            font-size: 9px;
            text-align: center;
        }
            font-size: 9px;
            text-align: center;
        }

        .header-grid .left-col,
        .header-grid .right-col {
            vertical-align: top;
        }

        .header-grid .field-row {
            display: flex;
            align-items: flex-start;
            padding: 1px 0;
            line-height: 1.3;
            word-break: break-word;
        }

        .header-grid .field-row > div:not(.label) {
            flex: 1 1 auto;
            min-width: 0;
        }

        .header-grid .left-col .label {
            flex: 0 0 45%;
        }

        .header-grid .right-col .label {
            flex: 0 0 40%;
        }

        .header-grid .right-col {
            padding: 0.3rem 0.3rem 0.3rem 0.3rem;
        }

        .header-grid .unpacking-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-top: 1px solid black;
            border-left: none;
            border-right: none;
            margin: 0;
        }

        .header-grid .unpacking-table td {
            vertical-align: top;
            padding: 0.3rem;
            border: none !important;
        }

        .header-grid .unpacking-table .unpacking-label {
            width: 40%;
            border-right: 1px solid black !important;
        }

        .header-grid .unpacking-table .helper {
            font-style: italic;
        }
        .header-container {
            border: 1px solid black;
            margin-bottom: 8px;
            min-height: 60px;
        }

        .border-black {
            border: 1px solid black !important;
        }

        .field-box {
            border: 1px solid black;
            padding: 4px 8px;
        }

        .field-row {
            margin: 0;
        }

        .notes {
            font-size: 10px;
            font-style: italic;
            margin: 2px 0 6px 0;
        }
    </style>
</head>

<body>
    @php
        $meta = config('documents.form_metadata.incoming_receipt');
        $steps = app(App\Services\SignOffService::class)->stepsFor('incoming_receipt');
        $signoffs = $receipt->signoffs->keyBy('step');

        $displayDate = function ($value) {
            return $value ? strtoupper(\Carbon\Carbon::parse($value)->format('d M Y')) : 'N/A';
        };

        $displayDateTime = function ($value) {
            return $value ? strtoupper(\Carbon\Carbon::parse($value)->format('d M Y H:i')) : '-';
        };

        $displayLot = function ($value) {
            return ($value === null || trim((string) $value) === '') ? 'N/A' : $value;
        };

        $checkbox = function ($value) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '☑' : '☐';
        };

        // Radio helper: filled dot only when the current value matches the candidate.
        $radio = function ($current, $candidate) {
            return $current === $candidate ? '◉' : '○';
        };

        $storageLabels = [
            'minus35_minus15' => '-35°C to -15°C',
            'minus25_minus10' => '-25°C to -10°C',
            '2_8' => '2°C to 8°C',
            '15_25' => '15°C to 25°C',
            '15_30' => '15°C to 30°C',
        ];

        $storageValue = $receipt->storage_requirement instanceof \BackedEnum
            ? $receipt->storage_requirement->value
            : $receipt->storage_requirement;

        $incomingValue = $receipt->incoming_type instanceof \BackedEnum
            ? $receipt->incoming_type->value
            : $receipt->incoming_type;

        $statusValue = $receipt->product_status instanceof \BackedEnum
            ? $receipt->product_status->value
            : $receipt->product_status;
    @endphp

    <div class="container-fluid header-section">
        <div class="row header-container g-0">
            <div class="col-2 d-flex justify-content-center align-items-center"
                style="background-color: #0E0F97 !important; padding: 4px 8px;">
                <img src="{{ asset('assets/images/LOGO-MEDQUEST-HD.png') }}" alt=""
                    style="max-width: 100%; max-height: 54px; object-fit: contain;">
            </div>
            <div class="col-6 d-flex flex-column px-1">
                <div class="row align-items-center h-50 border-bottom border-start border-end border-black g-0">
                    <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">Form</h6>
                </div>
                <div class="row align-items-center h-50 border-start border-end border-black g-0">
                    <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">
                        INCOMING RECEIPT INSPECTION</h6>
                </div>
            </div>
            <div class="col-4 ps-2">
                <div class="row">
                    <div class="col-5">
                        <p class="mb-0" style="font-size: 11px;">Doc. No</p>
                        <p class="mb-0" style="font-size: 11px;">Effective Date</p>
                        <p class="mb-0" style="font-size: 11px;">Revision No.</p>
                        <p class="mb-0" style="font-size: 11px;">Page No.</p>
                    </div>
                    <div class="col-7">
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $meta['doc_no'] }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $meta['effective_date'] }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $meta['revision'] }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;01 of 01</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="notes">
            *) Give a (✓) mark in the appropriate square &emsp; **) Signature, initial name and date &emsp; ***)
            Please strikethrough in unnecessary words
        </div>

        {{-- Header field grid: left box = receipt identity, right box = inspection radios (mirrors base form) --}}
        {{-- Header grid: single table, left column = receipt identity (40%), right = inspection fields (60%) --}}
        <table class="table table-bordered header-grid mb-2" style="width: 100%;">
            <tr>
                <td class="left-col" style="width: 40%;">
                    <div class="field-row">
                        <div class="label">Receipt No.</div>
                        <div>:&ensp;{{ $receipt->receipt_no ?? '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Purchase Order No.</div>
                        <div>:&ensp;{{ $receipt->purchase_order_no ?: '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Receipt Date / Time</div>
                        <div>:&ensp;{{ $receipt->receipt_date ? strtoupper(\Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y')) : '-' }}
                            / {{ $receipt->receipt_time ?? '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Carrier</div>
                        <div>:&ensp;{{ $receipt->carrier->name ?? '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Shipper Name</div>
                        <div>:&ensp;{{ $receipt->supplier->name ?? '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Shipper Address</div>
                        <div>:&ensp;{{ $receipt->shipper_address ?: '-' }}</div>
                    </div>
                    <div class="field-row">
                        <div class="label">Location</div>
                        <div>:&ensp;{{ $receipt->location->location_name ?? '-' }}</div>
                    </div>
                </td>
                <td class="right-col" style="width: 60%;">
                    <div class="field-row">
                        <div class="label">Type of Incoming Products *</div>
                        <div>
                            {{ $radio($incomingValue, 'new_inventory_po') }} New Inventory (PO)&ensp;
                            {{ $radio($incomingValue, 'new_inventory_non_po') }} New Inventory (Non PO)&ensp;
                            {{ $radio($incomingValue, 'return') }} Return&ensp;
                            {{ $radio($incomingValue, 'other') }} Others: ..........
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="label">Measured by Temperature Devices? *</div>
                        <div>
                            {{ $radio(($receipt->measured_by_device ?? false) ? 'yes' : 'no', 'no') }} No<br>
                            {{ $radio(($receipt->measured_by_device ?? false) ? 'yes' : 'no', 'yes') }} Yes
                            &ensp;Temperature records see:
                            {{ $receipt->product_temp_record_ref ?: 'NA' }}
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="label">Product temperature inspection results? °C</div>
                        <div>
                            {{ $receipt->product_temp_result !== null ? number_format((float) $receipt->product_temp_result, 2) : '-' }}
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="label">Storage Requirement *</div>
                        <div>
                            @foreach ($storageLabels as $key => $label)
                                {{ $radio($storageValue, $key) }} {{ $label }}&ensp;
                            @endforeach
                        </div>
                    </div>
                    <table class="unpacking-table">
                        <tr>
                            <td class="unpacking-label">
                                Unpacking Time
                                <div class="helper">(for critical temperature products, max. 15 minutes per collie)</div>
                            </td>
                            <td class="unpacking-fields">
                                <div class="field-row">
                                    <div class="label">Start</div>
                                    <div>:&ensp;{{ $receipt->unpacking_start_at ? strtoupper(\Carbon\Carbon::parse($receipt->unpacking_start_at)->format('d M Y / H:i')) : '-' }}</div>
                                </div>
                                <div class="field-row">
                                    <div class="label">Finish</div>
                                    <div>:&ensp;{{ $receipt->unpacking_finish_at ? strtoupper(\Carbon\Carbon::parse($receipt->unpacking_finish_at)->format('d M Y / H:i')) : '-' }}</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Line items --}}
        <div class="container-fluid px-0">
            <table class="table table-bordered">
                <thead>
                    <tr class="bg-light">
                        <th rowspan="2" class="text-center" style="width: 70px;">Internal Code</th>
                        <th rowspan="2" class="text-center" style="width: 200px;">Product Description</th>
                        <th rowspan="2" class="text-center" style="width: 70px;">Lot/ Batch/ Serial No.</th>
                        <th rowspan="2" class="text-center" style="width: 80px;">Expiry Date</th>
                        <th rowspan="2" class="text-center" style="width: 45px;">Quantity</th>
                        <th rowspan="2" class="text-center" style="width: 35px;">UoM</th>
                        <th rowspan="2" class="text-center" style="width: 55px;">Physical and Packing Condition</th>
                        <th colspan="2" class="text-center" style="vertical-align: middle;">Registration Label</th>
                        <th colspan="2" class="text-center" style="vertical-align: middle;">Function Test <br> (for Medical Devices)</th>
                        <th rowspan="2" class="text-center" style="width: 55px;">Storage Location</th>
                    </tr>
                    <tr class="bg-light">
                        <th class="text-center" style="width: 60px;">No</th>
                        <th class="text-center" style="width: 60px;">QA Verification</th>
                        <th class="text-center" style="width: 60px;">Result</th>
                        <th class="text-center" style="width: 60px;">Performed by **</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipt->lines as $line)
                        <tr>
                            <td class="text-center">{{ $line->internal_code ?? '-' }}</td>
                            <td>{{ $line->product_description ?? '-' }}</td>
                            <td class="text-center">{{ $displayLot($line->lot_no) }}</td>
                            <td class="text-center">{{ $displayDate($line->expiry_date) }}</td>
                            <td class="text-center">{{ $line->quantity }}</td>
                            <td class="text-center">{{ $line->uom ?? '-' }}</td>
                            <td class="text-center">{{ $line->physical_packing_condition ?? '-' }}</td>
                            <td class="text-center">{{ $line->registration_label_no ?: 'N/A' }}</td>
                            <td class="text-center">{{ $line->registrationLabelQaBy->name ?? '-' }}</td>
                            <td class="text-center">{{ $line->function_test_result ?: 'N/A' }}</td>
                            <td class="text-center">{{ $line->function_test_by ?: 'N/A' }}</td>
                            <td class="text-center">{{ $line->room->room_name ?? $receipt->location->location_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center">No line items.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Product status strip: mirrors base form row under the line table --}}
        <div class="row mb-2 g-0">
            <div class="col-12 border border-black py-1 px-2 d-flex">
                <strong class="me-2" style="font-size: 11px;">Product Status :</strong>
                <span style="font-size: 11px;">
                    {{ $checkbox($statusValue === 'quarantine') }} Quarantine /
                    {{ $checkbox($statusValue === 'released') }} Released /
                    {{ $checkbox($statusValue === 'return') }} Return
                </span>
            </div>
        </div>

        {{-- Footer signature grid: one column per configured sign-off step --}}
        <div class="container-fluid px-0 footer-section mt-3">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        @foreach ($steps as $stepKey => $stepConfig)
                            <td class="text-center" style="width: {{ floor(100 / count($steps)) }}%; height: 70px; font-size: 10px;">
                                <strong>{{ $stepConfig['label'] }}</strong>**<br><br><br>
                                {{ $signoffs->get($stepKey)?->signer_name ?? '' }}<br>
                                <span style="font-size: 8px;">({{ $stepConfig['role'] }})</span>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row mt-2 g-0">
            <div class="col-12 border border-black py-1 px-2">
                <p class="mb-0" style="font-size: 11px;">
                    <strong>Remark :</strong> {{ $receipt->remark ?: '' }}
                </p>
            </div>
        </div>
    </div>
</body>

</html>
