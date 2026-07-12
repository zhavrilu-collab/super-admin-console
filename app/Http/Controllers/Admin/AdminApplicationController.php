<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApplicationRequest;
use App\Http\Requests\Admin\UpdateApplicationRequest;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminApplicationController extends Controller
{
    public function index(): View
    {
        return view('admin.applications.index', [
            'applications' => Application::query()
                ->withCount('tenants')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.applications.create', [
            'drivers' => $this->driverOptions(),
        ]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Application::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sync_driver' => $data['sync_driver'] ?? null,
            'api_base_url' => $data['api_base_url'] ?? null,
            'api_sync_key' => $data['api_sync_key'] ?? null,
        ]);

        return redirect()
            ->route('admin.applications.index')
            ->with('status', 'Aplikacija je dodana.');
    }

    public function edit(Application $application): View
    {
        return view('admin.applications.edit', [
            'application' => $application,
            'drivers' => $this->driverOptions(),
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application): RedirectResponse
    {
        $data = $request->validated();

        $application->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'sync_driver' => $data['sync_driver'] ?? null,
            'api_base_url' => $data['api_base_url'] ?? null,
        ]);

        if (! empty($data['api_sync_key'])) {
            $application->api_sync_key = $data['api_sync_key'];
        }

        $application->save();

        return redirect()
            ->route('admin.applications.index')
            ->with('status', 'Aplikacija je ažurirana.');
    }

    public function destroy(Application $application): RedirectResponse
    {
        if ($application->tenants()->exists()) {
            return redirect()
                ->route('admin.applications.index')
                ->with('warning', 'Aplikacija ima tenante i ne može se obrisati.');
        }

        $application->delete();

        return redirect()
            ->route('admin.applications.index')
            ->with('status', 'Aplikacija je obrisana.');
    }

    /**
     * @return array<string, string>
     */
    private function driverOptions(): array
    {
        $drivers = config('saas_applications.drivers', []);

        if (! is_array($drivers)) {
            return [];
        }

        $options = ['' => 'Bez API sinkronizacije'];

        foreach ($drivers as $driver) {
            if (! is_array($driver)) {
                continue;
            }

            $class = $driver['class'] ?? null;
            $label = $driver['label'] ?? $class;

            if (is_string($class) && is_string($label)) {
                $options[$class] = $label;
            }
        }

        return $options;
    }
}
