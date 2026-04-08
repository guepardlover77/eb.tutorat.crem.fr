<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Student;
use PHPUnit\Framework\TestCase;

class StudentModelTest extends TestCase
{
    private function make(array $attrs = []): Student
    {
        $s = new Student;
        foreach ($attrs as $k => $v) {
            $s->$k = $v;
        }

        return $s;
    }

    public function test_full_name_concatenates_first_and_last(): void
    {
        $s = $this->make(['first_name' => 'Alice', 'last_name' => 'Martin']);
        $this->assertSame('Alice Martin', $s->fullName());
    }

    public function test_crem_prefix_returns_first_char(): void
    {
        $s = $this->make(['crem_number' => '10023']);
        $this->assertSame('1', $s->cremPrefix());
    }

    public function test_crem_prefix_returns_null_when_no_crem(): void
    {
        $s = $this->make(['crem_number' => null]);
        $this->assertNull($s->cremPrefix());
    }

    public function test_crem_prefix_9_for_las2(): void
    {
        $s = $this->make(['crem_number' => '90005']);
        $this->assertSame('9', $s->cremPrefix());
    }
}
