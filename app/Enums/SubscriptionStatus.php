<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Probno',
            self::Active => 'Aktivna',
            self::PastDue => 'Dospjela uplata',
            self::Canceled => 'Otkazana',
            self::Unpaid => 'Neplaćena',
            self::Incomplete => 'Nepotpuna',
            self::IncompleteExpired => 'Istekla',
            self::Paused => 'Pauzirana',
        };
    }

    public function isBillable(): bool
    {
        return in_array($this, [self::Trialing, self::Active], true);
    }
}
