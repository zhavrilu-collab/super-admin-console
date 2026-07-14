<?php

namespace App\Services\Gdpr;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class GdprExportResponseFactory
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function respond(array $payload, string $format, string $basename, bool $download): JsonResponse|Response|StreamedResponse
    {
        if ($format === 'csv') {
            return $this->csvZipResponse($payload, $basename, $download);
        }

        if ($download) {
            return response($this->encodeJson($payload), 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$basename.'.json"',
            ]);
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function csvZipResponse(array $payload, string $basename, bool $download): StreamedResponse|JsonResponse
    {
        $files = $this->buildCsvFiles($payload);

        if (! $download) {
            return response()->json(['data' => $payload, 'csv_files' => array_keys($files)]);
        }

        return response()->streamDownload(function () use ($files): void {
            $temp = tempnam(sys_get_temp_dir(), 'gdpr-export-');

            if ($temp === false) {
                throw new \RuntimeException('Privremena datoteka nije kreirana.');
            }

            $zipPath = $temp.'.zip';
            @unlink($temp);

            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('ZIP arhiva nije kreirana.');
            }

            foreach ($files as $filename => $contents) {
                $zip->addFromString($filename, $contents);
            }

            $zip->close();

            readfile($zipPath);
            @unlink($zipPath);
        }, $basename.'.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function buildCsvFiles(array $payload): array
    {
        $files = [];

        if (isset($payload['profile']) && is_array($payload['profile'])) {
            $files['profile.csv'] = $this->rowsToCsv([$payload['profile']]);
        }

        foreach (['oauth_identities', 'application_links', 'workspaces', 'active_sessions', 'organization_roles', 'memberships', 'payments'] as $section) {
            if (! isset($payload[$section]) || ! is_array($payload[$section]) || $payload[$section] === []) {
                continue;
            }

            $rows = array_map(
                fn (mixed $row): array => $this->flattenRow(is_array($row) ? $row : []),
                $payload[$section],
            );

            $files[$section.'.csv'] = $this->rowsToCsv($rows);
        }

        if ($files === []) {
            $files['export.csv'] = $this->rowsToCsv([['exported_at' => (string) ($payload['exported_at'] ?? now()->toIso8601String())]]);
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, scalar|null>
     */
    private function flattenRow(array $row, string $prefix = ''): array
    {
        $flat = [];

        foreach ($row as $key => $value) {
            $column = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenRow($value, $column));
                continue;
            }

            if (is_bool($value)) {
                $flat[$column] = $value ? '1' : '0';
                continue;
            }

            $flat[$column] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $flat;
    }

    /**
     * @param  list<array<string, scalar|null>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $headers = array_keys($rows[0]);
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('CSV stream nije otvoren.');
        }

        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            $line = [];

            foreach ($headers as $header) {
                $line[] = $row[$header] ?? null;
            }

            fputcsv($stream, $line);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
