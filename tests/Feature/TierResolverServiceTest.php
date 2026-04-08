<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TutoringMember;
use App\Services\TierResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TierResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private TierResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TierResolverService();
    }

    // --- CREM 1xxx ---

    public function test_crem_1xxx_in_tutorat_returns_las1_adherent(): void
    {
        TutoringMember::create(['crem_number' => '10001']);

        $result = $this->service->resolve('10001', null);

        $this->assertSame('las1_adherent', $result->tierKey);
        $this->assertSame('LAS 1 - ADHERENT', $result->tierLabel);
    }

    public function test_crem_1xxx_not_in_tutorat_returns_las1_sans_tuto(): void
    {
        $result = $this->service->resolve('10002', null);

        $this->assertSame('las1_adherent_sans_tuto', $result->tierKey);
        $this->assertSame('LAS 1 - ADHERENT CREM SANS TUTORAT', $result->tierLabel);
    }

    // --- CREM 9xxx ---

    public function test_crem_9xxx_in_tutorat_returns_las2_adherent(): void
    {
        TutoringMember::create(['crem_number' => '90001']);

        $result = $this->service->resolve('90001', null);

        $this->assertSame('las2_adherent', $result->tierKey);
        $this->assertSame('LAS 2/3 - ADHERENT', $result->tierLabel);
    }

    public function test_crem_9xxx_not_in_tutorat_returns_las2_sans_tuto(): void
    {
        $result = $this->service->resolve('90002', null);

        $this->assertSame('las2_adherent_sans_tuto', $result->tierKey);
        $this->assertSame('LAS 2/3 - ADHERENT CREM SANS TUTORAT', $result->tierLabel);
    }

    // --- Sans CREM ---

    public function test_no_crem_las1_returns_las1_non_adherent(): void
    {
        $result = $this->service->resolve(null, 'las1');

        $this->assertSame('las1_non_adherent', $result->tierKey);
        $this->assertSame('LAS 1 - NON-ADHERENT', $result->tierLabel);
    }

    public function test_no_crem_las2_returns_las2_non_adherent(): void
    {
        $result = $this->service->resolve(null, 'las2');

        $this->assertSame('las2_non_adherent', $result->tierKey);
        $this->assertSame('LAS 2/3 - NON-ADHERENT', $result->tierLabel);
    }

    // --- Cas d'erreur ---

    public function test_crem_7xxx_throws_la_rochelle_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La Rochelle');

        $this->service->resolve('70001', null);
    }

    public function test_crem_invalid_prefix_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalide');

        $this->service->resolve('20001', null);
    }

    public function test_no_crem_no_level_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->resolve(null, null);
    }
}
