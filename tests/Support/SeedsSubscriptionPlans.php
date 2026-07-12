<?php

namespace Tests\Support;

use App\Models\Application;
use App\Services\Admin\SubscriptionPlanService;

trait SeedsSubscriptionPlans
{
    protected function seedSubscriptionPlans(Application $application): void
    {
        app(SubscriptionPlanService::class)->seedDefaults($application);
    }
}
