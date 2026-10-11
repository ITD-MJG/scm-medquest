<?php

namespace App\Console\Commands;

use App\Models\SapConnection;
use App\Services\Sap\ProductImportService;
use Illuminate\Console\Command;

class ImportSapProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sap:import-products
                            {--connection= : SapConnection name or ID (defaults to the active hana connection)}
                            {--schema=MJG_LIVE : HANA schema holding OITM/OITB}
                            {--force : Overwrite manual products and user-corrected UoMs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap the product catalogue from SAP (one-time import)';

    /**
     * Execute the console command.
     */
    public function handle(ProductImportService $service): int
    {
        if (! config('sap.enabled')) {
            $this->info('SAP import is disabled — nothing imported.');

            return self::SUCCESS;
        }

        $connection = null;
        if ($name = $this->option('connection')) {
            $connection = SapConnection::where('name', $name)->first()
                ?? SapConnection::find($name);

            if ($connection === null) {
                $this->error("SAP connection [{$name}] not found.");

                return self::FAILURE;
            }
        }

        try {
            $result = $service->import(
                $connection,
                (string) $this->option('schema'),
                (bool) $this->option('force'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Import finished: {$result->created} created, {$result->updated} updated, {$result->skipped} skipped.");

        return self::SUCCESS;
    }
}
