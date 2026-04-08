<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlacementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlacementService;
        $this->seedAmphis();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function seedAmphis(): void
    {
        $amphis = [
            ['name' => 'Debré gauche', 'capacity' => 5,  'sort_order' => 1],
            ['name' => 'Debré droit',  'capacity' => 5,  'sort_order' => 2],
            ['name' => 'Debré haut',   'capacity' => 5,  'sort_order' => 3],
            ['name' => 'Côme Bas',     'capacity' => 5,  'sort_order' => 4],
            ['name' => 'Côme Haut',    'capacity' => 5,  'sort_order' => 5],
            ['name' => 'Beauchamps',   'capacity' => 10, 'sort_order' => 6],
            ['name' => 'Rambaud',      'capacity' => 10, 'sort_order' => 7],
            ['name' => 'Tourette',     'capacity' => 10, 'sort_order' => 8],
            ['name' => 'Lefèvre',      'capacity' => 10, 'sort_order' => 9],
        ];

        foreach ($amphis as $a) {
            Amphitheater::create($a);
        }
    }

    private function makeStudent(array $attrs = []): Student
    {
        static $itemId = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$itemId,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => "student{$itemId}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '1000'.$itemId,
            'is_excluded' => false,
            'is_manually_placed' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // run() — basic placement
    // -----------------------------------------------------------------------

    public function test_run_places_las1_members_in_debre(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '10001',
        ]);

        $result = $this->service->run();

        $student->refresh();
        $amphi = Amphitheater::where('name', 'LIKE', 'Debré%')->first();
        $this->assertEquals($amphi->id, $student->amphitheater_id);
        $this->assertSame(1, $result['placed']);
    }

    public function test_run_places_las2_members_in_come(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 2/3 - INSCRITS au Tutorat',
            'crem_number' => '90001',
        ]);

        $this->service->run();
        $student->refresh();

        $comeAmphis = Amphitheater::whereIn('name', ['Côme Bas', 'Côme Haut'])->pluck('id');
        $this->assertContains($student->amphitheater_id, $comeAmphis->toArray());
    }

    public function test_run_places_las2_non_members_in_beauchamps(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 2/3 - NON INSCRITS au Tutorat',
            'crem_number' => null,
        ]);

        $this->service->run();
        $student->refresh();

        $beauch = Amphitheater::where('name', 'Beauchamps')->first();
        $this->assertEquals($beauch->id, $student->amphitheater_id);
    }

    public function test_run_places_las1_non_members_in_rambaud(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 1 - NON INSCRITS au Tutorat',
            'crem_number' => null,
        ]);

        $this->service->run();
        $student->refresh();

        $rambaud = Amphitheater::where('name', 'Rambaud')->first();
        $this->assertEquals($rambaud->id, $student->amphitheater_id);
    }

    public function test_run_does_not_place_excluded_students(): void
    {
        $excluded = $this->makeStudent([
            'tier_name' => "Récupération sans passer l'épreuve",
            'is_excluded' => true,
        ]);

        $this->service->run();
        $excluded->refresh();

        $this->assertNull($excluded->amphitheater_id);
    }

    public function test_run_skips_la_rochelle_7xxx(): void
    {
        // Students with 7xxx are excluded at import, but if somehow present, skip
        $student = $this->makeStudent([
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '70001',
        ]);

        $this->service->run();
        $student->refresh();

        $this->assertNull($student->amphitheater_id);
    }

    public function test_run_preserves_manual_placements(): void
    {
        $amphi = Amphitheater::where('name', 'Côme Bas')->first();
        $manual = $this->makeStudent([
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '10002',
            'amphitheater_id' => $amphi->id,
            'seat_number' => '1',
            'is_manually_placed' => true,
        ]);

        $this->service->run();
        $manual->refresh();

        $this->assertEquals($amphi->id, $manual->amphitheater_id);
    }

    public function test_run_returns_correct_counts(): void
    {
        $this->makeStudent(['tier_name' => 'LAS 1 - INSCRITS au Tutorat', 'crem_number' => '10001']);
        $this->makeStudent(['tier_name' => 'LAS 1 - INSCRITS au Tutorat', 'crem_number' => '10002']);
        $this->makeStudent(['is_excluded' => true, 'tier_name' => "Récupération sans passer l'épreuve"]);

        $result = $this->service->run();

        $this->assertSame(2, $result['placed']);
        $this->assertArrayHasKey('unplaced', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    // -----------------------------------------------------------------------
    // Error detection
    // -----------------------------------------------------------------------

    public function test_run_flags_crem1_with_las2_tier_as_error(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 2/3 - INSCRITS au Tutorat',
            'crem_number' => '10001',
        ]);

        $this->service->run();
        $student->refresh();

        $this->assertTrue($student->has_error);
        $this->assertStringContainsString('1', $student->error_message);
    }

    public function test_run_flags_crem9_with_las1_tier_as_error(): void
    {
        $student = $this->makeStudent([
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '90001',
        ]);

        $this->service->run();
        $student->refresh();

        $this->assertTrue($student->has_error);
        $this->assertStringContainsString('9', $student->error_message);
    }

    public function test_run_flags_duplicate_emails_as_error(): void
    {
        $this->makeStudent(['email' => 'dup@test.com', 'crem_number' => '10001']);
        $this->makeStudent(['email' => 'dup@test.com', 'crem_number' => null]);

        $this->service->run();

        $errors = Student::where('has_error', true)->get();
        $this->assertGreaterThan(0, $errors->count());
    }

    public function test_run_deduplicate_keeps_adherent_over_non_adherent(): void
    {
        // Same email: one with CREM (adherent), one without
        $withCrem = $this->makeStudent([
            'email' => 'same@test.com',
            'crem_number' => '10001',
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
        ]);
        $withoutCrem = $this->makeStudent([
            'email' => 'same@test.com',
            'crem_number' => null,
            'tier_name' => 'LAS 1 - NON INSCRITS au Tutorat',
        ]);

        $this->service->run();

        $withCrem->refresh();
        $withoutCrem->refresh();

        // The one with CREM should be placed, the duplicate should have an error
        $this->assertNotNull($withCrem->amphitheater_id);
        $this->assertTrue($withoutCrem->has_error);
    }

    // -----------------------------------------------------------------------
    // reset()
    // -----------------------------------------------------------------------

    public function test_reset_clears_all_placements(): void
    {
        $amphi = Amphitheater::first();
        $s1 = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '1']);
        $s2 = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '2', 'is_manually_placed' => true]);

        $this->service->reset(true);

        $this->assertNull($s1->fresh()->amphitheater_id);
        $this->assertNull($s2->fresh()->amphitheater_id);
        $this->assertFalse($s2->fresh()->is_manually_placed);
    }

    public function test_reset_preserves_manual_when_flag_false(): void
    {
        $amphi = Amphitheater::first();
        $auto = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '1']);
        $manual = $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '2',
            'is_manually_placed' => true,
        ]);

        $this->service->reset(false);

        $this->assertNull($auto->fresh()->amphitheater_id);
        $this->assertEquals($amphi->id, $manual->fresh()->amphitheater_id);
    }

    // -----------------------------------------------------------------------
    // assignAutoNumbers()
    // -----------------------------------------------------------------------

    public function test_assign_auto_numbers_starts_at_8001(): void
    {
        $s = $this->makeStudent(['crem_number' => null]);

        $count = $this->service->assignAutoNumbers();

        $this->assertSame(1, $count);
        $this->assertSame('8001', $s->fresh()->crem_number);
    }

    public function test_assign_auto_numbers_increments_from_existing_8xxx(): void
    {
        $this->makeStudent(['crem_number' => '8005']);
        $s = $this->makeStudent(['crem_number' => null]);

        $this->service->assignAutoNumbers();

        $this->assertSame('8006', $s->fresh()->crem_number);
    }

    public function test_assign_auto_numbers_skips_excluded(): void
    {
        $excluded = $this->makeStudent(['crem_number' => null, 'is_excluded' => true]);

        $count = $this->service->assignAutoNumbers();

        $this->assertSame(0, $count);
        $this->assertNull($excluded->fresh()->crem_number);
    }

    public function test_assign_auto_numbers_skips_students_with_existing_crem(): void
    {
        $withCrem = $this->makeStudent(['crem_number' => '10001']);

        $this->service->assignAutoNumbers();

        $this->assertSame('10001', $withCrem->fresh()->crem_number);
    }
}
