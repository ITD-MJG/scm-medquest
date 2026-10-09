<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\Location;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class DocumentNumberService
{
    private const ROMAN_MONTHS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * Issue the next number for a document type in the period containing $date.
     *
     * The sequence row is read under lockForUpdate() inside a transaction. Without
     * the row lock two concurrent submissions read the same last_number and produce
     * the same number, which on a controlled document is a compliance defect (spec §6).
     */
    public function issue(string $documentType, ?int $locationId, CarbonInterface $date): string
    {
        $period = $date->format('Y-m');

        $sequence = DB::transaction(function () use ($documentType, $locationId, $period) {
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('location_id', $locationId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DocumentSequence::create([
                    'document_type' => $documentType,
                    'location_id' => $locationId,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->last_number++;
            $sequence->save();

            return $sequence;
        });

        return $this->render($documentType, (int) $sequence->last_number, $locationId, $date);
    }

    private function render(string $documentType, int $sequence, ?int $locationId, CarbonInterface $date): string
    {
        $location = $locationId === null ? null : Location::find($locationId);

        $tokens = [
            'location_code' => $location?->location_code ?: (string) $location?->id,
            'roman_month' => self::ROMAN_MONTHS[(int) $date->format('n') - 1],
            'yy' => $date->format('y'),
            'yyyy' => $date->format('Y'),
            'mm' => $date->format('m'),
        ];

        $number = preg_replace_callback(
            '/\{seq:(\d+)\}/',
            fn (array $matches): string => str_pad((string) $sequence, (int) $matches[1], '0', STR_PAD_LEFT),
            (string) config("documents.formats.{$documentType}")
        );

        $number = str_replace('{seq}', str_pad((string) $sequence, (int) config('documents.sequence_padding', 4), '0', STR_PAD_LEFT), $number);

        foreach ($tokens as $token => $value) {
            $number = str_replace('{'.$token.'}', $value, $number);
        }

        return $number;
    }
}
