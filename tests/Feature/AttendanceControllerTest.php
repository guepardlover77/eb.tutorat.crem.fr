<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Amphitheater $amphi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->amphi = Amphitheater::create(['name' => 'Salle A', 'capacity' => 50, 'sort_order' => 1]);
    }

    private function makeStudent(array $attrs = []): Student
    {
        static $i = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$i,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => "s{$i}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_access_attendance_index(): void
    {
        $this->get('/emargement')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_attendance_data(): void
    {
        $this->get("/emargement/{$this->amphi->id}/data")->assertRedirect('/login');
    }

    public function test_guest_cannot_toggle_attendance(): void
    {
        $student = $this->makeStudent();
        $this->patch("/emargement/{$student->id}/toggle")->assertRedirect('/login');
    }

    public function test_guest_cannot_reset_amphi(): void
    {
        $this->post("/emargement/{$this->amphi->id}/reset")->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // index
    // -----------------------------------------------------------------------

    public function test_index_shows_amphitheaters(): void
    {
        $this->actingAs($this->user)->get('/emargement')
            ->assertOk()
            ->assertSee('Salle A');
    }

    public function test_index_shows_present_and_total_counts(): void
    {
        $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'is_present' => true]);
        $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'is_present' => false]);

        $this->actingAs($this->user)->get('/emargement')->assertOk();
    }

    // -----------------------------------------------------------------------
    // data (JSON)
    // -----------------------------------------------------------------------

    public function test_data_returns_students_for_amphi(): void
    {
        $s1 = $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'seat_number' => '1', 'is_present' => false]);
        $s2 = $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'seat_number' => '2', 'is_present' => true]);

        $response = $this->actingAs($this->user)
            ->getJson("/emargement/{$this->amphi->id}/data");

        $response->assertOk()
            ->assertJsonStructure(['students', 'present', 'total'])
            ->assertJson(['total' => 2, 'present' => 1]);
    }

    public function test_data_returns_empty_for_amphi_with_no_students(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/emargement/{$this->amphi->id}/data");

        $response->assertOk()
            ->assertJson(['students' => [], 'present' => 0, 'total' => 0]);
    }

    // -----------------------------------------------------------------------
    // toggle
    // -----------------------------------------------------------------------

    public function test_toggle_marks_student_present(): void
    {
        $student = $this->makeStudent(['is_present' => false]);

        $response = $this->actingAs($this->user)
            ->patchJson("/emargement/{$student->id}/toggle");

        $response->assertOk()->assertJson(['is_present' => true]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'is_present' => true]);
    }

    public function test_toggle_marks_student_absent(): void
    {
        $student = $this->makeStudent(['is_present' => true]);

        $response = $this->actingAs($this->user)
            ->patchJson("/emargement/{$student->id}/toggle");

        $response->assertOk()->assertJson(['is_present' => false]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'is_present' => false]);
    }

    public function test_toggle_sets_marked_present_at_when_marking_present(): void
    {
        $student = $this->makeStudent(['is_present' => false]);

        $this->actingAs($this->user)->patchJson("/emargement/{$student->id}/toggle");

        $this->assertNotNull($student->fresh()->marked_present_at);
    }

    public function test_toggle_clears_marked_present_at_when_marking_absent(): void
    {
        $student = $this->makeStudent(['is_present' => true, 'marked_present_at' => now()]);

        $this->actingAs($this->user)->patchJson("/emargement/{$student->id}/toggle");

        $this->assertNull($student->fresh()->marked_present_at);
    }

    // -----------------------------------------------------------------------
    // resetAmphi
    // -----------------------------------------------------------------------

    public function test_reset_marks_all_students_absent(): void
    {
        $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'is_present' => true, 'marked_present_at' => now()]);
        $this->makeStudent(['amphitheater_id' => $this->amphi->id, 'is_present' => true, 'marked_present_at' => now()]);

        $response = $this->actingAs($this->user)
            ->postJson("/emargement/{$this->amphi->id}/reset");

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('students', [
            'amphitheater_id' => $this->amphi->id,
            'is_present' => true,
        ]);
    }

    public function test_reset_does_not_affect_other_amphitheaters(): void
    {
        $other = Amphitheater::create(['name' => 'Other', 'capacity' => 10, 'sort_order' => 2]);
        $s = $this->makeStudent(['amphitheater_id' => $other->id, 'is_present' => true]);

        $this->actingAs($this->user)->postJson("/emargement/{$this->amphi->id}/reset");

        $this->assertTrue($s->fresh()->is_present);
    }
}
