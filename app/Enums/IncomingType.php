<?php

namespace App\Enums;

enum IncomingType: string
{
    case NEW_INVENTORY_PO = 'new_inventory_po';
    case NEW_INVENTORY_NON_PO = 'new_inventory_non_po';
    case RETURN = 'return';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NEW_INVENTORY_PO => 'New Inventory (PO)',
            self::NEW_INVENTORY_NON_PO => 'New Inventory (Non-PO)',
            self::RETURN => 'Return',
            self::OTHER => 'Other',
        };
    }
}
