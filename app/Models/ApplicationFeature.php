<?php

namespace App\Models;

use App\Enums\ApplicationFeatureType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFeature extends Model
{
    protected $fillable = [
        'application_id',
        'key',
        'label',
        'description',
        'type',
        'unit',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationFeatureType::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isBoolean(): bool
    {
        return $this->type === ApplicationFeatureType::Boolean;
    }

    public function isLimit(): bool
    {
        return $this->type === ApplicationFeatureType::Limit;
    }
}
