<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Amphitheater;
use PHPUnit\Framework\TestCase;

class AmphitheaterModelTest extends TestCase
{
    private function make(array $attrs = []): Amphitheater
    {
        $a = new Amphitheater();
        foreach (array_merge(['capacity' => 100, 'seat_layout' => null], $attrs) as $k => $v) {
            $a->$k = $v;
        }
        return $a;
    }

    public function test_seat_count_uses_capacity_when_no_layout(): void
    {
        $a = $this->make(['capacity' => 150]);
        $this->assertSame(150, $a->seatCount());
    }

    public function test_seat_count_uses_layout_length_when_set(): void
    {
        $seats = range(1, 42);
        $a = $this->make(['seat_layout' => $seats]);
        $this->assertSame(42, $a->seatCount());
    }

    public function test_fill_rate_zero_when_no_seats(): void
    {
        $a = $this->make(['capacity' => 0]);
        $this->assertSame(0.0, $a->fillRate());
    }
}
