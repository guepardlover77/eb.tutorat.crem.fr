<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\HelloassoToken;
use Carbon\Carbon;
use Tests\TestCase;

class HelloassoTokenTest extends TestCase
{
    public function test_is_valid_returns_true_when_far_from_expiry(): void
    {
        $token = new HelloassoToken;
        $token->expires_at = Carbon::now()->addHour();

        $this->assertTrue($token->isValid());
    }

    public function test_is_valid_returns_false_when_already_expired(): void
    {
        $token = new HelloassoToken;
        $token->expires_at = Carbon::now()->subMinute();

        $this->assertFalse($token->isValid());
    }

    public function test_is_valid_returns_false_when_expires_within_5_minutes(): void
    {
        $token = new HelloassoToken;
        $token->expires_at = Carbon::now()->addMinutes(3);

        $this->assertFalse($token->isValid());
    }

    public function test_is_valid_returns_false_when_expires_at_is_null(): void
    {
        $token = new HelloassoToken;
        $token->expires_at = null;

        $this->assertFalse($token->isValid());
    }
}
