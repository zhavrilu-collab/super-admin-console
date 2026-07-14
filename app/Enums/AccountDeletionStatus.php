<?php

namespace App\Enums;

enum AccountDeletionStatus: string
{
    case Pending = 'pending';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Zakazano',
            self::Cancelled => 'Otkazano',
            self::Completed => 'Izvršeno',
        };
    }
}
