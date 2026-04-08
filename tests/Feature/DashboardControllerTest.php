<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_dashboard(): void
    {
        $this->auth()->get('/')->assertOk()->assertSee('Tableau de bord');
    }

    public function test_dashboard_shows_student_stats(): void
    {
        Amphitheater::create(['name' => 'A', 'capacity' => 100, 'sort_order' => 1]);
        $amphi = Amphitheater::first();

        Student::create([
            'helloasso_item_id' => 1, 'first_name' => 'A', 'last_name' => 'B',
            'email' => 'a@b.com', 'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false, 'amphitheater_id' => $amphi->id,
        ]);
        Student::create([
            'helloasso_item_id' => 2, 'first_name' => 'C', 'last_name' => 'D',
            'email' => 'c@d.com', 'tier_name' => "Récupération sans passer l'épreuve",
            'is_excluded' => true,
        ]);

        $response = $this->auth()->get('/');

        $response->assertOk();
        $response->assertSee('1'); // placed count
    }

    public function test_dashboard_shows_last_sync_info(): void
    {
        SyncLog::create([
            'started_at'  => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'status'      => 'success',
            'new_records' => 3,
            'updated_records' => 1,
        ]);

        $this->auth()->get('/')->assertOk()->assertSee('sync');
    }

    public function test_dashboard_shows_failed_sync_warning(): void
    {
        SyncLog::create([
            'started_at'   => now()->subMinutes(5),
            'finished_at'  => now()->subMinutes(4),
            'status'       => 'failed',
            'new_records'  => 0,
            'updated_records' => 0,
            'error_message' => 'Timeout',
        ]);

        $this->auth()->get('/')->assertOk()->assertSee('Échec');
    }

    public function test_dashboard_shows_amphitheater_breakdown(): void
    {
        Amphitheater::create(['name' => 'Debré gauche', 'capacity' => 50, 'sort_order' => 1]);

        $this->auth()->get('/')->assertOk()->assertSee('Debré gauche');
    }
}
