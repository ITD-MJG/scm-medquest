<?php

declare(strict_types=1);

namespace App\Services\Sap;

use App\Enums\ProductSource;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductImportService
{
    public function import(bool $force = false): SapImportResult
    {
        if (! config('sap.enabled')) {
            return new SapImportResult;
        }

        return DB::transaction(function () use ($force): SapImportResult {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ((array) config('sap.rows') as $row) {
                $product = Product::where('sap_code', $row['sap_code'] ?? null)->first()
                    ?? Product::whereNull('sap_code')->where('internal_code', $row['internal_code'])->first();

                if ($product === null) {
                    $product = Product::create([
                        'productId' => (string) Str::uuid(),
                        'internal_code' => $row['internal_code'],
                        'description' => $row['description'],
                        'uom' => $row['uom'],
                        'uom_source' => ProductSource::SAP->value,
                        'principal_code' => $row['principal_code'] ?? null,
                        'sap_code' => $row['sap_code'] ?? null,
                        'source' => ProductSource::SAP,
                    ]);
                    $created++;
                } elseif ($product->source === ProductSource::MANUAL && ! $force) {
                    $skipped++;

                    continue;
                } else {
                    $product->description = $row['description'];
                    $product->principal_code = $row['principal_code'] ?? null;
                    if ($product->uom_source === ProductSource::SAP->value || $force) {
                        $product->uom = $row['uom'];
                    }
                    $updated++;
                }

                $principal = Principal::firstOrCreate(
                    ['code' => $row['principal_code']],
                    ['name' => $row['principal_code'], 'principalId' => (string) Str::uuid()],
                );
                $product->principal()->associate($principal);
                $product->save();
            }

            return new SapImportResult($created, $updated, $skipped);
        });
    }
}
