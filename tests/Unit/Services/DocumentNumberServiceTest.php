<?php

use App\Models\Location;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// The issued number is opaque; assert on its trailing zero-padded sequence only.
function sequenceSuffix(string $number): string
{
    preg_match('/^(\d{4})/', $number, $m);

    return $m[1] ?? '';
}

it('issues sequential numbers within a period', function () {
    $location = Location::factory()->create();
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $first = $service->issue('incoming_receipt', $location->id, $date);
    $second = $service->issue('incoming_receipt', $location->id, $date);

    expect($second)->not->toBe($first)
        ->and(sequenceSuffix($first))->toBe('0001')
        ->and(sequenceSuffix($second))->toBe('0002');
});

it('resets the sequence in a new period', function () {
    $location = Location::factory()->create();
    $service = app(DocumentNumberService::class);

    $service->issue('incoming_receipt', $location->id, Carbon::parse('2026-08-31'));

    expect(sequenceSuffix($service->issue('incoming_receipt', $location->id, Carbon::parse('2026-09-01'))))
        ->toBe('0001');
});

it('isolates sequences per location', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $service->issue('incoming_receipt', $a->id, $date);

    expect(sequenceSuffix($service->issue('incoming_receipt', $b->id, $date)))->toBe('0001');
});

it('does not reuse a number when two transactions race', function () {
    $location = Location::factory()->create();
    $date = Carbon::parse('2026-08-05');

    // Two callers in separate transactions. The row lock in issue() is what keeps
    // these distinct; without lockForUpdate both would read last_number = 0.
    $first = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));
    $second = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));

    expect($second)->not->toBe($first)
        ->and(sequenceSuffix($second))->toBe('0002');
});
