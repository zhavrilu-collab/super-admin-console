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
        $properties = is_array($this->properties) ? $this->properties : [];
        $tenantName = is_string($properties['tenant_name'] ?? null) && $properties['tenant_name'] !== ''
            ? $properties['tenant_name']
            : 'Tenant';

        return match ($this->action) {
            AuditAction::TenantStatusChanged => sprintf(
                '%s: %s → %s',
                $tenantName,
                $this->tenantStatusLabel($properties['from'] ?? null),
                $this->tenantStatusLabel($properties['to'] ?? null),
            ),
            AuditAction::TenantPlanChanged => sprintf(
                '%s: %s → %s',
                $tenantName,
                $this->planLabelFromAuditProperty('from', $properties),
                $this->planLabelFromAuditProperty('to', $properties),
            ),
            AuditAction::TenantSsoChanged => sprintf(
                '%s: SSO %s → %s',
                $tenantName,
                ($properties['from'] ?? false) ? 'obavezno' : 'opcionalno',
                ($properties['to'] ?? false) ? 'obavezno' : 'opcionalno',
            ),
            AuditAction::TenantDeleted => sprintf(
                'Obrisan tenant: %s (%s)',
                $tenantName,
                $properties['tenant_slug'] ?? '—',
            ),
            AuditAction::ImpersonationStarted => sprintf(
                'Support ulaz: %s%s',
                $tenantName,
                isset($properties['reason']) && $properties['reason'] !== ''
                    ? ' ('.$properties['reason'].')'
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
                $properties['reminder_day'] ?? '?',
                $tenantName,
            ),
            AuditAction::BillingDunningSuspended => sprintf(
                'Auto-suspend: %s',
                $tenantName,
            ),
            AuditAction::BillingDunningResolved => sprintf(
                'Dunning riješen (%s): %s',
                DunningResolution::tryFrom((string) ($properties['resolution'] ?? ''))?->label()
                    ?? ($properties['resolution'] ?? '—'),
                $tenantName,
            ),
            AuditAction::AccountDeletionRequested => sprintf(
                'GDPR brisanje zakazano: %s',
                $properties['email'] ?? 'korisnik',
            ),
            AuditAction::AccountDeletionCancelled => sprintf(
                'GDPR brisanje otkazano: %s',
                $properties['email'] ?? 'korisnik',
            ),
            AuditAction::AccountDeletionCompleted => sprintf(
                'GDPR brisanje izvršeno: %s',
                $properties['email'] ?? 'korisnik',
            ),
            AuditAction::SuperAdminCreated => sprintf(
                'Super-admin kreiran: %s',
                $properties['email'] ?? '—',
            ),
            AuditAction::SuperAdminUpdated => sprintf(
                'Super-admin ažuriran: %s',
                $properties['email'] ?? '—',
            ),
            AuditAction::SuperAdminDeleted => sprintf(
                'Super-admin obrisan: %s',
                $properties['email'] ?? '—',
            ),
            default => $this->action?->label() ?? 'Nepoznata akcija',
        };
    }

    private function tenantStatusLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '—';
        }

        return TenantStatus::tryFrom($value)?->label() ?? $value;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function planLabelFromAuditProperty(string $key, array $properties = []): string
    {
        if ($properties === []) {
            $properties = is_array($this->properties) ? $this->properties : [];
        }

        $slug = $properties[$key] ?? '';

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
