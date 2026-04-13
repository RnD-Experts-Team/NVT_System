<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\FingerprintUploadRequest;
use App\Models\FingerprintImport;
use App\Services\FingerprintImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class FingerprintImportController extends Controller
{
    public function __construct(private FingerprintImportService $service) {}

    public function index(): JsonResponse
    {
        $imports = FingerprintImport::with('importedBy')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $imports->map(fn ($i) => $this->serializeImport($i)),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page'    => $imports->lastPage(),
                'total'        => $imports->total(),
            ],
        ]);
    }

    public function upload(FingerprintUploadRequest $request): JsonResponse
    {
        $result = $this->service->upload(
            $request->file('file'),
            $request->input('week_start'),
            $request->user()->id
        );

        return response()->json([
            'message'       => 'Import completed.',
            'rows_imported' => $result['rows_imported'],
            'rows_failed'   => $result['rows_failed'],
            'errors'        => $result['errors'],
        ], 201);
    }

    private function serializeImport(FingerprintImport $import): array
    {
        return [
            'id'            => $import->id,
            'filename'      => $import->filename,
            'week_start'    => $import->week_start?->toDateString(),
            'status'        => $import->status,
            'rows_imported' => $import->rows_imported,
            'rows_failed'   => $import->rows_failed,
            'error_log'     => $import->error_log,
            'imported_at'   => $import->imported_at?->toISOString(),
            'imported_by'   => $import->importedBy ? [
                'id'   => $import->importedBy->id,
                'name' => $import->importedBy->name,
            ] : null,
        ];
    }
}
