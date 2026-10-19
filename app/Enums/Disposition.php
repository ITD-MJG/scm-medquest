<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Disposition: string implements HasLabel
{
    case RELEASED = 'released';
    case QUARANTINE = 'quarantine';

    public function label(): string
    {
        return match ($this) {
            self::RELEASED => 'Released',
            self::QUARANTINE => 'Quarantine',
        };
    }

    /**
     * `Select::options(Disposition::class)` reads option labels through this
     * interface; without it the select would show the raw case names.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}
