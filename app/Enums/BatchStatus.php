<?php

namespace App\Enums;

enum BatchStatus: string
{
    case QUARANTINE = 'quarantine';
    case RELEASED = 'released';
    case EXPIRED = 'expired';
    case REJECTED = 'rejected';
    case DESTRUCTION_REQUESTED = 'destruction_requested';
    case DESTROYED = 'destroyed';

    public function label(): string
    {
        return match ($this) {
            self::QUARANTINE => 'Quarantine',
            self::RELEASED => 'Released',
            self::EXPIRED => 'Expired',
            self::REJECTED => 'Rejected',
            self::DESTRUCTION_REQUESTED => 'Destruction Requested',
            self::DESTROYED => 'Destroyed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::EXPIRED, self::REJECTED, self::DESTROYED], true);
    }
}
