<?php

namespace App\Models;

use App\Enums\AuditAction;
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
