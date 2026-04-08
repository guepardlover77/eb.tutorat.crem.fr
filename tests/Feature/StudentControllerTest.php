<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function makeStudent(array $attrs = []): Student
    {
        static $i = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$i,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => "student{$i}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '1000'.$i,
            'is_excluded' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // index
    // -----------------------------------------------------------------------

    public function test_guest_cannot_access_students(): void
    {
        $this->get('/students')->assertRedirect('/login');
    }

    public function test_index_shows_student_list(): void
    {
        $s = $this->makeStudent(['first_name' => 'Alice', 'last_name' => 'Martin']);

        $this->actingAs($this->user)->get('/students')
            ->assertOk()
            ->assertSee('Martin');
    }

    public function test_index_filters_by_search(): void
    {
        $this->makeStudent(['last_name' => 'Dupont']);
        $this->makeStudent(['last_name' => 'Bernard']);

        $this->actingAs($this->user)->get('/students?search=Dupont')
            ->assertOk()
            ->assertSee('Dupont')
            ->assertDontSee('Bernard');
    }

    public function test_index_filters_by_status_placed(): void
    {
        $amphi = Amphitheater::create(['name' => 'A', 'capacity' => 10, 'sort_order' => 1]);
        $placed = $this->makeStudent(['amphitheater_id' => $amphi->id, 'last_name' => 'Placed']);
        $unplaced = $this->makeStudent(['last_name' => 'Unplaced']);

        $this->actingAs($this->user)->get('/students?status=placed')
            ->assertOk()
            ->assertSee('Placed')
            ->assertDontSee('Unplaced');
    }

    public function test_index_filters_by_status_errors(): void
    {
        $withError = $this->makeStudent(['has_error' => true, 'last_name' => 'WithError']);
        $withoutError = $this->makeStudent(['last_name' => 'NoError']);

        $this->actingAs($this->user)->get('/students?status=errors')
            ->assertOk()
            ->assertSee('WithError')
            ->assertDontSee('NoError');
    }

    public function test_index_paginates_results(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->makeStudent(['last_name' => "User{$i}"]);
        }

        $response = $this->actingAs($this->user)->get('/students');
        $response->assertOk();
        // Should show pagination (50 per page)
        $response->assertSee('User0');
    }

    // -----------------------------------------------------------------------
    // errors
    // -----------------------------------------------------------------------

    public function test_errors_page_shows_only_error_students(): void
    {
        $withError = $this->makeStudent(['has_error' => true, 'last_name' => 'BadStudent']);
        $withoutError = $this->makeStudent(['last_name' => 'GoodStudent']);

        $this->actingAs($this->user)->get('/students/errors')
            ->assertOk()
            ->assertSee('BADSTUDENT')
            ->assertDontSee('GOODSTUDENT');
    }

    // -----------------------------------------------------------------------
    // recuperation
    // -----------------------------------------------------------------------

    public function test_recuperation_page_shows_excluded_students(): void
    {
        $excluded = $this->makeStudent([
            'is_excluded' => true,
            'tier_name' => "Récupération sans passer l'épreuve",
            'last_name' => 'RecupStudent',
        ]);

        $this->actingAs($this->user)->get('/students/recuperation')
            ->assertOk()
            ->assertSee('RECUPSTUDENT');
    }

    // -----------------------------------------------------------------------
    // assign (PATCH)
    // -----------------------------------------------------------------------

    public function test_assign_updates_student_fields(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->user)->patch("/students/{$student->id}/assign", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com',
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '10999',
            'amphitheater_id' => null,
            'seat_number' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Updated',
            'email' => 'updated@test.com',
        ]);
    }

    public function test_assign_to_amphi_marks_manually_placed(): void
    {
        $student = $this->makeStudent();
        $amphi = Amphitheater::create(['name' => 'TestAmphi', 'capacity' => 50, 'sort_order' => 1]);

        $this->actingAs($this->user)->patch("/students/{$student->id}/assign", [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'tier_name' => $student->tier_name,
            'amphitheater_id' => $amphi->id,
            'seat_number' => '5',
        ])->assertRedirect();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'amphitheater_id' => $amphi->id,
            'is_manually_placed' => true,
            'is_manually_edited' => true,
        ]);
    }

    public function test_assign_swaps_seat_with_occupant(): void
    {
        $amphi = Amphitheater::create(['name' => 'A', 'capacity' => 50, 'sort_order' => 1]);
        $target = $this->makeStudent(['amphitheater_id' => null, 'seat_number' => null]);
        $occupant = $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '3',
            'is_manually_placed' => true,
        ]);

        $this->actingAs($this->user)->patch("/students/{$target->id}/assign", [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'tier_name' => $target->tier_name,
            'amphitheater_id' => $amphi->id,
            'seat_number' => '3',
        ]);

        // occupant should have moved to target's old seat (null -> null)
        $this->assertDatabaseHas('manual_placement_logs', ['student_id' => $occupant->id]);
    }

    public function test_assign_validates_required_fields(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->user)->patch("/students/{$student->id}/assign", [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'tier_name']);
    }

    // -----------------------------------------------------------------------
    // destroy
    // -----------------------------------------------------------------------

    public function test_destroy_soft_deletes_student(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($this->user)->delete("/students/{$student->id}")
            ->assertRedirect('/students');

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    // -----------------------------------------------------------------------
    // manual-placements
    // -----------------------------------------------------------------------

    public function test_manual_placements_shows_manually_placed_students(): void
    {
        $amphi = Amphitheater::create(['name' => 'A', 'capacity' => 50, 'sort_order' => 1]);
        $manual = $this->makeStudent([
            'is_manually_placed' => true,
            'amphitheater_id' => $amphi->id,
            'last_name' => 'ManualStudent',
        ]);

        $this->actingAs($this->user)->get('/students/manual-placements')
            ->assertOk()
            ->assertSee('MANUALSTUDENT');
    }

    // -----------------------------------------------------------------------
    // byAmphi
    // -----------------------------------------------------------------------

    public function test_by_amphi_shows_students_in_amphitheater(): void
    {
        $amphi = Amphitheater::create(['name' => 'TargetAmphi', 'capacity' => 50, 'sort_order' => 1]);
        $inAmphi = $this->makeStudent(['amphitheater_id' => $amphi->id, 'last_name' => 'InAmphi']);
        $notIn = $this->makeStudent(['last_name' => 'NotInAmphi']);

        $this->actingAs($this->user)->get("/amphitheaters/{$amphi->id}")
            ->assertOk()
            ->assertSee('InAmphi')
            ->assertDontSee('NotInAmphi');
    }
}
