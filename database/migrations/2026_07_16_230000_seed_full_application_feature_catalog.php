<?php

use App\Models\Application;
use App\Services\Admin\ApplicationFeatureCatalogService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = app(ApplicationFeatureCatalogService::class);

        foreach (Application::query()->cursor() as $application) {
            $catalog->seedDefaults($application);
        }
    }

    public function down(): void
    {
        // Katalog se ne briše — admin može ručno urediti.
    }
};
