<?php

namespace App\Enums;

enum ReceiptCategory: string
{
    case LOCAL = 'local';
    case IMPORT = 'import';

    public function label(): string
    {
        return match ($this) {
            self::LOCAL => 'Local',
            self::IMPORT => 'Import',
        };
    }
}
