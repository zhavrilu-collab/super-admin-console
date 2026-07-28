<?php

namespace App\Enums;

enum ApplicationFeatureType: string
{
    case Boolean = 'boolean';
    case Limit = 'limit';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Da / Ne',
            self::Limit => 'Numerički limit',
        };
    }
}
