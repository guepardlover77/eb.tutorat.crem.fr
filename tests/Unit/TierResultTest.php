<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TierResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TierResultTest extends TestCase
{
    public function test_valid_tier_key_sets_label(): void
    {
        $r = new TierResult('las1_adherent');
        $this->assertSame('LAS 1 - ADHERENT', $r->tierLabel);
    }

    public function test_all_tier_keys_are_valid(): void
    {
        foreach (array_keys(TierResult::LABELS) as $key) {
            $r = new TierResult($key);
            $this->assertNotEmpty($r->tierLabel);
        }
    }

    public function test_unknown_tier_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TierResult('unknown_key');
    }
}
