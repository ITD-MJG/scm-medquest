<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header Template for Temperature Humidity Export PDF</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-1 border border-black" id="header">
            <div class="col-2 px-0">
                <img src="{{ asset('assets/images/LOGO-MEDQUEST-HD.png') }}" alt="" style="width: 100%;">
            </div>
            <div class="col-7 d-flex flex-column border-black">
                <div class="row align-items-center h-50 border-bottom border-start border-end border-black">
                    <h5 class="fw-bolder text-uppercase text-center mb-0">Form</h5>
                </div>
                <div class="row align-items-center h-50 border-start border-end border-black">
                    <h5 class="fw-bolder text-uppercase text-center mb-0">Temperature & Humidity</h5>
                </div>
            </div>
            <div class="col-3 ps-1">
                <div class="row">
                    <div class="col-3">
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">Doc. No</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">Effective Date</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">Revision No.</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">Page No.</p>
                    </div>
                    <div class="col-9">
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">:&ensp;MJG-FOR-SCM.02.002-01</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">:&ensp;12 JUN 2023</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">:&ensp;09</p>
                        <p class="mb-0" style="font-size: 13px; padding-left: 2%;">:&ensp;<span class="pageNumber"></span> of <span class="totalPages"></span></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-1" id="temperature-range">
            <div class="col-7 pe-0 border border-black">
                <p class="mb-0 fw-bold ps-1" style="font-size: 16px; padding-left: 2%;">Acceptable Temperature Range:</p>
                <div class="row">
                    <div class="col-5">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">a.&ensp;Ambient storage temperature (<i>suhu kamar</i>)</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">b.&ensp;Cold storage temperature (<i>suhu dingin</i>)</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">b.&ensp;Frozen storage temperature (<i>suhu beku</i>)</p>
                    </div>
                    <div class="col-7">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;15°C to 30°C or 15°C to 25°C</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;2°C to 8°C</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;-35°C to -15°C or -25°C to -10°C</p>
                    </div>
                </div>
            </div>
            <div class="col-5 pe-0 border border-black">
                <p class="mb-0 fw-bold ps-1" style="font-size: 16px; padding-left: 2%;">Acceptable Humidity Range:</p>
                <div class="row">
                    <div class="col-2">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">d.&ensp;Humidity (RH)</p>
                    </div>
                    <div class="col-10">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;30% to 90%</p>
                    </div>
                </div>
                <p class="mb-0" style="font-size: 16px;">Note:&emsp;<b>Humidity for Freeze and Refrigerator are not monitored.</b> so it can be filled with "N/A" (Not Applicable)</p>
            </div>
        </div>
        <div class="row" id="detail">
            <div class="col-7 pe-0 border border-black">
                <div class="row">
                    <div class="col-3">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">Period</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">Location / Serial No.</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">Observed Temp</p>
                    </div>
                    <div class="col-9">
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;</p>
                        <p class="mb-0" style="font-size: 16px; padding-left: 1% !important;">:&emsp;</p>
                    </div>
                </div>
            </div>
            <div class="col-5 pe-0 border border-black">
                <p class="mb-0" style="font-size: 11px;">***) Initial name and date (<i>Inisial nama dan tanggal</i>)</p>
            </div>
        </div>
    </div>
</body>
</html>