<?php

namespace App\Enums;

enum ProductSource: string
{
    case SAP = 'sap';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::SAP => 'SAP',
            self::MANUAL => 'Manual',
        };
    }
}
