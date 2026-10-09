<?php

namespace App\Enums;

enum StorageRequirement: string
{
    case MINUS_35_MINUS_15 = 'minus35_minus15';
    case MINUS_25_MINUS_10 = 'minus25_minus10';
    case TWO_EIGHT = '2_8';
    case FIFTEEN_TWENTY_FIVE = '15_25';
    case FIFTEEN_THIRTY = '15_30';

    public function label(): string
    {
        return match ($this) {
            self::MINUS_35_MINUS_15 => '-35°C to -15°C',
            self::MINUS_25_MINUS_10 => '-25°C to -10°C',
            self::TWO_EIGHT => '2°C to 8°C',
            self::FIFTEEN_TWENTY_FIVE => '15°C to 25°C',
            self::FIFTEEN_THIRTY => '15°C to 30°C',
        };
    }
}
