<?php

namespace App\Enums;

enum ProductStatus: string
{
    case QUARANTINE = 'quarantine';
    case RELEASED = 'released';
    case RETURN = 'return';

    public function label(): string
    {
        return match ($this) {
            self::QUARANTINE => 'Quarantine',
            self::RELEASED => 'Released',
            self::RETURN => 'Return',
        };
    }
}
