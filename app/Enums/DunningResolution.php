<?php

namespace App\Enums;

enum DunningResolution: string
{
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Suspended = 'suspended';
    case Downgraded = 'downgraded';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Uplata uspjela',
            self::Canceled => 'Pretplata otkazana',
            self::Suspended => 'Tenant suspendiran',
            self::Downgraded => 'Downgrade na osnovni paket',
        };
    }
}
