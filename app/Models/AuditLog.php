<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DunningResolution;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'application_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function summary(): string
    {
        $tenantName = $this->properties['tenant_name'] ?? 'Tenant';

        return match ($this->action) {
            AuditAction::TenantStatusChanged => sprintf(
                '%s: %s → %s',
                $tenantName,
                TenantStatus::from($this->properties['from'])->label(),
                TenantStatus::from($this->properties['to'])->label(),
            ),
            AuditAction::TenantPlanChanged => sprintf(
                '%s: %s → %s',
                $tenantName,
                $this->planLabelFromAuditProperty('from'),
                $this->planLabelFromAuditProperty('to'),
            ),
            AuditAction::TenantSsoChanged => sprintf(
                '%s: SSO %s → %s',
                $tenantName,
                ($this->properties['from'] ?? false) ? 'obavezno' : 'opcionalno',
                ($this->properties['to'] ?? false) ? 'obavezno' : 'opcionalno',
            ),
            AuditAction::ImpersonationStarted => sprintf(
                'Support ulaz: %s%s',
                $tenantName,
                isset($this->properties['reason']) && $this->properties['reason'] !== ''
                    ? ' ('.$this->properties['reason'].')'
                    : '',
            ),
            AuditAction::ImpersonationEnded => sprintf(
                'Support izlaz: %s',
                $tenantName,
            ),
            AuditAction::BillingDunningOpened => sprintf(
                'Neuspjela uplata: %s',
                $tenantName,
            ),
            AuditAction::BillingDunningReminderSent => sprintf(
                'Podsjetnik (%s. dan): %s',
                $this->properties['reminder_day'] ?? '?',
                $tenantName,
            ),
            AuditAction::BillingDunningSuspended => sprintf(
                'Auto-suspend: %s',
                $tenantName,
            ),
            AuditAction::BillingDunningResolved => sprintf(
                'Dunning riješen (%s): %s',
                DunningResolution::tryFrom((string) ($this->properties['resolution'] ?? ''))?->label()
                    ?? ($this->properties['resolution'] ?? '—'),
                $tenantName,
            ),
            AuditAction::AccountDeletionRequested => sprintf(
                'GDPR brisanje zakazano: %s',
                $this->properties['email'] ?? 'korisnik',
            ),
            AuditAction::AccountDeletionCancelled => sprintf(
                'GDPR brisanje otkazano: %s',
                $this->properties['email'] ?? 'korisnik',
            ),
            AuditAction::AccountDeletionCompleted => sprintf(
                'GDPR brisanje izvršeno: %s',
                $this->properties['email'] ?? 'korisnik',
            ),
            AuditAction::SuperAdminCreated => sprintf(
                'Super-admin kreiran: %s',
                $this->properties['email'] ?? '—',
            ),
            AuditAction::SuperAdminUpdated => sprintf(
                'Super-admin ažuriran: %s',
                $this->properties['email'] ?? '—',
            ),
            AuditAction::SuperAdminDeleted => sprintf(
                'Super-admin obrisan: %s',
                $this->properties['email'] ?? '—',
            ),
        };
    }

    private function planLabelFromAuditProperty(string $key): string
    {
        $slug = $this->properties[$key] ?? '';

        if (! is_string($slug) || $slug === '') {
            return '—';
        }

        $plan = SubscriptionPlan::query()
            ->where('application_id', $this->application_id)
            ->where('slug', $slug)
            ->value('name');

        return is_string($plan) && $plan !== '' ? $plan : $slug;
    }
}
