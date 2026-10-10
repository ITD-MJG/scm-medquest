<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class BatchResolver
{
    /**
     * Spellings a user may enter for "no lot number". Compared case-insensitively
     * after trimming; every one collapses to the same key so the unique index on
     * (product_id, lot_no, expiry_date) merges them instead of creating duplicates.
     */
    private const NONE_VALUES = ['', 'n/a', '-'];

    /**
     * Normalise a lot number to the value stored in `batches.lot_no`.
     *
     * The column is NOT NULL with a `''` default because MySQL treats NULLs as
     * distinct in a unique index — a nullable key column would let the same
     * physical batch be inserted twice (spec §5).
     */
    public static function normalizeLotNo(?string $lotNo): string
    {
        $trimmed = trim((string) $lotNo);

        return self::isNone($trimmed) ? '' : $trimmed;
    }

    /**
     * Normalise an expiry date to the value stored in `batches.expiry_date`.
     *
     * Same reasoning as the lot number: a "no expiry" line must hash to the same
     * key as every other "no expiry" line, so it is stored as the sentinel date
     * rather than NULL (spec §5).
     */
    public static function normalizeExpiryDate(null|string|CarbonInterface $expiryDate): string
    {
        if ($expiryDate instanceof CarbonInterface) {
            return $expiryDate->toDateString();
        }

        $trimmed = trim((string) $expiryDate);

        if (self::isNone($trimmed)) {
            return Batch::EXPIRY_SENTINEL;
        }

        return Carbon::parse($trimmed)->toDateString();
    }

    /**
     * Return the batch a receipt line belongs to, creating it in quarantine if this
     * is the first time the physical batch has been seen.
     *
     * Merges quantity into an existing batch because the Expired and Relocation forms
     * assume one row per physical batch (spec §6). `room_id` is only filled in when the
     * batch has none — the first recorded location wins — and `location_id` never moves
     * on merge. Callers own the transaction; this method does not open one.
     */
    public function resolve(
        Product $product,
        ?string $lotNo,
        null|string|CarbonInterface $expiryDate,
        float $quantity,
        string $uom,
        ?Room $room,
        int $locationId,
    ): Batch {
        $normalizedLotNo = self::normalizeLotNo($lotNo);
        $normalizedExpiryDate = self::normalizeExpiryDate($expiryDate);

        $batch = Batch::query()
            ->where('product_id', $product->id)
            ->where('lot_no', $normalizedLotNo)
            ->where('expiry_date', $normalizedExpiryDate)
            ->first();

        if ($batch !== null) {
            $batch->quantity = (float) $batch->quantity + $quantity;

            if ($batch->room_id === null && $room !== null) {
                $batch->room_id = $room->id;
            }

            $batch->save();

            return $batch;
        }

        // Eloquent does not hydrate database column defaults, so `status` must be set
        // explicitly or the enum cast yields null on the returned model.
        return Batch::create([
            'batchId' => (string) Str::orderedUuid(),
            'product_id' => $product->id,
            'lot_no' => $normalizedLotNo,
            'expiry_date' => $normalizedExpiryDate,
            'quantity' => $quantity,
            'uom' => $uom,
            'status' => BatchStatus::QUARANTINE,
            'location_id' => $locationId,
            'room_id' => $room?->id,
        ]);
    }

    private static function isNone(string $value): bool
    {
        return in_array(strtolower($value), self::NONE_VALUES, true);
    }
}
