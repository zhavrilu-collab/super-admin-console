<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Gdpr\GdprDataExportService;
use App\Services\Gdpr\GdprExportResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformGdprExportController extends Controller
{
    public function __construct(
        private readonly GdprDataExportService $exports,
        private readonly GdprExportResponseFactory $responses,
    ) {}

    public function export(Request $request): JsonResponse|Response|StreamedResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Neautorizirano.');
        }

        if ($user->isSuperAdmin()) {
            abort(403, 'Super-admin izvoz ide kroz konzolu.');
        }

        $format = $request->string('format', 'json')->toString();

        if (! in_array($format, ['json', 'csv'], true)) {
            abort(422, 'Format mora biti json ili csv.');
        }

        $maxExports = max(1, (int) config('gdpr.max_exports_per_hour', 3));

        if ($this->exports->recentExportCount($user) >= $maxExports) {
            abort(429, 'Previše zahtjeva za izvoz podataka. Pokušajte kasnije.');
        }

        $payload = $this->exports->buildExport($user);
        $this->exports->logExport($user, $format, $request->ip());

        $download = ! $request->boolean('inline');

        return $this->responses->respond(
            $payload,
            $format,
            'platform-gdpr-export-'.$user->id.'-'.now()->format('Ymd-His'),
            $download,
        );
    }
}
