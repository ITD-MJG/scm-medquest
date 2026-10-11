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
            font-size: 10px;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table th,
        .table td {
            padding: 0.3rem;
            vertical-align: middle;
            border: 1px solid black !important;
            word-wrap: break-word;
        }

        .header-container {
            border: 1px solid black;
            margin-bottom: 8px;
            min-height: 60px;
        }

        .border-black {
            border: 1px solid black !important;
        }
    </style>
</head>

<body>
    @php
        $meta = config('documents.form_metadata.incoming_receipt_grifols');
        $steps = config('documents.signoff_steps.incoming_receipt_grifols');
        $signoffs = $receipt->signoffs->keyBy('step');

        $row1Steps = ['received_by', 'inspected_by', 'verified_by', 'checked_by_scm'];
        $row2Steps = ['disposition', 'sap_entry_by', 'verified_by_whm'];

        $displayDate = function ($value) {
            return $value ? strtoupper(\Carbon\Carbon::parse($value)->format('d M Y')) : 'N/A';
        };

        $displayLot = function ($value) {
            return ($value === null || trim((string) $value) === '') ? 'N/A' : $value;
        };
    @endphp

    <div class="container-fluid header-section">
        <div class="row header-container">
            <div class="col-2 d-flex justify-content-center align-items-center px-0"
                style="background-color: #0E0F97 !important;">
                <img src="{{ asset('assets/images/LOGO-MEDQUEST-HD.png') }}" alt=""
                    style="width: 100%; height: auto; max-height: 60px; object-fit: contain;">
            </div>
            <div class="col-7 d-flex flex-column">
                <div class="row align-items-center h-50 border-bottom border-start border-end border-black">
                    <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">Form</h6>
                </div>
                <div class="row align-items-center h-50 border-start border-end border-black">
                    <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">
                        INCOMING RECEIPT INSPECTION FOR GRIFOLS DIAGNOSTICS PRODUCT</h6>
                </div>
            </div>
            <div class="col-3 ps-1">
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
                        <p class="mb-0" style="font-size: 11px;">:&ensp;01 of 02</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Header field grid --}}
        <div class="row mb-2">
            <div class="col-12 border border-black py-1">
                <div class="row">
                    <div class="col-4">
                        <p class="mb-0" style="font-size: 11px;">Receipt No.</p>
                        <p class="mb-0" style="font-size: 11px;">Purchase Order No.</p>
                        <p class="mb-0" style="font-size: 11px;">Receipt Date / Time</p>
                        <p class="mb-0" style="font-size: 11px;">Shipper Name and Address</p>
                        <p class="mb-0" style="font-size: 11px;">Incoming Type</p>
                    </div>
                    <div class="col-8">
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->receipt_no ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->purchase_order_no ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">
                            :&ensp;{{ $receipt->receipt_date ? strtoupper(\Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y H:i')) : '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->supplier->name ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->incoming_type?->value ?? $receipt->incoming_type ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-12 border border-black py-1">
                <div class="row">
                    <div class="col-4">
                        <p class="mb-0" style="font-size: 11px;">Storage Requirement</p>
                        <p class="mb-0" style="font-size: 11px;">Unpacking Start / Finish Time</p>
                        <p class="mb-0" style="font-size: 11px;">Product Temperature Result</p>
                        <p class="mb-0" style="font-size: 11px;">Package Conditions</p>
                        <p class="mb-0" style="font-size: 11px;">Location</p>
                        <p class="mb-0" style="font-size: 11px;">Remark</p>
                    </div>
                    <div class="col-8">
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->storage_requirement ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">
                            :&ensp;{{ $receipt->unpacking_start_at ?? '-' }} / {{ $receipt->unpacking_finish_at ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->product_temp_result ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->product_status?->value ?? $receipt->product_status ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->location->location_name ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->remark ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="container-fluid px-0">
        <table class="table table-bordered">
            <thead>
                <tr class="bg-light">
                    <th class="text-center" style="width: 25px;">No</th>
                    <th class="text-center">Principal</th>
                    <th class="text-center">Code</th>
                    <th class="text-center">Part No</th>
                    <th class="text-center">Lot</th>
                    <th class="text-center">Serial No</th>
                    <th class="text-center" style="width: 70px;">Expiry Date</th>
                    <th class="text-center" style="width: 40px;">No. of Cases</th>
                    <th class="text-center" style="width: 35px;">Qty</th>
                    <th class="text-center" style="width: 35px;">UoM</th>
                    <th class="text-center">Abbreviation of Packaging Product</th>
                    <th class="text-center">Packing Condition</th>
                    <th class="text-center">Storage Conditions</th>
                    <th class="text-center">Remark</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipt->lines as $line)
                    <tr>
                        <td class="text-center">{{ $line->line_no ?? $loop->iteration }}</td>
                        <td>{{ $line->product->principal->name ?? '-' }}</td>
                        <td>{{ $line->internal_code ?? '-' }}</td>
                        <td>{{ $line->part_no ?? '&ensp;-' }}</td>
                        <td>{{ $displayLot($line->lot_no) }}</td>
                        <td>{{ $line->serial_no ?? '&ensp;-' }}</td>
                        <td class="text-center">{{ $displayDate($line->expiry_date) }}</td>
                        <td class="text-center">{{ $line->no_of_cases ?? '&ensp;-' }}</td>
                        <td class="text-center">{{ $line->quantity }}</td>
                        <td class="text-center">{{ $line->uom ?? '-' }}</td>
                        <td>{{ $line->packaging_observation ?? '&ensp;-' }}</td>
                        <td>{{ $line->physical_packing_condition ?? '-' }}</td>
                        <td>{{ $receipt->storage_requirement ?? '-' }}</td>
                        <td>{{ $receipt->remark ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="text-center">No line items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mb-0" style="font-size: 10px;">
            Abbreviation of Packaging Product:&ensp;ea = each,&ensp;unit = unit,&ensp;kit = kit
        </p>
    </div>

    {{-- Footer signature grid: row 1 then row 2 --}}
    <div class="container-fluid footer-section mt-3">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    @foreach ($row1Steps as $stepKey)
                        <td style="width: 25%; height: 70px; font-size: 10px;">
                            <strong>{{ $steps[$stepKey]['label'] }}</strong><br>
                            ({{ $steps[$stepKey]['role'] }})<br><br>
                            {{ $signoffs->get($stepKey)?->signer_name ?? '' }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($row2Steps as $stepKey)
                        <td style="width: {{ $stepKey === 'disposition' ? '34%' : '33%' }}; height: 70px; font-size: 10px;">
                            <strong>{{ $steps[$stepKey]['label'] }}</strong><br>
                            ({{ $steps[$stepKey]['role'] }})<br>
                            @if ($stepKey === 'disposition')
                                {{ $receipt->disposition ?? 'N/A' }}<br><br>
                            @else
                                <br>
                            @endif
                            {{ $signoffs->get($stepKey)?->signer_name ?? '' }}
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
