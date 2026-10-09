<?php

use App\Models\Location;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// The issued number is opaque; assert on its leading zero-padded sequence only.
function sequencePrefix(string $number): string
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
        ->and(sequencePrefix($first))->toBe('0001')
        ->and(sequencePrefix($second))->toBe('0002');
});

it('resets the sequence in a new period', function () {
    $location = Location::factory()->create();
    $service = app(DocumentNumberService::class);

    $service->issue('incoming_receipt', $location->id, Carbon::parse('2026-08-31'));

    expect(sequencePrefix($service->issue('incoming_receipt', $location->id, Carbon::parse('2026-09-01'))))
        ->toBe('0001');
});

it('isolates sequences per location', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $service->issue('incoming_receipt', $a->id, $date);

    expect(sequencePrefix($service->issue('incoming_receipt', $b->id, $date)))->toBe('0001');
});

it('increments the persisted sequence across separate transactions', function () {
    $location = Location::factory()->create();
    $date = Carbon::parse('2026-08-05');

    // Sequential transactions in one process: this guards the increment/persist path,
    // it does not exercise lockForUpdate() contention (see the null-location test below
    // for the uniqueness guarantee that the generated column enforces).
    $first = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));
    $second = DB::transaction(fn () => app(DocumentNumberService::class)->issue('incoming_receipt', $location->id, $date));

    expect($second)->not->toBe($first)
        ->and(sequencePrefix($second))->toBe('0002');
});

it('increments the sequence for a null location instead of duplicating', function () {
    $service = app(DocumentNumberService::class);
    $date = Carbon::parse('2026-08-05');

    $first = $service->issue('incoming_receipt', null, $date);
    $second = $service->issue('incoming_receipt', null, $date);

    expect($second)->not->toBe($first)
        ->and(sequencePrefix($first))->toBe('0001')
        ->and(sequencePrefix($second))->toBe('0002')
        ->and(DB::table('document_sequences')->whereNull('location_id')->count())->toBe(1);
});

it('rejects a second null-location sequence row for the same period', function () {
    // MySQL/MariaDB treat NULL as distinct in a unique index, so without the
    // generated `location_key` column this insert succeeds and two first-time
    // callers could each claim sequence 0001 for the null-location path (spec §6).
    $row = ['document_type' => 'incoming_receipt', 'location_id' => null, 'period' => '2026-08', 'last_number' => 0];

    DB::table('document_sequences')->insert($row);

    expect(fn () => DB::table('document_sequences')->insert($row))
        ->toThrow(QueryException::class);

    expect(DB::table('document_sequences')->whereNull('location_id')->count())->toBe(1);
});

it('allows distinct non-null locations in the same period', function () {
    $a = Location::factory()->create();
    $b = Location::factory()->create();

    DB::table('document_sequences')->insert([
        ['document_type' => 'incoming_receipt', 'location_id' => $a->id, 'period' => '2026-08', 'last_number' => 1],
        ['document_type' => 'incoming_receipt', 'location_id' => $b->id, 'period' => '2026-08', 'last_number' => 1],
    ]);

    expect(DB::table('document_sequences')->count())->toBe(2);
});

it('rejects an unknown document type instead of issuing an empty number', function () {
    $location = Location::factory()->create();

    expect(fn () => app(DocumentNumberService::class)->issue('not_a_document_type', $location->id, Carbon::parse('2026-08-05')))
        ->toThrow(InvalidArgumentException::class, 'not_a_document_type');

    expect(DB::table('document_sequences')->count())->toBe(0);
});
