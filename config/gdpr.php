<?php

return [

    'max_exports_per_hour' => (int) env('GDPR_MAX_EXPORTS_PER_HOUR', 3),

    'erasure_grace_period_days' => (int) env('GDPR_ERASURE_GRACE_PERIOD_DAYS', 14),

];
