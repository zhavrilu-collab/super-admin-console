<?php

namespace App\Services\Billing;

use App\Enums\AuditAction;
use App\Enums\DunningResolution;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\SubscriptionDunningCase;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Notifications\DunningSuspendedTenantNotification;
use App\Notifications\PaymentFailedReminderNotification;
use App\Services\Admin\SubscriptionPlanService;
use App\Services\Admin\TenantSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BillingDunningService
{
    public function __construct(
        private readonly TenantSyncService $tenantSync,
        private readonly SubscriptionPlanService $subscriptionPlanService,
    ) {}

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function recordPaymentFailure(
        Tenant $tenant,
        ?TenantSubscription $subscription,
        array $invoice,
    ): SubscriptionDunningCase {
        $openCase = $this->openCaseForTenant($tenant);

        if ($openCase !== null) {
            $openCase->forceFill([
                'stripe_invoice_id' => $this->stringOrNull($invoice['id'] ?? null) ?? $openCase->stripe_invoice_id,
                'stripe_subscription_id' => $this->stringOrNull($invoice['subscription'] ?? null)
                    ?? $openCase->stripe_subscription_id,
                'contact_email' => $this->extractContactEmail($invoice) ?? $openCase->contact_email,
            ])->save();

            return $openCase->fresh();
        }

        $startedAt = now();
        $suspendAfterDays = (int) config('billing.dunning.suspend_after_days', 14);

        $case = SubscriptionDunningCase::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription?->id,
            'stripe_invoice_id' => $this->stringOrNull($invoice['id'] ?? null),
            'stripe_subscription_id' => $this->stringOrNull($invoice['subscription'] ?? null),
            'contact_email' => $this->extractContactEmail($invoice),
            'started_at' => $startedAt,
            'suspend_after_at' => $startedAt->copy()->addDays($suspendAfterDays),
        ]);

        $this->logAudit($tenant, AuditAction::BillingDunningOpened, [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'stripe_invoice_id' => $case->stripe_invoice_id,
        ]);

        return $case;
    }

    public function resolveForTenant(Tenant $tenant, DunningResolution $resolution): void
    {
        $openCase = $this->openCaseForTenant($tenant);

        if ($openCase === null) {
            return;
        }

        $openCase->forceFill([
            'resolved_at' => now(),
            'resolution' => $resolution,
        ])->save();

        $this->logAudit($tenant, AuditAction::BillingDunningResolved, [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'resolution' => $resolution->value,
        ]);

        if ($resolution === DunningResolution::Paid && $tenant->status === TenantStatus::Suspended) {
            $this->reactivateTenant($tenant);
        }
    }

    /**
     * @return array{reminders_sent: int, suspensions_applied: int}
     */
    public function processScheduledActions(): array
    {
        $remindersSent = 0;
        $suspensionsApplied = 0;

        $openCases = SubscriptionDunningCase::query()
            ->with(['tenant.application'])
            ->whereNull('resolved_at')
            ->orderBy('id')
            ->get();

        foreach ($openCases as $case) {
            if ($this->processReminder($case)) {
                $remindersSent++;
            }

            if ($this->processSuspension($case)) {
                $suspensionsApplied++;
            }
        }

        return [
            'reminders_sent' => $remindersSent,
            'suspensions_applied' => $suspensionsApplied,
        ];
    }

    private function processReminder(SubscriptionDunningCase $case): bool
    {
        if (! $case->isOpen()) {
            return false;
        }

        /** @var list<int> $reminderDays */
        $reminderDays = config('billing.dunning.reminder_days', [3, 5, 7]);

        foreach ($reminderDays as $index => $day) {
            $stage = $index + 1;

            if ($case->reminder_stage >= $stage) {
                continue;
            }

            if ($case->started_at->copy()->addDays($day)->isFuture()) {
                continue;
            }

            $this->sendReminder($case, $day, $stage);

            return true;
        }

        return false;
    }

    private function processSuspension(SubscriptionDunningCase $case): bool
    {
        if (! $case->isOpen() || $case->suspend_after_at->isFuture()) {
            return false;
        }

        $tenant = $case->tenant;

        if ($tenant === null) {
            return false;
        }

        if ($tenant->status === TenantStatus::Suspended) {
            $case->forceFill([
                'resolved_at' => now(),
                'resolution' => DunningResolution::Suspended,
            ])->save();

            return false;
        }

        $this->suspendTenant($tenant, $case);

        return true;
    }

    private function sendReminder(SubscriptionDunningCase $case, int $day, int $stage): void
    {
        $tenant = $case->tenant;

        if ($tenant === null) {
            return;
        }

        $case->forceFill([
            'reminder_stage' => $stage,
            'last_reminder_at' => now(),
        ])->save();

        $this->notifyContact(
            $case,
            new PaymentFailedReminderNotification($case, $day),
        );

        $this->logAudit($tenant, AuditAction::BillingDunningReminderSent, [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'reminder_day' => $day,
            'stage' => $stage,
        ]);
    }

    private function suspendTenant(Tenant $tenant, SubscriptionDunningCase $case): void
    {
        $previousStatus = $tenant->status;
        $previousPlan = $tenant->plan;

        if ($previousStatus !== TenantStatus::Suspended) {
            $tenant->forceFill(['status' => TenantStatus::Suspended])->save();

            try {
                $this->tenantSync->pushStatus($tenant, TenantStatus::Suspended);
            } catch (\Throwable $exception) {
                Log::warning('Dunning tenant status sync failed.', [
                    'tenant_id' => $tenant->id,
                    'message' => $exception->getMessage(),
                ]);
            }

            $this->logAudit($tenant, AuditAction::TenantStatusChanged, [
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'from' => $previousStatus->value,
                'to' => TenantStatus::Suspended->value,
                'source' => 'billing_dunning',
            ]);
        }

        if ((bool) config('billing.dunning.downgrade_on_suspend', true)) {
            $defaultPlan = $this->subscriptionPlanService->defaultForApplication($tenant->application_id);

            if ($defaultPlan !== null && $tenant->plan !== $defaultPlan->slug) {
                $tenant->forceFill(['plan' => $defaultPlan->slug])->save();

                try {
                    $this->tenantSync->pushPlan($tenant, $defaultPlan->slug);
                } catch (\Throwable $exception) {
                    Log::warning('Dunning tenant plan sync failed.', [
                        'tenant_id' => $tenant->id,
                        'message' => $exception->getMessage(),
                    ]);
                }

                $this->logAudit($tenant, AuditAction::TenantPlanChanged, [
                    'tenant_name' => $tenant->name,
                    'tenant_slug' => $tenant->slug,
                    'from' => $previousPlan,
                    'to' => $defaultPlan->slug,
                    'source' => 'billing_dunning',
                ]);
            }
        }

        $case->forceFill([
            'resolved_at' => now(),
            'resolution' => DunningResolution::Suspended,
        ])->save();

        $this->notifyContact($case, new DunningSuspendedTenantNotification($tenant, $case));

        $this->logAudit($tenant, AuditAction::BillingDunningSuspended, [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'dunning_case_id' => $case->id,
        ]);
    }

    private function reactivateTenant(Tenant $tenant): void
    {
        $previousStatus = $tenant->status;
        $tenant->forceFill(['status' => TenantStatus::Active])->save();

        try {
            $this->tenantSync->pushStatus($tenant, TenantStatus::Active);
        } catch (\Throwable $exception) {
            Log::warning('Dunning tenant reactivation sync failed.', [
                'tenant_id' => $tenant->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $this->logAudit($tenant, AuditAction::TenantStatusChanged, [
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'from' => $previousStatus->value,
            'to' => TenantStatus::Active->value,
            'source' => 'billing_dunning_recovery',
        ]);
    }

    private function openCaseForTenant(Tenant $tenant): ?SubscriptionDunningCase
    {
        return SubscriptionDunningCase::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('resolved_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function extractContactEmail(array $invoice): ?string
    {
        $email = $invoice['customer_email'] ?? null;

        return $this->stringOrNull($email);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logAudit(Tenant $tenant, AuditAction $action, array $properties): void
    {
        AuditLog::query()->create([
            'user_id' => null,
            'application_id' => $tenant->application_id,
            'action' => $action,
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'properties' => $properties,
        ]);
    }

    private function notifyContact(SubscriptionDunningCase $case, object $notification): void
    {
        if (is_string($case->contact_email) && $case->contact_email !== '') {
            Notification::route('mail', $case->contact_email)->notify($notification);

            return;
        }

        Log::warning('Dunning notification skipped — nema kontakt e-maila.', [
            'dunning_case_id' => $case->id,
            'tenant_id' => $case->tenant_id,
        ]);
    }

    public function shouldOpenCaseForSubscriptionStatus(SubscriptionStatus $status): bool
    {
        return in_array($status, [SubscriptionStatus::PastDue, SubscriptionStatus::Unpaid], true);
    }
}
