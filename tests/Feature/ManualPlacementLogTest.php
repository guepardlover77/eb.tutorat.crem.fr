<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\ManualPlacementLog;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPlacementLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_belongs_to_student(): void
    {
        $amphi = Amphitheater::create(['name' => 'A', 'capacity' => 10, 'sort_order' => 1]);
        $student = Student::create([
            'helloasso_item_id' => 1,
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'a@b.com',
            'tier_name' => 'LAS 1',
            'is_excluded' => false,
        ]);

        $log = ManualPlacementLog::create([
            'student_id' => $student->id,
            'from_amphitheater' => 'Debré gauche',
            'from_seat' => '1',
            'to_amphitheater' => $amphi->name,
            'to_seat' => '2',
            'created_at' => now(),
        ]);

        $this->assertTrue($log->student->is($student));
    }
}
