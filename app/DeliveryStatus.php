<?php

namespace App;

enum DeliveryStatus: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Created => 0,
            self::Assigned => 1,
            self::PickedUp => 2,
            self::InTransit => 3,
            self::Delivered, self::Failed => 4,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::Failed;
    }
}
