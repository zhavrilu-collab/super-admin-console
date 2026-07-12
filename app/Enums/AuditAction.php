<?php

namespace App\Enums;

enum AuditAction: string
{
    case TenantStatusChanged = 'tenant.status_changed';
    case TenantPlanChanged = 'tenant.plan_changed';

    public function label(): string
    {
        return match ($this) {
            self::TenantStatusChanged => 'Promjena statusa tenanta',
            self::TenantPlanChanged => 'Promjena plana tenanta',
        };
    }
}
