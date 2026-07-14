<?php

namespace App\Enums;

enum CustomerWebhookEvent: string
{
    case MemberCreated = 'member.created';
    case MemberApproved = 'member.approved';
    case InvoicePaid = 'invoice.paid';
    case TenantPlanChanged = 'tenant.plan_changed';
    case TenantStatusChanged = 'tenant.status_changed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $event): string => $event->value,
            self::cases(),
        );
    }
}
