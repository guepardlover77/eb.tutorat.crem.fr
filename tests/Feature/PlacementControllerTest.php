<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlacementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seedAmphis();
    }

    private function seedAmphis(): void
    {
        $amphis = [
            ['name' => 'Debré gauche', 'capacity' => 50, 'sort_order' => 1],
            ['name' => 'Debré droit',  'capacity' => 50, 'sort_order' => 2],
            ['name' => 'Debré haut',   'capacity' => 50, 'sort_order' => 3],
            ['name' => 'Côme Bas',     'capacity' => 50, 'sort_order' => 4],
            ['name' => 'Côme Haut',    'capacity' => 50, 'sort_order' => 5],
            ['name' => 'Beauchamps',   'capacity' => 50, 'sort_order' => 6],
            ['name' => 'Rambaud',      'capacity' => 50, 'sort_order' => 7],
            ['name' => 'Tourette',     'capacity' => 50, 'sort_order' => 8],
            ['name' => 'Lefèvre',      'capacity' => 50, 'sort_order' => 9],
        ];
        foreach ($amphis as $a) {
            Amphitheater::create($a);
        }
    }

    private function makeStudent(array $attrs = []): Student
    {
        static $i = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$i,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => "student{$i}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'crem_number' => '1000'.$i,
            'is_excluded' => false,
            'is_manually_placed' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_run_placement(): void
    {
        $this->post('/placement/run')->assertRedirect('/login');
    }

    public function test_guest_cannot_reset_placement(): void
    {
        $this->post('/placement/reset')->assertRedirect('/login');
    }

    public function test_guest_cannot_assign_numbers(): void
    {
        $this->post('/placement/assign-numbers')->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // run
    // -----------------------------------------------------------------------

    public function test_run_redirects_to_dashboard(): void
    {
        $this->makeStudent(['crem_number' => '10001', 'tier_name' => 'LAS 1 - INSCRITS au Tutorat']);

        $this->actingAs($this->user)
            ->post('/placement/run')
            ->assertRedirect('/');
    }

    public function test_run_sets_success_flash(): void
    {
        $this->makeStudent(['crem_number' => '10001', 'tier_name' => 'LAS 1 - INSCRITS au Tutorat']);

        $this->actingAs($this->user)
            ->post('/placement/run')
            ->assertSessionHas('success');
    }

    public function test_run_places_students(): void
    {
        $s = $this->makeStudent(['crem_number' => '10001', 'tier_name' => 'LAS 1 - INSCRITS au Tutorat']);

        $this->actingAs($this->user)->post('/placement/run');

        $this->assertNotNull($s->fresh()->amphitheater_id);
    }

    // -----------------------------------------------------------------------
    // reset
    // -----------------------------------------------------------------------

    public function test_reset_clears_placements_with_manual(): void
    {
        $amphi = Amphitheater::first();
        $s = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '1', 'is_manually_placed' => true]);

        $this->actingAs($this->user)
            ->post('/placement/reset', ['include_manual' => '1'])
            ->assertRedirect('/');

        $this->assertNull($s->fresh()->amphitheater_id);
        $this->assertFalse($s->fresh()->is_manually_placed);
    }

    public function test_reset_preserves_manual_when_include_manual_false(): void
    {
        $amphi = Amphitheater::first();
        $auto = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '1', 'is_manually_placed' => false]);
        $manual = $this->makeStudent(['amphitheater_id' => $amphi->id, 'seat_number' => '2', 'is_manually_placed' => true]);

        $this->actingAs($this->user)
            ->post('/placement/reset', ['include_manual' => '0'])
            ->assertRedirect('/');

        $this->assertNull($auto->fresh()->amphitheater_id);
        $this->assertEquals($amphi->id, $manual->fresh()->amphitheater_id);
    }

    public function test_reset_sets_success_flash(): void
    {
        $this->actingAs($this->user)
            ->post('/placement/reset')
            ->assertSessionHas('success');
    }

    // -----------------------------------------------------------------------
    // assignNumbers
    // -----------------------------------------------------------------------

    public function test_assign_numbers_redirects_to_dashboard(): void
    {
        $this->actingAs($this->user)
            ->post('/placement/assign-numbers')
            ->assertRedirect('/');
    }

    public function test_assign_numbers_sets_success_flash(): void
    {
        $this->makeStudent(['crem_number' => null]);

        $this->actingAs($this->user)
            ->post('/placement/assign-numbers')
            ->assertSessionHas('success');
    }

    public function test_assign_numbers_assigns_8xxx_to_students_without_crem(): void
    {
        $s = $this->makeStudent(['crem_number' => null]);

        $this->actingAs($this->user)->post('/placement/assign-numbers');

        $this->assertStringStartsWith('8', $s->fresh()->crem_number);
    }

    // -----------------------------------------------------------------------
    // Error paths (service throws)
    // -----------------------------------------------------------------------

    public function test_run_returns_error_flash_when_service_throws(): void
    {
        $this->mock(\App\Services\PlacementService::class, function ($mock) {
            $mock->shouldReceive('run')->andThrow(new \RuntimeException('boom'));
        });

        $this->actingAs($this->user)
            ->post('/placement/run')
            ->assertRedirect('/')
            ->assertSessionHas('error');
    }

    public function test_assign_numbers_returns_error_flash_when_service_throws(): void
    {
        $this->mock(\App\Services\PlacementService::class, function ($mock) {
            $mock->shouldReceive('assignAutoNumbers')->andThrow(new \RuntimeException('boom'));
        });

        $this->actingAs($this->user)
            ->post('/placement/assign-numbers')
            ->assertRedirect('/')
            ->assertSessionHas('error');
    }
}
