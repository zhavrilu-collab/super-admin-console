<?php

namespace App\Http\Requests\Admin;

use App\Contracts\TenantSyncDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('sync_driver') === '') {
            $this->merge(['sync_driver' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:applications,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sync_driver' => ['nullable', 'string', Rule::in($this->allowedDrivers())],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'api_sync_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedDrivers(): array
    {
        $drivers = config('saas_applications.drivers', []);

        if (! is_array($drivers)) {
            return [];
        }

        return collect($drivers)
            ->pluck('class')
            ->filter(fn ($class) => is_string($class) && is_subclass_of($class, TenantSyncDriver::class))
            ->values()
            ->all();
    }
}
