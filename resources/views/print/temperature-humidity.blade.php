<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temperature Humidity Export PDF</title>
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
        .table th, .table td {
            padding: 0.15rem;
            vertical-align: middle;
            border: 1px solid black !important;
            word-wrap: break-word;
        }
        thead {
            display: table-header-group;
        }
        .room-section {
            page-break-after: always;
        }
        .room-section:last-child {
            page-break-after: auto;
        }
        .header-container {
            border: 1px solid black;
            margin-bottom: 5px;
        }
        .border-black {
            border: 1px solid black !important;
        }
        .text-center {
            text-align: center;
        }
        .fw-bold {
            font-weight: bold;
        }
        .time-column, .temp-column { width: 32px; }
        .rh-column { width: 28px; }
        .pic-column { width: 35px; }
    </style>
</head>

<body>
    @php
// Ensure we have grouped records
$renderGroups = isset($groupedTempHumidity) && $groupedTempHumidity->count() > 0
    ? $groupedTempHumidity
    : (isset($tempHumidity) ? $tempHumidity->groupBy('room_id') : collect());
    @endphp

    @forelse($renderGroups as $roomId => $roomRecords)
        <div class="room-section">
            @php
                $firstRecord = $roomRecords->first();
            @endphp
            {{-- Header Section --}}
            <div class="container-fluid mb-2">
                <div class="row header-container">
                    <div class="col-2 d-flex justify-content-center align-items-center px-0" style="background-color: #0E0F97 !important;">
                        <img src="{{ asset('assets/images/LOGO-MEDQUEST-HD.png') }}" alt="" style="width: 100%;">
                    </div>
                    <div class="col-7 d-flex flex-column">
                        <div class="row align-items-center h-50 border-bottom border-start border-end border-black">
                            <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">Form</h6>
                        </div>
                        <div class="row align-items-center h-50 border-start border-end border-black">
                            <h6 class="fw-bolder text-uppercase text-center mb-0" style="width: 100%;">Temperature And Humidity Monitoring</h6>
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
                                <p class="mb-0" style="font-size: 11px;">:&ensp;MJG-FOR-SCM.02-002-01</p>
                                <p class="mb-0" style="font-size: 11px;">:&ensp;12 JUN 2023</p>
                                <p class="mb-0" style="font-size: 11px;">:&ensp;10</p>
                                <p class="mb-0" style="font-size: 11px;">:&ensp;@pageNumber of @totalPages</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-1" id="temperature-range">
                    <div class="col-7 pe-0 border border-black border-end-0 py-1">
                        <p class="mb-0 fw-bold ps-1" style="font-size: 12px;">Acceptable Temperature Range:</p>
                        <div class="row">
                            <div class="col-6">
                                <p class="mb-0" style="font-size: 11px;">a.&ensp;Ambient storage temp (<i>suhu kamar</i>)</p>
                                <p class="mb-0" style="font-size: 11px;">b.&ensp;Cold storage temp (<i>suhu dingin</i>)</p>
                                <p class="mb-0" style="font-size: 11px;">c.&ensp;Frozen storage temp (<i>suhu beku</i>)</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-0" style="font-size: 11px;">:&emsp;15°C to 30°C or 15°C to 25°C</p>
                                <p class="mb-0" style="font-size: 11px;">:&emsp;2°C to 8°C</p>
                                <p class="mb-0" style="font-size: 11px;">:&emsp;-35°C to -15°C or -25°C to -10°C</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-5 pe-0 border border-black py-1">
                        <p class="mb-0 fw-bold ps-1" style="font-size: 12px;">Acceptable Humidity Range:</p>
                        <div class="row">
                            <div class="col-4">
                                <p class="mb-0" style="font-size: 11px;">d.&ensp;Humidity (RH)</p>
                            </div>
                            <div class="col-8">
                                <p class="mb-0" style="font-size: 11px;">:&emsp;30% to 90%</p>
                            </div>
                        </div>
                        <p class="mb-0" style="font-size: 10px;">Note:&emsp;<b>Humidity for Freezer and Refrigerator are not monitored.</b> so it
                            can be filled with "N/A" (Not Applicable)</p>
                    </div>
                </div>

                <div class="row mb-1" id="detail">
                    <div class="col-7 pe-0 border border-black border-end-0 py-1">
                        <div class="row">
                            <div class="col-3">
                                <p class="mb-0" style="font-size: 12px;">Period</p>
                                <p class="mb-0" style="font-size: 12px;">Location / Serial No.</p>
                                <p class="mb-0" style="font-size: 12px;">Observed Temp</p>
                            </div>
                            <div class="col-9">
                                <p class="mb-0 text-uppercase" style="font-size: 12px;">:&emsp;{{ \Carbon\Carbon::parse($firstRecord->period)->format('M Y') }}</p>
                                <p class="mb-0" style="font-size: 12px;">:&emsp;{{ ($firstRecord->room->room_name ?? '-') . ' ' . ($firstRecord->location->location_name ?? '-') }} / {{ $firstRecord->serialNumber->serial_number ?? '-' }}</p>
                                <p class="mb-0" style="font-size: 12px;">:&emsp;{{ $firstRecord->roomTemperature->temperature_start ?? '-' }}°C to {{ $firstRecord->roomTemperature->temperature_end ?? '-' }}°C</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-5 pe-0 border border-black py-1">
                        <p class="mb-0" style="font-size: 11px;">*) Initial name and date (<i>Inisial nama dan tanggal</i>) or Name for Security (<i>Nama untuk Sekuriti</i>)</p>
                    </div>
                </div>
            </div>

            {{-- Table Section --}}
            <div class="container-fluid px-0">
                <table class="table table-bordered">
                    <thead>
                        <tr class="bg-light">
                            <th rowspan="2" class="text-center align-middle" style="width: 30px">Date</th>
                            @foreach(['0200', '0500', '0800', '1100', '1400', '1700', '2000', '2300'] as $time)
                                <th colspan="4" class="text-center">{{ $time }}</th>
                            @endforeach
                            <th rowspan="2" class="text-center align-middle" style="width: 50px">Reviewed By * (SCM Manager)</th>
                            <th rowspan="2" class="text-center align-middle" style="width: 50px">Acknowledged By * (QA Manager)</th>
                        </tr>
                        <tr class="bg-light">
                            @for($i = 0; $i < 8; $i++)
                                <th class="text-center time-column">Time</th>
                                <th class="text-center temp-column">Temp<br>(°C)</th>
                                <th class="text-center rh-column">RH<br>(%)</th>
                                <th class="text-center pic-column">PIC *</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $periodDate = \Carbon\Carbon::parse($firstRecord->period);
                            $daysInMonth = $periodDate->daysInMonth;

                            // Build per-day/time-slot deviation lookup once (avoids N+1 DB queries in the loop)
                            $deviationTimeSlots = [];
                            foreach ($roomRecords as $rec) {
                                $recDay = \Carbon\Carbon::parse($rec->date)->day;
                                if ($rec->relationLoaded('temperatureDeviations')) {
                                    foreach ($rec->temperatureDeviations as $dev) {
                                        $hourPrefix = substr(\Carbon\Carbon::parse($dev->time)->format('Hi'), 0, 2) . '00';
                                        $deviationTimeSlots[$recDay][$hourPrefix] = true;
                                    }
                                }
                            }
                        @endphp
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $record = $roomRecords->first(function($r) use ($day) {
                                    return \Carbon\Carbon::parse($r->date)->day == $day;
                                });
                            @endphp
                            <tr>
                                <td class="text-center">{{ sprintf('%02d', $day) }}</td>
                                @foreach(['0200', '0500', '0800', '1100', '1400', '1700', '2000', '2300'] as $t)
                                    @php 
                                        $timeField = "time_$t";
                                        $tempField = "temp_$t";
                                        $rhField = "rh_$t";
                                        $picField = "pic_$t";
                                        $tempVal = $record ? $record->$tempField : null;
                                        $rhVal = $record ? $record->$rhField : null;
                                        $isOutOfRange = $record && $tempVal !== null && ($tempVal < $record->roomTemperature->temperature_start || $tempVal > $record->roomTemperature->temperature_end);
                                        $hasDeviation = $record && $tempVal !== null && isset($deviationTimeSlots[$day][$t]);
                                        $style = ($isOutOfRange || $hasDeviation) ? 'color: red; font-weight: bold;' : '';
                                    @endphp
                                    <td class="text-center">{{ ($record && $record->$timeField) ? \Carbon\Carbon::parse($record->$timeField)->format('Hi') : '-' }}</td>
                                    <td class="text-center" style="{{ $style }}">{{ $tempVal ?? '-' }}</td>
                                    <td class="text-center">{{ $rhVal ?? '-' }}</td>
                                    <td class="text-center" style="font-size: 8px;">{{ $record ? $record->formatPicSignature($picField) : '-' }}</td>
                                @endforeach
                                <td class="text-center">{{ $record->reviewed_by ?? '-' }}</td>
                                <td class="text-center">{{ $record->acknowledged_by ?? '-' }}</td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="container mt-5">
            <div class="alert alert-warning text-center">
                No temperature and humidity records found for the selected criteria.
            </div>
        </div>
    @endforelse
</body>
</html>