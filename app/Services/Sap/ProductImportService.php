<?php

declare(strict_types=1);

namespace App\Services\Sap;

use App\Enums\ProductSource;
use App\Models\Principal;
use App\Models\Product;
use App\Models\SapConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductImportService
{
    public function import(?SapConnection $connection = null, string $schema = 'MJG_LIVE', bool $force = false): SapImportResult
    {
        if (! config('sap.enabled')) {
            return new SapImportResult;
        }

        $connection ??= SapConnection::where('driver', 'hana')->where('is_active', true)->first();

        $configRows = (array) config('sap.rows');

        if ($configRows !== []) {
            // Config-driven path (tests, offline bootstrap) takes precedence
            $principals = [];
            $rows = collect($configRows)
                ->map(fn (array $row): array => [
                    'sap_code' => (string) $row['sap_code'],
                    'internal_code' => (string) ($row['internal_code'] ?? $row['sap_code']),
                    'description' => $row['description'] ?? null,
                    'principal_code' => (string) ($row['principal_code'] ?? ''),
                    'uom' => $row['uom'] ?? null,
                ])
                ->filter(fn (array $row): bool => $row['sap_code'] !== '')
                ->values()
                ->all();
        } elseif ($connection !== null) {
            $reader = new SapHanaReader($connection, $schema);

            $principals = $reader->readPrincipals();
            $rows = array_map(
                fn (array $row): array => ['internal_code' => $row['sap_code']] + $row,
                array_values(array_filter(
                    $reader->readProducts(),
                    fn (array $row): bool => $row['sap_code'] !== '',
                )),
            );
        } else {
            throw new \RuntimeException('No active SAP HANA connection configured. Create one in System Administration > SAP Connections.');
        }

        if ($rows === []) {
            return new SapImportResult;
        }

        return DB::transaction(function () use ($rows, $principals, $force): SapImportResult {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $product = Product::where('sap_code', $row['sap_code'])->first()
                    ?? Product::whereNull('sap_code')->where('internal_code', $row['internal_code'] ?? $row['sap_code'])->first();

                if ($product === null) {
                    $product = Product::create([
                        'productId' => (string) Str::uuid(),
                        'internal_code' => $row['internal_code'] ?? $row['sap_code'],
                        'description' => $row['description'] ?? $row['sap_code'],
                        'uom' => $row['uom'] ?? 'EA',
                        'uom_source' => ProductSource::SAP->value,
                        'principal_code' => $row['principal_code'],
                        'sap_code' => $row['sap_code'],
                        'source' => ProductSource::SAP,
                    ]);
                    $created++;
                } elseif ($product->source === ProductSource::MANUAL && ! $force) {
                    $skipped++;

                    continue;
                } else {
                    $product->description = $row['description'] ?? $product->description;
                    $product->principal_code = $row['principal_code'];
                    if ($product->uom_source === ProductSource::SAP->value || $force) {
                        $product->uom = $row['uom'] ?? $product->uom;
                    }
                    $updated++;
                }

                $principal = Principal::firstOrCreate(
                    ['code' => $row['principal_code']],
                    [
                        'name' => $principals[$row['principal_code']] ?? $row['principal_code'],
                        'principalId' => (string) Str::uuid(),
                    ],
                );
                $product->principal()->associate($principal);
                $product->save();
            }

            return new SapImportResult($created, $updated, $skipped);
        });
    }
}
