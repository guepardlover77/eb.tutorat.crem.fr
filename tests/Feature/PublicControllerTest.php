<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $attrs = []): Student
    {
        static $i = 0;

        return Student::create(array_merge([
            'helloasso_item_id' => ++$i,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => "pub{$i}@test.com",
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
        ], $attrs));
    }

    // -----------------------------------------------------------------------
    // placement (public page)
    // -----------------------------------------------------------------------

    public function test_placement_page_is_publicly_accessible(): void
    {
        $this->get('/placement')->assertOk();
    }

    public function test_placement_page_shows_amphitheater_with_placed_students(): void
    {
        $amphi = Amphitheater::create(['name' => 'Debré gauche', 'capacity' => 50, 'sort_order' => 1]);
        $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '1',
            'crem_number' => '10001',
            'is_excluded' => false,
        ]);

        $this->get('/placement')->assertOk()->assertSee('Debré gauche');
    }

    public function test_placement_page_hides_amphitheaters_with_no_placed_students(): void
    {
        Amphitheater::create(['name' => 'Empty Amphi', 'capacity' => 50, 'sort_order' => 1]);

        // No students placed → amphi filtered out
        $this->get('/placement')->assertOk()->assertDontSee('Empty Amphi');
    }

    public function test_placement_page_excludes_la_rochelle_students(): void
    {
        $amphi = Amphitheater::create(['name' => 'Debré gauche', 'capacity' => 50, 'sort_order' => 1]);
        $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '1',
            'crem_number' => '70001',
            'is_excluded' => false,
        ]);

        // La Rochelle students (7xxx) are filtered out in placementData
        $response = $this->get('/placement/data');
        $response->assertOk();
        $data = $response->json('amphitheaters');
        $this->assertEmpty($data);
    }

    // -----------------------------------------------------------------------
    // placementData (JSON)
    // -----------------------------------------------------------------------

    public function test_placement_data_returns_json(): void
    {
        $this->get('/placement/data')
            ->assertOk()
            ->assertJsonStructure(['hash', 'amphitheaters']);
    }

    public function test_placement_data_includes_seat_and_crem(): void
    {
        $amphi = Amphitheater::create(['name' => 'Rambaud', 'capacity' => 50, 'sort_order' => 1]);
        $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '5',
            'crem_number' => '10001',
            'is_excluded' => false,
        ]);

        $response = $this->get('/placement/data')->assertOk();
        $amphis = $response->json('amphitheaters');

        $this->assertNotEmpty($amphis);
        $students = $amphis[0]['students'];
        $this->assertNotEmpty($students);
        $this->assertArrayHasKey('seat', $students[0]);
        $this->assertArrayHasKey('crem', $students[0]);
    }

    public function test_placement_data_hash_changes_when_data_changes(): void
    {
        $hash1 = $this->get('/placement/data')->json('hash');

        $amphi = Amphitheater::create(['name' => 'Tourette', 'capacity' => 50, 'sort_order' => 1]);
        $this->makeStudent([
            'amphitheater_id' => $amphi->id,
            'seat_number' => '1',
            'crem_number' => '10001',
            'is_excluded' => false,
        ]);

        $hash2 = $this->get('/placement/data')->json('hash');

        $this->assertNotEquals($hash1, $hash2);
    }

    // -----------------------------------------------------------------------
    // monNumero
    // -----------------------------------------------------------------------

    public function test_mon_numero_page_loads_on_get(): void
    {
        $this->get('/placement/mon-numero')->assertOk();
    }

    public function test_mon_numero_returns_not_found_for_unknown_email(): void
    {
        $response = $this->post('/placement/mon-numero', ['email' => 'ghost@unknown.com']);

        $response->assertOk()->assertSee('introuvable');
    }

    public function test_mon_numero_returns_crem_for_adherent(): void
    {
        $this->makeStudent([
            'email' => 'adherent@test.com',
            'crem_number' => '10001',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        $response = $this->post('/placement/mon-numero', ['email' => 'adherent@test.com']);

        $response->assertOk()->assertSee('10001');
    }

    public function test_mon_numero_returns_auto_status_for_8xxx_crem(): void
    {
        $this->makeStudent([
            'email' => 'auto@test.com',
            'crem_number' => '8001',
        ]);

        $response = $this->post('/placement/mon-numero', ['email' => 'auto@test.com']);

        $response->assertOk()->assertSee('8001');
    }

    public function test_mon_numero_validates_email_format(): void
    {
        $this->post('/placement/mon-numero', ['email' => 'not-an-email'])
            ->assertSessionHasErrors(['email']);
    }

    public function test_mon_numero_requires_email_field(): void
    {
        $this->post('/placement/mon-numero', [])
            ->assertSessionHasErrors(['email']);
    }
}
