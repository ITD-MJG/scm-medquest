<?php

?><?php

use App\Http\Controllers\Auth\LogoutController;
use App\Mail\TemperatureHumidityBulkNotification;
use App\Models\IncomingReceipt;
use App\Models\TemperatureDeviation; // Add this line
use App\Models\TemperatureHumidity;
use App\Traits\HasLocationBasedAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;

// Redirect root to dashboard
Route::get('/', fn () => redirect(filament()->getHomeUrl()));

// Email preview routes for development
Route::get('/email-preview/review', function () {
    $mailable = new TemperatureHumidityBulkNotification(5, 'review');

    return $mailable;
});

Route::get('/email-preview/acknowledgment', function () {
    $mailable = new TemperatureHumidityBulkNotification(3, 'acknowledgment');

    return $mailable;
});

Route::get('/temperature-humidities/bulk-export', function () {
    $ids = session()->get('export_ids', []);
    $tempHumidity = TemperatureHumidity::whereIn('id', $ids)
        ->with(['location', 'room', 'serialNumber', 'roomTemperature', 'temperatureDeviations'])
        ->get();

    if ($tempHumidity->isEmpty()) {
        return redirect()->back()->with('warning', 'No records selected for export.');
    }

    // Group records by room
    $groupedTempHumidity = $tempHumidity->groupBy('room_id');

    // Generate filename logic
    $roomCount = $tempHumidity->pluck('room_id')->unique()->count();
    $firstRecord = $tempHumidity->first();
    $locationName = strtoupper(Str::slug($firstRecord->location->location_name, '_'));
    $period = strtoupper(Carbon::parse($firstRecord->period)->format('MY'));

    if ($roomCount === 1) {
        $roomName = strtoupper(Str::slug($firstRecord->room->room_name, '_'));
        $filename = "TemperatureHumidity_{$locationName}_{$roomName}_{$period}.pdf";
    } else {
        $filename = "TemperatureHumidity_{$locationName}_{$period}.pdf";
    }

    $html = view('print.temperature-humidity', [
        'tempHumidity' => $tempHumidity,
        'groupedTempHumidity' => $groupedTempHumidity,
    ]);
    $pdf = Browsershot::html($html)
        ->format('A4')
        ->margins(3, 3, 3, 3)
        ->landscape()
        ->showBackground()
        ->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
})->name('temperature-humidities.bulk-export');

Route::get('/temperature-deviations/bulk-export', function () {
    $ids = session()->get('export_ids', []);
    $temperatureDeviations = TemperatureDeviation::whereIn('id', $ids)
        ->with(['location', 'room', 'serialNumber', 'roomTemperature'])
        ->get();

    if ($temperatureDeviations->isEmpty()) {
        return redirect()->back()->with('warning', 'No records selected for export.');
    }

    // Group records by room
    $groupedDeviations = $temperatureDeviations->groupBy('room_id');

    // Generate a single PDF with all rooms
    $roomCount = $temperatureDeviations->pluck('room_id')->unique()->count();
    $firstDeviation = $temperatureDeviations->first();
    $locationName = strtoupper(Str::slug($firstDeviation->location->location_name, '_'));
    $period = strtoupper(Carbon::parse($firstDeviation->date)->format('MY'));

    if ($roomCount === 1) {
        $roomName = strtoupper(Str::slug($firstDeviation->room->room_name, '_'));
        $filename = "TemperatureDeviations_{$locationName}_{$roomName}_{$period}.pdf";
    } else {
        $filename = "TemperatureDeviations_{$locationName}_{$period}.pdf";
    }

    $html = view('print.temperature-deviation', [
        'temperatureDeviations' => $temperatureDeviations,
        'groupedDeviations' => $groupedDeviations,
    ]);
    $pdf = Browsershot::html($html)
        ->format('A4')
        ->margins(3, 3, 3, 3)
        ->landscape()
        ->showBackground()
        ->pdf();

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
})->name('temperature-deviations.bulk-export-pdf');

Route::get('/incoming-receipts/{record}/print', function (IncomingReceipt $record) {
    abort_unless(HasLocationBasedAccess::canAccessLocation((int) $record->location_id), 403);

    $html = view('print.incoming-receipt', [
        'receipt' => $record->load('lines.batch.product', 'signoffs', 'location', 'supplier', 'carrier'),
    ]);

    $pdf = Browsershot::html($html)->format('A4')->margins(3, 3, 3, 3)->landscape()->showBackground()->pdf();

    $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $record->receipt_no);

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="IncomingReceipt_'.$filename.'.pdf"');
})->name('incoming-receipts.print')->middleware(['auth']);

Route::get('/incoming-receipts-grifols/{record}/print', function (IncomingReceipt $record) {
    abort_unless(HasLocationBasedAccess::canAccessLocation((int) $record->location_id), 403);

    $html = view('print.incoming-grifols', [
        'receipt' => $record->load('lines.product.principal', 'lines.batch.product', 'signoffs', 'location', 'supplier', 'carrier'),
    ]);

    $pdf = Browsershot::html($html)->format('A4')->margins(3, 3, 3, 3)->landscape()->showBackground()->pdf();

    $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $record->receipt_no);

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="IncomingReceiptGrifols_'.$filename.'.pdf"');
})->name('incoming-receipts-grifols.print')->middleware(['auth']);

// Custom logout routes
Route::post('/logout/403', [LogoutController::class, 'logoutFrom403'])->name('logout.403');

Route::get('/pdf/temp-humidity', function () {
    return view('print.header.temperature-humidity');
});
Route::get('/pdf/temp-deviation', function () {
    return view('print.header.temperature-deviation');
});
Route::get('/pdf/temp-humidity-body', function () {
    return view('print.temperature-humidity');
});
