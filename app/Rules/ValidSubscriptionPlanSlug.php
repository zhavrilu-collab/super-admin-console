<?php

namespace App\Rules;

use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\SubscriptionPlanService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSubscriptionPlanSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Paket pretplate nije ispravan.');

            return;
        }

        $applicationId = app(AdminSaaSService::class)->getActiveApplicationId();
        $plan = app(SubscriptionPlanService::class)->findForApplication($applicationId, $value);

        if ($plan === null) {
            $fail('Odabrani paket ne postoji za aktivnu aplikaciju.');
        }
    }
}
