<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\AdminStatsService;
use App\Services\Admin\ApplicationFeatureCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminStatsController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly AdminStatsService $statsService,
        private readonly ApplicationFeatureCatalogService $featureCatalog,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $application = $this->adminSaaSService->getActiveApplication();

        if ($application === null) {
            return redirect()
                ->route('admin.applications.index')
                ->with('warning', 'Prvo odaberite ili kreirajte aplikaciju.');
        }

        $this->featureCatalog->seedDefaults($application);

        $validated = $request->validate([
            'metric' => ['nullable', Rule::in(['plan', 'status', 'feature', 'mrr'])],
            'value' => ['nullable', 'string', 'max:100'],
        ]);

        $metric = $validated['metric'] ?? null;
        $value = $validated['value'] ?? null;
        $aggregates = $this->statsService->aggregates($application->id);

        $drillDownTenants = null;
        $metricLabel = null;
        $valueLabel = null;

        if (is_string($metric) && is_string($value) && $metric !== '' && $value !== '') {
            $drillDownTenants = $this->statsService->drillDown($application->id, $metric, $value);
            $metricLabel = $this->statsService->metricLabel($metric);
            $valueLabel = $this->statsService->valueLabel($application->id, $metric, $value);
        }

        return view('admin.stats.index', [
            'activeApplication' => $application,
            'aggregates' => $aggregates,
            'drillDownTenants' => $drillDownTenants,
            'metric' => $metric,
            'value' => $value,
            'metricLabel' => $metricLabel,
            'valueLabel' => $valueLabel,
        ]);
    }
}
