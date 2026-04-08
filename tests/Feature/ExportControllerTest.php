<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Amphitheater $amphi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->amphi = Amphitheater::create(['name' => 'Debré gauche', 'capacity' => 50, 'sort_order' => 1]);
    }

    private function makeStudent(array $attrs = []): Student
    {
        static $i = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$i,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => "export{$i}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_export_amphi(): void
    {
        $this->get("/export/amphi/{$this->amphi->id}")->assertRedirect('/login');
    }

    public function test_guest_cannot_export_emargement(): void
    {
        $this->get("/export/emargement/{$this->amphi->id}")->assertRedirect('/login');
    }

    public function test_guest_cannot_export_all(): void
    {
        $this->get('/export/all')->assertRedirect('/login');
    }

    public function test_guest_cannot_export_student_placement_check(): void
    {
        $this->get('/export/student-placement/check')->assertRedirect('/login');
    }

    public function test_guest_cannot_export_recuperation_emails(): void
    {
        $this->get('/export/recuperation-no-option-emails')->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // amphi XLSX
    // -----------------------------------------------------------------------

    public function test_amphi_export_returns_xlsx(): void
    {
        $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'seat_number' => '1']);

        $response = $this->actingAs($this->user)
            ->get("/export/amphi/{$this->amphi->id}");

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
    }

    public function test_amphi_export_filename_contains_amphi_name(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/export/amphi/{$this->amphi->id}");

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('debre-gauche', $disposition);
    }

    // -----------------------------------------------------------------------
    // emargement XLSX
    // -----------------------------------------------------------------------

    public function test_emargement_export_returns_xlsx(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/export/emargement/{$this->amphi->id}");

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
    }

    public function test_emargement_export_filename_contains_amphi_name(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/export/emargement/{$this->amphi->id}");

        $disposition = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('emargement', $disposition);
        $this->assertStringContainsString('debre-gauche', $disposition);
    }

    // -----------------------------------------------------------------------
    // studentPlacementCheck (JSON)
    // -----------------------------------------------------------------------

    public function test_student_placement_check_returns_json(): void
    {
        $this->actingAs($this->user)
            ->getJson('/export/student-placement/check')
            ->assertOk()
            ->assertJsonStructure(['students']);
    }

    public function test_student_placement_check_includes_error_students(): void
    {
        $this->makeStudent(['has_error' => true, 'error_message' => 'CREM mismatch', 'last_name' => 'ErrorStudent']);

        $response = $this->actingAs($this->user)
            ->getJson('/export/student-placement/check')
            ->assertOk();

        $students = $response->json('students');
        $this->assertNotEmpty($students);
        $names = array_column($students, 'nom');
        $this->assertTrue(
            count(array_filter($names, fn ($n) => str_contains(strtoupper($n), 'ERRORSTUDENT'))) > 0
        );
    }

    public function test_student_placement_check_includes_unplaced_students(): void
    {
        $this->makeStudent(['seat_number' => null, 'last_name' => 'Unplaced']);

        $response = $this->actingAs($this->user)
            ->getJson('/export/student-placement/check')
            ->assertOk();

        $students = $response->json('students');
        $this->assertNotEmpty($students);
    }

    public function test_student_placement_check_excludes_excluded_students(): void
    {
        $this->makeStudent(['is_excluded' => true, 'last_name' => 'ExcludedStudent']);

        $response = $this->actingAs($this->user)
            ->getJson('/export/student-placement/check')
            ->assertOk();

        $students = $response->json('students');
        $names = array_column($students, 'nom');
        $this->assertTrue(
            count(array_filter($names, fn ($n) => str_contains(strtoupper($n), 'EXCLUDEDSTUDENT'))) === 0
        );
    }

    // -----------------------------------------------------------------------
    // recuperationNoOptionEmails
    // -----------------------------------------------------------------------

    public function test_recuperation_emails_returns_text_file(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/export/recuperation-no-option-emails');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('content-type') ?? '');
    }

    public function test_recuperation_emails_contains_excluded_students_without_recovery_option(): void
    {
        $this->makeStudent([
            'is_excluded' => true,
            'recovery_option' => null,
            'email' => 'recup@test.com',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/export/recuperation-no-option-emails');

        $response->assertOk();
        $this->assertStringContainsString('recup@test.com', $response->getContent());
    }

    public function test_recuperation_emails_excludes_students_with_recovery_option(): void
    {
        $this->makeStudent([
            'is_excluded' => true,
            'recovery_option' => 'option_a',
            'email' => 'has-option@test.com',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/export/recuperation-no-option-emails');

        $this->assertStringNotContainsString('has-option@test.com', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // studentPlacement XLSX download
    // -----------------------------------------------------------------------

    public function test_student_placement_export_returns_xlsx(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/export/student-placement');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
    }

    // -----------------------------------------------------------------------
    // allAmphis ZIP download
    // -----------------------------------------------------------------------

    public function test_all_amphi_export_redirects_when_no_students_placed(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/export/all');

        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    #[RequiresPhpExtension('zip')]
    public function test_all_amphi_export_includes_amphi_files_when_students_placed(): void
    {
        $this->makeStudent([
            'amphitheater_id' => $this->amphi->id,
            'seat_number' => '1',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/export/all');

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition') ?? '';
        $this->assertStringContainsString('export-complet', $disposition);
    }
}
