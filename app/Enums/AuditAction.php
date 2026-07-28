<?php

namespace App\Enums;

enum AuditAction: string
{
    case TenantStatusChanged = 'tenant.status_changed';
    case TenantPlanChanged = 'tenant.plan_changed';
    case TenantSsoChanged = 'tenant.sso_changed';
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';
    case BillingDunningOpened = 'billing.dunning_opened';
    case BillingDunningReminderSent = 'billing.dunning_reminder_sent';
    case BillingDunningSuspended = 'billing.dunning_suspended';
    case BillingDunningResolved = 'billing.dunning_resolved';
    case AccountDeletionRequested = 'account.deletion_requested';
    case AccountDeletionCancelled = 'account.deletion_cancelled';
    case AccountDeletionCompleted = 'account.deletion_completed';
    case SuperAdminCreated = 'super_admin.created';
    case SuperAdminUpdated = 'super_admin.updated';
    case SuperAdminDeleted = 'super_admin.deleted';

    public function label(): string
    {
        return match ($this) {
            self::TenantStatusChanged => 'Promjena statusa tenanta',
            self::TenantPlanChanged => 'Promjena plana tenanta',
            self::TenantSsoChanged => 'Promjena SSO pravila tenanta',
            self::ImpersonationStarted => 'Support ulaz u tenant',
            self::ImpersonationEnded => 'Support izlaz iz tenanta',
            self::BillingDunningOpened => 'Neuspjela uplata (dunning)',
            self::BillingDunningReminderSent => 'Dunning podsjetnik poslan',
            self::BillingDunningSuspended => 'Auto-suspend (dunning)',
            self::BillingDunningResolved => 'Dunning riješen',
            self::AccountDeletionRequested => 'GDPR brisanje računa',
            self::AccountDeletionCancelled => 'GDPR brisanje otkazano',
            self::AccountDeletionCompleted => 'GDPR brisanje izvršeno',
            self::SuperAdminCreated => 'Super-admin kreiran',
            self::SuperAdminUpdated => 'Super-admin ažuriran',
            self::SuperAdminDeleted => 'Super-admin obrisan',
        };
    }
}
