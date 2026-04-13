<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * Imports a manager-grid schedule Excel/CSV file and saves it as a draft.
 *
 * Expected layout (rows are 1-indexed):
 *   Row 1 — orange banner / title (skipped)
 *   Row 2 — "Department:" / value / "Week Start:" / value (skipped)
 *   Row 3 — spacer (skipped)
 *   Row 4 — headers: "Employee Name" | Mon DD/MM | Tue DD/MM | … | Sun DD/MM
 *   Row 5+ — data: employee name or ac_no | cell values per day
 *
 * Cell values (case-insensitive):
 *   off / day_off            → day_off
 *   sick / sick_day          → sick_day
 *   leave / leave_request    → leave_request
 *   <shift name>             → shift (looked up by name, dept: any)
 *
 * Department enforcement: employees are matched by name (or ac_no) only within
 * the given department_id. Cross-department matches are rejected as failed rows.
 */
class ExcelScheduleImportService
{
    public function __construct(private ScheduleSaveService $saveService) {}

    /**
     * @return array{success_count: int, failed_count: int, failed_rows: list<array{row: int, reason: string}>}
     */
    public function import(
        UploadedFile $file,
        int $departmentId,
        string $weekStart,
        int $changedBy
    ): array {
        $rows = $this->loadAllRows($file);

        if (count($rows) < 4) {
            return ['success_count' => 0, 'failed_count' => 0, 'failed_rows' => []];
        }

        // Row 4 (index 3) is the header row
        $headerRow = array_values($rows[3]);

        // Parse day dates from the header — columns 1..7
        $dates = $this->parseDatesFromHeader($headerRow, $weekStart);

        // Pre-load employees in this department (matched by name or ac_no)
        $deptUsers = User::where('department_id', $departmentId)
            ->get(['id', 'name', 'nickname', 'ac_no']);

        $nameMap  = $deptUsers->keyBy(fn ($u) => strtolower(trim($u->name)));
        $acNoMap  = $deptUsers->whereNotNull('ac_no')->keyBy(fn ($u) => strtolower(trim($u->ac_no)));

        // Pre-load shifts
        $shiftMap = Shift::all()->keyBy(fn ($s) => strtolower(trim($s->name)));

        $typeMap = [
            'off'            => 'day_off',
            'day_off'        => 'day_off',
            'sick'           => 'sick_day',
            'sick_day'       => 'sick_day',
            'leave'          => 'leave_request',
            'leave_request'  => 'leave_request',
        ];

        $assignments = [];
        $failedRows  = [];

        // Data rows start at index 4 (row 5), Excel row number = index + 1
        for ($i = 4; $i < count($rows); $i++) {
            $row       = array_values($rows[$i]);
            $excelRow  = $i + 1;
            $nameOrAc  = trim((string) ($row[0] ?? ''));

            if ($nameOrAc === '') {
                continue; // blank row
            }

            $nameLower = strtolower($nameOrAc);
            $user      = $nameMap[$nameLower] ?? $acNoMap[$nameLower] ?? null;

            if (! $user) {
                $failedRows[] = ['row' => $excelRow, 'reason' => "Employee '{$nameOrAc}' not found in department."];
                continue;
            }

            // Columns 1..7 correspond to Mon..Sun
            for ($col = 1; $col <= 7; $col++) {
                if (! isset($dates[$col - 1])) {
                    continue;
                }

                $date     = $dates[$col - 1];
                $cellRaw  = trim((string) ($row[$col] ?? ''));
                $cellLow  = strtolower($cellRaw);

                if ($cellRaw === '') {
                    $failedRows[] = ['row' => $excelRow, 'reason' => "Empty cell for {$date}."];
                    continue;
                }

                if (isset($typeMap[$cellLow])) {
                    $assignments[] = [
                        'user_id'  => $user->id,
                        'date'     => $date,
                        'type'     => $typeMap[$cellLow],
                        'shift_id' => null,
                    ];
                } elseif (isset($shiftMap[$cellLow])) {
                    $assignments[] = [
                        'user_id'  => $user->id,
                        'date'     => $date,
                        'type'     => 'shift',
                        'shift_id' => $shiftMap[$cellLow]->id,
                    ];
                } else {
                    $failedRows[] = ['row' => $excelRow, 'reason' => "Unknown value '{$cellRaw}' on {$date}."];
                }
            }
        }

        if (! empty($assignments)) {
            $this->saveService->save(
                [
                    'department_id' => $departmentId,
                    'week_start'    => $weekStart,
                    'assignments'   => $assignments,
                    'changed_by'    => $changedBy,
                ],
                publish: false,
                forcedraft: true
            );
        }

        return [
            'success_count' => count($assignments),
            'failed_count'  => count($failedRows),
            'failed_rows'   => $failedRows,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load every row from the file as a flat array of cell values.
     * Returns all rows including header rows (no skipping here).
     */
    private function loadAllRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->loadCsvRows($file->getRealPath());
        }

        return $this->loadExcelRows($file->getRealPath());
    }

    private function loadCsvRows(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_values($row);
        }

        fclose($handle);

        return $rows;
    }

    private function loadExcelRows(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cellValues   = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $cellValues[] = $cell->getFormattedValue();
            }

            $rows[] = array_values($cellValues);
        }

        return $rows;
    }

    /**
     * Parse Mon–Sun dates from the header row (columns 1–7).
     * Header cells look like "Mon 07/04" or "Mon\n07/04" or just "2026-04-07".
     * Falls back to computing from week_start if parsing fails.
     *
     * @return list<string>   7 date strings (Y-m-d)
     */
    private function parseDatesFromHeader(array $headerRow, string $weekStart): array
    {
        $dates = [];

        for ($col = 1; $col <= 7; $col++) {
            $header = trim((string) ($headerRow[$col] ?? ''));

            // Try explicit YYYY-MM-DD
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $header, $m)) {
                $dates[] = $m[1];
                continue;
            }

            // Try DD/MM (we append the year from week_start)
            if (preg_match('/(\d{1,2})\/(\d{1,2})/', $header, $m)) {
                $year    = Carbon::parse($weekStart)->year;
                $parsed  = Carbon::createFromFormat('d/m/Y', $m[1] . '/' . $m[2] . '/' . $year);
                $dates[] = $parsed->toDateString();
                continue;
            }

            // Fallback: offset from week_start (Monday = col 1)
            $dates[] = Carbon::parse($weekStart)->addDays($col - 1)->toDateString();
        }

        return $dates;
    }
}
