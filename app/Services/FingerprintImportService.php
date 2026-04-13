<?php

namespace App\Services;

use App\Models\FingerprintImport;
use App\Models\FingerprintRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FingerprintImportService
{
    public function __construct(private AttendanceComputeService $computeService) {}

    /**
     * Parse and store a fingerprint file for the given week.
     *
     * @return array{rows_imported: int, rows_failed: int, errors: list<string>}
     */
    public function upload(UploadedFile $file, string $weekStart, int $userId): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows      = $this->parseFile($file->getRealPath(), $extension);

        if (empty($rows)) {
            abort(422, 'The file contains no data rows.');
        }

        // Group swipes: ac_no → date → ['ins' => [], 'outs' => []]
        $grouped = [];
        $errors  = [];

        foreach ($rows as $i => $row) {
            $acNo    = trim((string) ($row[0] ?? ''));
            $rawTime = trim((string) ($row[2] ?? ''));
            $state   = str_replace('/', '-', strtolower(trim((string) ($row[3] ?? ''))));

            if ($acNo === '' || $rawTime === '') {
                continue;
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
            }
        }

        $acNos = array_keys($grouped);
        $users = User::whereIn('ac_no', $acNos)->pluck('id', 'ac_no')->all();

        $rowsImported = 0;
        $rowsFailed   = 0;

        DB::transaction(function () use (
            $grouped, $users, $weekStart, $userId, $file,
            &$errors, &$rowsImported, &$rowsFailed
        ) {
            $import = FingerprintImport::create([
                'imported_by'   => $userId,
                'week_start'    => $weekStart,
                'filename'      => $file->getClientOriginalName(),
                'status'        => 'pending',
                'rows_imported' => 0,
                'rows_failed'   => 0,
                'error_log'     => null,
                'imported_at'   => now(),
            ]);

            foreach ($grouped as $acNo => $dates) {
                $mappedUserId = $users[$acNo] ?? null;

                if (! $mappedUserId) {
                    $rowsFailed += count($dates);
                    $errors[]    = "AC_No '{$acNo}' not found in system.";
                    continue;
                }

                foreach ($dates as $date => $swipes) {
                    $clockIn  = ! empty($swipes['ins'])  ? min($swipes['ins'])  : null;
                    $clockOut = ! empty($swipes['outs']) ? max($swipes['outs']) : null;

                    FingerprintRecord::updateOrCreate(
                        ['user_id' => $mappedUserId, 'record_date' => $date],
                        ['import_id' => $import->id, 'clock_in' => $clockIn, 'clock_out' => $clockOut]
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

        $this->computeService->recompute(null, $weekStart);

        return [
            'rows_imported' => $rowsImported,
            'rows_failed'   => $rowsFailed,
            'errors'        => $errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  File parsing helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function parseFile(string $path, string $extension): array
    {
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($path);
        }

        return $this->parseExcel($path);
    }

    private function parseCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        fgetcsv($handle); // skip header row

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
                continue;
            }

            $cellValues   = [];
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

    private function parseDateTime(string $raw): ?Carbon
    {
        $formats = [
            'n/j/Y g:i A',
            'n/d/Y g:i A',
            'd/n/Y g:i A',
            'd/m/Y g:i A',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw);
            } catch (\Exception) {
                // try next format
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
