<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationFeatureType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApplicationFeatureRequest;
use App\Http\Requests\Admin\UpdateApplicationFeatureRequest;
use App\Models\ApplicationFeature;
use App\Services\Admin\AdminSaaSService;
use App\Services\Admin\ApplicationFeatureCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminApplicationFeatureController extends Controller
{
    public function __construct(
        private readonly AdminSaaSService $adminSaaSService,
        private readonly ApplicationFeatureCatalogService $featureCatalog,
    ) {}

    public function index(): View|RedirectResponse
    {
        $application = $this->adminSaaSService->getActiveApplication();

        if ($application === null) {
            return redirect()
                ->route('admin.applications.index')
                ->with('warning', 'Prvo odaberite ili kreirajte aplikaciju.');
        }

        $this->featureCatalog->seedDefaults($application);

        return view('admin.application-features.index', [
            'activeApplication' => $application,
            'features' => $this->featureCatalog->forApplication($application->id),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StoreApplicationFeatureRequest $request): RedirectResponse
    {
        $application = $this->requireActiveApplication();
        $payload = $request->validatedPayload();

        ApplicationFeature::query()->create([
            'application_id' => $application->id,
            ...$payload,
        ]);

        return redirect()
            ->route('admin.application-features.index')
            ->with('status', 'Značajka je dodana u katalog.');
    }

    public function update(UpdateApplicationFeatureRequest $request, ApplicationFeature $applicationFeature): RedirectResponse
    {
        $this->assertBelongsToActiveApplication($applicationFeature);

        $applicationFeature->fill($request->validatedPayload());
        $applicationFeature->save();

        return redirect()
            ->route('admin.application-features.index')
            ->with('status', 'Značajka je ažurirana.');
    }

    public function destroy(ApplicationFeature $applicationFeature): RedirectResponse
    {
        $this->assertBelongsToActiveApplication($applicationFeature);

        try {
            $this->featureCatalog->assertDeletable($applicationFeature);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.application-features.index')
                ->with('warning', collect($exception->errors())->flatten()->first());
        }

        $applicationFeature->delete();

        return redirect()
            ->route('admin.application-features.index')
            ->with('status', 'Značajka je obrisana.');
    }

    private function requireActiveApplication()
    {
        $application = $this->adminSaaSService->getActiveApplication();

        if ($application === null) {
            throw new NotFoundHttpException('Nema aktivne aplikacije.');
        }

        return $application;
    }

    private function assertBelongsToActiveApplication(ApplicationFeature $feature): void
    {
        $applicationId = $this->adminSaaSService->getActiveApplicationId();

        if ($applicationId === null || $feature->application_id !== $applicationId) {
            throw new NotFoundHttpException('Značajka nije pronađena za aktivnu aplikaciju.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function typeOptions(): array
    {
        return collect(ApplicationFeatureType::cases())
            ->mapWithKeys(fn (ApplicationFeatureType $type) => [$type->value => $type->label()])
            ->all();
    }
}
