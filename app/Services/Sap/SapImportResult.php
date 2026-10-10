<?php

declare(strict_types=1);

namespace App\Services\Sap;

final readonly class SapImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}
}
