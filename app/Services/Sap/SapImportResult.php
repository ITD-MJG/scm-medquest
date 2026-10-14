<?php

namespace App\Services\Sap;

final readonly class SapImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->created === 0 && $this->updated === 0 && $this->skipped === 0;
    }
}
