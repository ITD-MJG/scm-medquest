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
        $steps = app(App\Services\SignOffService::class)->stepsFor('incoming_receipt_grifols');
        $signoffs = $receipt->signoffs->keyBy('step');

        // Flow-driven steps keep the print grid's fixed slot positions; slots are
        // identified by their original config key order (legacy names kept for layout).
        $slotKeys = ['received_by', 'inspected_by', 'verified_by', 'checked_by_scm', 'disposition', 'sap_entry_by', 'verified_by_whm'];
        $row1Slots = array_slice($slotKeys, 0, 4);
        $row2Slots = array_slice($slotKeys, 4);
        $stepKeys = array_keys($steps); // flow sequences in order
        $slotByStepKey = array_combine($stepKeys, array_pad([], count($stepKeys), null));
        foreach ($stepKeys as $i => $k) { $slotByStepKey[$k] = $slotKeys[$i] ?? $k; }
        $signoffs = $receipt->signoffs->keyBy(fn ($s) => isset($slotByStepKey[$s->step]) ? $slotByStepKey[$s->step] : $s->step);
        $displayDate = function ($value) {
            return $value ? strtoupper(\Carbon\Carbon::parse($value)->format('d M Y')) : 'N/A';
        };


        // Form rev 06 renders checkbox groups instead of free-text enum values.
        $check = fn (bool $on) => $on ? '&#9745;' : '&#9744;';
        $incomingTypeChecks = [
            'New Inventory (PO)' => ($receipt->incoming_type?->value ?? $receipt->incoming_type) === 'new_inventory_po',
            'New Inventory (Non PO)' => ($receipt->incoming_type?->value ?? $receipt->incoming_type) === 'new_inventory_non_po',
            'Return' => ($receipt->incoming_type?->value ?? $receipt->incoming_type) === 'return',
            'Others' => ($receipt->incoming_type?->value ?? $receipt->incoming_type) === 'other',
        ];
        $storageChecks = [
            'Ambient (15°C to 30°C)' => ($receipt->storage_requirement?->value ?? $receipt->storage_requirement) === '15_30',
            'Refrigerated (2°C to 8°C)' => ($receipt->storage_requirement?->value ?? $receipt->storage_requirement) === '2_8',
            'Frozen (-35°C to -15°C)' => in_array(($receipt->storage_requirement?->value ?? $receipt->storage_requirement), ['minus35_minus15', 'minus25_minus10']),
            'Controlled Room' => ($receipt->storage_requirement?->value ?? $receipt->storage_requirement) === null,
        ];
        $displayLot = function ($value) {
            return ($value === null || trim((string) $value) === '') ? 'N/A' : $value;
        };

        // Nullable booleans on the paper are tri-state: an unanswered box is not "No".
        $triState = fn (?bool $value) => $value === null ? '-' : ($value ? 'Y' : 'N');
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
                        <p class="mb-0" style="font-size: 11px;">Carrier</p>
                        <p class="mb-0" style="font-size: 11px;">Receipt Date / Time</p>
                        <p class="mb-0" style="font-size: 11px;">Shipper Name and Address</p>
                        <p class="mb-0" style="font-size: 11px;">Incoming Type</p>
                    </div>
                    <div class="col-8">
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->receipt_no ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->purchase_order_no ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $receipt->carrier->name ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">
                            :&ensp;{{ $receipt->receipt_date ? strtoupper(\Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y H:i')) : '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">
                            :&ensp;{{ collect([$receipt->supplier?->name, $receipt->shipper_address])->filter()->implode(', ') ?: '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;@foreach($incomingTypeChecks as $label => $on){!! $check($on) !!} {{ $label }} &ensp;@endforeach</p>
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
                        <p class="mb-0" style="font-size: 11px;">Registration Label attached (Y/N)</p>
                        <p class="mb-0" style="font-size: 11px;">Location</p>
                        <p class="mb-0" style="font-size: 11px;">Remark</p>
                    </div>
                    <div class="col-8">
                        <p class="mb-0" style="font-size: 11px;">:&ensp;@foreach($storageChecks as $label => $on){!! $check($on) !!} {{ $label }} &ensp;@endforeach</p>
                        <p class="mb-0" style="font-size: 11px;">
                            :&ensp;{{ $receipt->unpacking_start_at ?? '-' }} / {{ $receipt->unpacking_finish_at ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{!! $check((bool) $receipt->measured_by_device) !!} Temperature measured by Device (Y/N) &ensp; Product Temperature Result: {{ $receipt->product_temp_result ?? '-' }}</p>
                        <p class="mb-0" style="font-size: 11px;">:&ensp;{{ $triState($receipt->registration_label_attached) }}</p>
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
                    <th class="text-center" style="width: 40px;">Unit per Case</th>
                    <th class="text-center" style="width: 35px;">Qty</th>
                    <th class="text-center" style="width: 35px;">UoM</th>
                    <th class="text-center" style="width: 35px;">Labeled Correctly (Y/N)</th>
                    <th class="text-center" style="width: 40px;">ISN or Packing List (Y/N)</th>
                    <th class="text-center">Pallet/ID</th>
                    <th class="text-center">Physical Condition</th>
                    <th class="text-center">Storage Conditions</th>
                    <th class="text-center">Remark</th>
                    <th class="text-center">Packaging Observation</th>
                    <th class="text-center">Registration Label No.</th>
                    <th class="text-center">Labeled by</th>
                    <th class="text-center">Storage Location</th>
                    <th class="text-center">Physical Movement by</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipt->lines as $line)
                    <tr>
                        <td class="text-center">{{ $line->line_no ?? $loop->iteration }}</td>
                        <td>{{ $line->batch->product->principal->name ?? '-' }}</td>
                        <td>{{ $line->internal_code ?? '-' }}</td>
                        <td>{{ $line->part_no ?? '-' }}</td>
                        <td>{{ $displayLot($line->lot_no) }}</td>
                        <td>{{ $line->serial_no ?? '-' }}</td>
                        <td class="text-center">{{ $displayDate($line->expiry_date) }}</td>
                        <td class="text-center">{{ $line->no_of_cases ?? '-' }}</td>
                        <td class="text-center">{{ $line->unit_per_case ?? '-' }}</td>
                        <td class="text-center">{{ $line->quantity }}</td>
                        <td class="text-center">{{ $line->uom ?? '-' }}</td>
                        <td class="text-center">{{ $triState($line->labeled_correctly) }}</td>
                        <td class="text-center">{{ $triState($line->isn_or_packing_list) }}</td>
                        <td>{{ $line->pallet_id ?? '-' }}</td>
                        <td>{{ $line->physical_packing_condition ?? '-' }}</td>
                        <td>{{ $receipt->storage_requirement?->label() ?? '-' }}</td>
                        {{-- The paper has a per-line Remark cell but no column backs it;
                             the receipt-level remark prints in the header grid. --}}
                        <td>-</td>
                        <td>{{ $line->packaging_observation ?? '-' }}</td>
                        <td>{{ $line->registration_label_no ?? '-' }}</td>
                        <td>{{ $line->labeledBy?->name ?? '-' }}</td>
                        <td>{{ $line->room?->room_name ?? '-' }}</td>
                        <td>{{ $line->physical_movement_by ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="22" class="text-center">No line items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p class="mb-0" style="font-size: 10px;">
            Abbreviation of Packaging Product:&ensp;ea = each,&ensp;unit = unit,&ensp;kit = kit
        </p>
        <p class="mb-0" style="font-size: 10px;">
            Receipt Total (item of products per sheet):&ensp;{{ $receipt->receipt_total }}
        </p>
    </div>

    {{-- Footer signature grid: row 1 then row 2 --}}
    <div class="container-fluid footer-section mt-3">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    @foreach ($row1Slots as $slotKey) @php $stepKey = array_search($slotKey, $slotByStepKey) ?: $slotKey; @endphp
                        <td style="width: 25%; height: 70px; font-size: 10px;">
                            <strong>{{ $steps[$stepKey]['label'] }}</strong><br>
                            ({{ $steps[$stepKey]['role'] }})<br><br>
                            {{ $signoffs->get($stepKey)?->signer_name ?? '' }}
                        </td>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($row2Slots as $slotKey) @php $stepKey = array_search($slotKey, $slotByStepKey) ?: $slotKey; @endphp
                        <td style="width: {{ $slotKey === 'disposition' ? '34%' : '33%' }}; height: 70px; font-size: 10px;">
                            <strong>{{ $steps[$stepKey]['label'] }}</strong><br>
                            ({{ $steps[$stepKey]['role'] }})<br>
                            @if ($slotKey === 'disposition')
                                {{ $receipt->disposition?->label() ?? 'N/A' }}<br>
                                Duration of transportation:
                                {{ $receipt->duration_of_transportation_hours !== null ? $receipt->duration_of_transportation_hours . ' hour' : 'N/A' }}<br>
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
