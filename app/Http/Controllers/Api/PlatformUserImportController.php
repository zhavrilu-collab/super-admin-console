<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlatformUserImportRequest;
use App\Models\Application;
use App\Services\Identity\PlatformAuthService;
use Illuminate\Http\JsonResponse;

class PlatformUserImportController extends Controller
{
    public function __construct(
        private readonly PlatformAuthService $platformAuth,
    ) {}

    public function store(PlatformUserImportRequest $request): JsonResponse
    {
        $application = Application::query()
            ->where('slug', $request->string('application_slug')->toString())
            ->firstOrFail();

        /** @var list<array{external_id: string, name: string, email: string, password: string}> $users */
        $users = collect($request->input('users'))
            ->map(fn (array $user) => [
                'external_id' => (string) $user['external_id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'password' => (string) $user['password'],
            ])
            ->all();

        $imported = $this->platformAuth->importUsers($application, $users);

        return response()->json([
            'imported' => $imported,
        ]);
    }
}
