<?php

namespace App\Http\Controllers\Api;

use App\Models\FingerprintImport;
use App\Models\FingerprintRecord;
use App\Models\User;
use App\Services\AttendanceComputeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class FingerprintImportController extends Controller
{
    /**
     * GET /api/fingerprint/imports
     * List all imports (most recent first).
     */
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

    /**
     * POST /api/fingerprint/imports/upload
     * Upload and parse a fingerprint CSV or XLSX file.
     *
     * Expected file columns (in order): AC_No, Name, Time, State
     *   AC_No – employee access control number → maps to users.ac_no
     *   Name  – employee display name (used only for unmatched-row logging)
     *   Time  – datetime string e.g. "1/06/2026 9:11 PM" or "2026-06-01 21:11"
     *   State – "C-In" or "C-Out"
     *
     * Each row is a single fingerprint swipe. The controller groups rows by
     * (ac_no, date) then picks:
     *   clock_in  = first C-In swipe of the day
     *   clock_out = last  C-Out swipe of the day
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'       => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'week_start' => ['required', 'date_format:Y-m-d'],
        ]);
        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $weekStart = $validated['week_start'];
        $userId    = $request->user()->id;

       

        // Parse all rows from the file
        $rows = $this->parseFile($file->getRealPath(), $extension);

       

        if (empty($rows)) {
            return response()->json(['message' => 'The file contains no data rows.'], 422);
        }

        // Group swipes by ac_no → date
        $grouped = [];   // [ ac_no => [ 'Y-m-d' => ['ins' => [...], 'outs' => [...]] ] ]
        $errors  = [];

        foreach ($rows as $i => $row) {
            $acNo    = trim((string) ($row[0] ?? ''));
            $rawTime = trim((string) ($row[2] ?? ''));
            $state   = str_replace('/', '-', strtolower(trim((string) ($row[3] ?? ''))));

            

            if ($acNo === '' || $rawTime === '') {
                continue; // empty row — skip silently
            }

            $dt = $this->parseDateTime($rawTime);
            if (! $dt) {
                $errors[] = "Row " . ($i + 2) . ": Cannot parse datetime '{$rawTime}'";
                continue;
            }

            $date = $dt->toDateString();
            $time = $dt->format('H:i:s');

            if ($state === 'c-in') {
                $grouped[$acNo][$date]['ins'][] = $time;
            } elseif ($state === 'c-out') {
                $grouped[$acNo][$date]['outs'][] = $time;
            } else {
            }
        }

        // Resolve ac_no → user_id
        $acNos  = array_keys($grouped);
        $users  = User::whereIn('ac_no', $acNos)->pluck('id', 'ac_no')->all();

      

        $rowsImported = 0;
        $rowsFailed   = 0;

        DB::transaction(function () use (
            $grouped, $users, $weekStart, $userId, $file,
            &$errors, &$rowsImported, &$rowsFailed
        ) {
            $import = FingerprintImport::create([
                'imported_by'  => $userId,
                'week_start'   => $weekStart,
                'filename'     => $file->getClientOriginalName(),
                'status'       => 'pending',
                'rows_imported' => 0,
                'rows_failed'  => 0,
                'error_log'    => null,
                'imported_at'  => now(),
            ]);

            foreach ($grouped as $acNo => $dates) {
                $mappedUserId = $users[$acNo] ?? null;

                if (! $mappedUserId) {
                    $rowsFailed += count($dates);
                    $errors[] = "AC_No '{$acNo}' not found in system.";
                    continue;
                }

                foreach ($dates as $date => $swipes) {
                    $clockIn  = ! empty($swipes['ins'])  ? min($swipes['ins'])  : null;
                    $clockOut = ! empty($swipes['outs']) ? max($swipes['outs']) : null;

                    // Upsert: re-import overwrites existing record
                    FingerprintRecord::updateOrCreate(
                        [
                            'user_id'     => $mappedUserId,
                            'record_date' => $date,
                        ],
                        [
                            'import_id' => $import->id,
                            'clock_in'  => $clockIn,
                            'clock_out' => $clockOut,
                        ]
                    );

                    $rowsImported++;
                }
            }

            $import->update([
                'status'        => 'processed',
                'rows_imported' => $rowsImported,
                'rows_failed'   => $rowsFailed,
                'error_log'     => empty($errors) ? null : implode("\n", $errors),
            ]);
        });

       

        // Recompute attendance statuses for this week so the audit grid is populated
        (new AttendanceComputeService())->recompute(null, $weekStart);

        return response()->json([
            'message'       => 'Import completed.',
            'rows_imported' => $rowsImported,
            'rows_failed'   => $rowsFailed,
            'errors'        => $errors,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse file into a flat array of rows.
     * Each row is a zero-indexed array of cell values.
     * Skips the header row automatically.
     */
    private function parseFile(string $path, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($path);
        }

        // XLSX / XLS via PhpSpreadsheet (bundled with maatwebsite/excel)
        return $this->parseExcel($path);
    }

    private function parseCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle); // skip header row
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_values($row);
        }

        fclose($handle);

        return $rows;
    }

    private function parseExcel(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];
        $firstRow    = true;

        foreach ($sheet->getRowIterator() as $row) {
            if ($firstRow) {
                $firstRow = false;
                continue; // skip header
            }

            $cellValues = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $cellValues[] = $cell->getFormattedValue();
            }

            if (array_filter($cellValues, fn ($v) => $v !== '' && $v !== null)) {
                $rows[] = array_values($cellValues);
            }
        }

        return $rows;
    }

    /**
     * Parse a datetime string in multiple possible formats:
     *   M/D/YYYY H:MM AM/PM  (fingerprint machine default)
     *   YYYY-MM-DD HH:MM:SS
     *   YYYY-MM-DD HH:MM
     */
    private function parseDateTime(string $raw): ?Carbon
    {
        $formats = [
            'n/j/Y g:i A',   // 1/6/2026 9:11 AM
            'n/d/Y g:i A',   // 1/06/2026 9:11 AM
            'd/n/Y g:i A',   // 06/1/2026 9:11 AM
            'd/m/Y g:i A',   // 06/01/2026 9:11 AM
            'Y-m-d H:i:s',   // 2026-06-01 09:11:00
            'Y-m-d H:i',     // 2026-06-01 09:11
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw);
            } catch (\Exception) {
                // try next format
            }
        }

        // Last resort: let Carbon try on its own
        try {
            return Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
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
