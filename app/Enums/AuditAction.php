<?php

namespace App\Enums;

enum AuditAction: string
{
    case TenantStatusChanged = 'tenant.status_changed';
    case TenantPlanChanged = 'tenant.plan_changed';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';
    case BillingDunningOpened = 'billing.dunning_opened';
    case BillingDunningReminderSent = 'billing.dunning_reminder_sent';
    case BillingDunningSuspended = 'billing.dunning_suspended';
    case BillingDunningResolved = 'billing.dunning_resolved';

    public function label(): string
    {
        return match ($this) {
            self::TenantStatusChanged => 'Promjena statusa tenanta',
            self::TenantPlanChanged => 'Promjena plana tenanta',
            self::ImpersonationStarted => 'Support ulaz u tenant',
            self::ImpersonationEnded => 'Support izlaz iz tenanta',
            self::BillingDunningOpened => 'Neuspjela uplata (dunning)',
            self::BillingDunningReminderSent => 'Dunning podsjetnik poslan',
            self::BillingDunningSuspended => 'Auto-suspend (dunning)',
            self::BillingDunningResolved => 'Dunning riješen',
        };
    }
}
