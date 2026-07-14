<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PlatformUserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_slug' => ['required', 'string', 'max:100'],
            'users' => ['required', 'array', 'min:1'],
            'users.*.external_id' => ['required', 'string', 'max:100'],
            'users.*.name' => ['required', 'string', 'max:255'],
            'users.*.email' => ['required', 'email', 'max:255'],
            'users.*.password' => ['required', 'string', 'max:255'],
        ];
    }
}
