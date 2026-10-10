<?php

namespace App\Console\Commands;

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

        $result = $service->import((bool) $this->option('force'));

        $this->info("Import finished: {$result->created} created, {$result->updated} updated, {$result->skipped} skipped.");

        return self::SUCCESS;
    }
}
