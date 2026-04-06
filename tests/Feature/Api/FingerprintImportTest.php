<?php

namespace Tests\Feature\Api;

use Tests\Feature\ApiTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * M3 — Fingerprint file import endpoint.
 * SRS requirements: M3-IMP-01 through M3-IMP-05
 */
class FingerprintImportTest extends ApiTestCase
{
    // ─── CSV Content helpers ─────────────────────────────────────────────────

    /**
     * Build a CSV string matching the exact fingerprint format:
     * AC_No, Name, Time (M/D/YYYY H:MM AM/PM), State (C-In / C-Out)
     */
    private function makeCsv(array $rows): string
    {
        $lines = ["AC_No,Name,Time,State"];
        foreach ($rows as $r) {
            $lines[] = implode(',', $r);
        }
        return implode("\n", $lines);
    }

    private function csvFile(string $content, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'fp_');
        file_put_contents($path, $content);
        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    // ─── Authentication / Authorization ──────────────────────────────────────

    public function test_unauthenticated_cannot_upload(): void
    {
        $csv  = $this->makeCsv([]);
        $file = $this->csvFile($csv);

        $this->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-02',
        ])->assertUnauthorized();
    }

    public function test_non_compliance_cannot_upload(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);
        $csv     = $this->makeCsv([]);
        $file    = $this->csvFile($csv);

        $this->actingAs($manager)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-02',
        ])->assertForbidden();
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_upload_requires_file(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'week_start' => '2026-06-02',
        ])->assertUnprocessable();
    }

    public function test_upload_requires_week_start(): void
    {
        $compliance = $this->createCompliance();
        $csv        = $this->makeCsv([['001', 'John', '6/2/2026 8:00 AM', 'C-In']]);
        $file       = $this->csvFile($csv);

        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file' => $file,
        ])->assertUnprocessable();
    }

    public function test_upload_rejects_empty_csv(): void
    {
        $compliance = $this->createCompliance();
        $file       = $this->csvFile("AC_No,Name,Time,State\n"); // header only

        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-02',
        ])->assertUnprocessable()->assertJsonPath('message', 'The file contains no data rows.');
    }

    // ─── Core import logic ────────────────────────────────────────────────────

    public function test_successful_import_creates_fingerprint_records(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('FP Dept');
        $emp        = $this->createUser($dept);
        $emp->update(['ac_no' => 'AC001']);

        $csv = $this->makeCsv([
            ['AC001', $emp->name, '6/2/2026 8:00 AM', 'C-In'],
            ['AC001', $emp->name, '6/2/2026 5:00 PM', 'C-Out'],
        ]);
        $file = $this->csvFile($csv);

        $response = $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('rows_imported', 1)
            ->assertJsonPath('rows_failed', 0);

        $this->assertDatabaseHas('fingerprint_records', [
            'user_id'     => $emp->id,
            'record_date' => '2026-06-02',
            'clock_in'    => '08:00:00',
            'clock_out'   => '17:00:00',
        ]);
    }

    public function test_import_picks_first_cin_and_last_cout_per_day(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Multi Swipe Dept');
        $emp        = $this->createUser($dept);
        $emp->update(['ac_no' => 'AC002']);

        // Multiple swipes of same type — pick first C-In, last C-Out
        $csv = $this->makeCsv([
            ['AC002', $emp->name, '6/2/2026 8:00 AM', 'C-In'],
            ['AC002', $emp->name, '6/2/2026 8:02 AM', 'C-In'],   // second C-In (should be ignored)
            ['AC002', $emp->name, '6/2/2026 4:00 PM', 'C-Out'],
            ['AC002', $emp->name, '6/2/2026 5:00 PM', 'C-Out'],  // last C-Out (should be kept)
        ]);
        $file = $this->csvFile($csv);

        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('fingerprint_records', [
            'user_id'     => $emp->id,
            'record_date' => '2026-06-02',
            'clock_in'    => '08:00:00',
            'clock_out'   => '17:00:00',
        ]);
    }

    public function test_import_logs_unmatched_ac_no_as_failed(): void
    {
        $compliance = $this->createCompliance();

        $csv = $this->makeCsv([
            ['UNKNOWN_AC', 'Ghost User', '6/2/2026 8:00 AM', 'C-In'],
        ]);
        $file = $this->csvFile($csv);

        $response = $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $file,
            'week_start' => '2026-06-01',
        ])->assertStatus(201);

        $this->assertGreaterThan(0, $response->json('rows_failed'));
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_reimport_upserts_existing_record(): void
    {
        $compliance = $this->createCompliance();
        $dept       = $this->createDepartment('Re-Import Dept');
        $emp        = $this->createUser($dept);
        $emp->update(['ac_no' => 'AC003']);

        $csv1 = $this->makeCsv([
            ['AC003', $emp->name, '6/2/2026 8:00 AM', 'C-In'],
            ['AC003', $emp->name, '6/2/2026 5:00 PM', 'C-Out'],
        ]);
        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $this->csvFile($csv1),
            'week_start' => '2026-06-01',
        ])->assertStatus(201);

        // Re-import with corrected clock_in
        $csv2 = $this->makeCsv([
            ['AC003', $emp->name, '6/2/2026 9:00 AM', 'C-In'],   // corrected
            ['AC003', $emp->name, '6/2/2026 5:00 PM', 'C-Out'],
        ]);
        $this->actingAs($compliance)->postJson('/api/fingerprint/imports/upload', [
            'file'       => $this->csvFile($csv2),
            'week_start' => '2026-06-01',
        ])->assertStatus(201);

        // Should have updated clock_in, not created duplicate
        $this->assertDatabaseHas('fingerprint_records', [
            'user_id'     => $emp->id,
            'record_date' => '2026-06-02',
            'clock_in'    => '09:00:00',
        ]);
        $this->assertDatabaseCount('fingerprint_records', 1);
    }

    // ─── Import listing ───────────────────────────────────────────────────────

    public function test_compliance_can_list_imports(): void
    {
        $compliance = $this->createCompliance();

        $this->actingAs($compliance)
            ->getJson('/api/fingerprint/imports')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_non_compliance_cannot_list_imports(): void
    {
        $dept    = $this->createDepartment();
        $manager = $this->createManager($dept);

        $this->actingAs($manager)
            ->getJson('/api/fingerprint/imports')
            ->assertForbidden();
    }
}
