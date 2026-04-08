<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmphitheaterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_placed_count_returns_student_count(): void
    {
        $amphi = Amphitheater::create(['name' => 'Test', 'capacity' => 10, 'sort_order' => 1]);

        Student::create([
            'helloasso_item_id' => 1,
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => 'a@b.com',
            'tier_name' => 'LAS 1',
            'is_excluded' => false,
            'amphitheater_id' => $amphi->id,
        ]);

        $this->assertSame(1, $amphi->placedCount());
    }

    public function test_placed_count_returns_zero_when_empty(): void
    {
        $amphi = Amphitheater::create(['name' => 'Empty', 'capacity' => 10, 'sort_order' => 1]);

        $this->assertSame(0, $amphi->placedCount());
    }

    public function test_fill_rate_computes_correctly(): void
    {
        $amphi = Amphitheater::create(['name' => 'Half', 'capacity' => 10, 'sort_order' => 1]);

        foreach (range(1, 5) as $i) {
            Student::create([
                'helloasso_item_id' => $i,
                'first_name' => 'A',
                'last_name' => 'B',
                'email' => "s{$i}@b.com",
                'tier_name' => 'LAS 1',
                'is_excluded' => false,
                'amphitheater_id' => $amphi->id,
            ]);
        }

        $this->assertSame(50.0, $amphi->fillRate());
    }
}
