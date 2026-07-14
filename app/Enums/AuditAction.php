<?php

namespace App\Enums;

enum AuditAction: string
{
    case TenantStatusChanged = 'tenant.status_changed';
    case TenantPlanChanged = 'tenant.plan_changed';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';

    public function label(): string
    {
        return match ($this) {
            self::TenantStatusChanged => 'Promjena statusa tenanta',
            self::TenantPlanChanged => 'Promjena plana tenanta',
            self::ImpersonationStarted => 'Support ulaz u tenant',
            self::ImpersonationEnded => 'Support izlaz iz tenanta',
        };
    }
}
