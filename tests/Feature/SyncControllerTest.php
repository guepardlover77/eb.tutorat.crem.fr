<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SyncLog;
use App\Models\User;
use App\Services\HelloAssoService;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_run_sync(): void
    {
        $this->post('/sync')->assertRedirect('/login');
    }

    public function test_guest_cannot_run_chunk(): void
    {
        $this->post('/sync/chunk')->assertRedirect('/login');
    }

    public function test_guest_cannot_run_verify(): void
    {
        $this->post('/sync/verify')->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // run — not configured
    // -----------------------------------------------------------------------

    public function test_run_returns_503_when_helloasso_not_configured(): void
    {
        // Ensure HelloAsso is NOT configured
        config(['services.helloasso.client_id' => null]);
        config(['services.helloasso.client_secret' => null]);

        $this->actingAs($this->user)
            ->postJson('/sync')
            ->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    // -----------------------------------------------------------------------
    // run — configured
    // -----------------------------------------------------------------------

    public function test_run_returns_json_when_configured(): void
    {
        $mockHelloAsso = $this->createMock(HelloAssoService::class);
        $mockHelloAsso->method('fetchPage')->willReturn(['items' => [], 'next_cursor' => null]);

        $this->mock(HelloAssoService::class, function ($mock) use ($mockHelloAsso) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('fetchPage')->andReturn(['items' => [], 'next_cursor' => null]);
        });

        $mockService = $this->createMock(SyncService::class);
        $mockService->method('startSync')->willReturn([
            'log_id'  => 1,
            'new'     => 0,
            'updated' => 0,
            'skipped' => 0,
            'done'    => true,
        ]);

        $this->app->instance(SyncService::class, $mockService);
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $this->actingAs($this->user)
            ->postJson('/sync')
            ->assertOk()
            ->assertJsonStructure(['log_id']);
    }

    // -----------------------------------------------------------------------
    // chunk — validation
    // -----------------------------------------------------------------------

    public function test_chunk_returns_422_without_log_id(): void
    {
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $this->actingAs($this->user)
            ->postJson('/sync/chunk', [])
            ->assertStatus(422);
    }

    public function test_chunk_returns_422_with_nonexistent_log_id(): void
    {
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $this->actingAs($this->user)
            ->postJson('/sync/chunk', ['log_id' => 9999])
            ->assertStatus(422);
    }

    public function test_chunk_processes_running_log(): void
    {
        $log = SyncLog::create([
            'started_at'         => now(),
            'status'             => 'running',
            'new_records'        => 0,
            'updated_records'    => 0,
            'continuation_token' => 'some_token',
        ]);

        $mockService = $this->createMock(SyncService::class);
        $mockService->method('continueSync')->willReturn([
            'done'    => true,
            'new'     => 0,
            'updated' => 0,
        ]);

        $this->app->instance(SyncService::class, $mockService);
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $this->actingAs($this->user)
            ->postJson('/sync/chunk', ['log_id' => $log->id])
            ->assertOk()
            ->assertJsonFragment(['done' => true]);
    }

    // -----------------------------------------------------------------------
    // verify
    // -----------------------------------------------------------------------

    public function test_verify_returns_503_when_not_configured(): void
    {
        config(['services.helloasso.client_id' => null]);

        $this->actingAs($this->user)
            ->postJson('/sync/verify')
            ->assertStatus(503);
    }

    public function test_verify_returns_results_with_cursor(): void
    {
        $mockService = $this->createMock(SyncService::class);
        $mockService->method('verifyPage')->willReturn(['items' => [], 'next_cursor' => null]);
        $mockService->method('checkMissing')->willReturn(['missing' => [], 'deleted' => []]);

        $this->app->instance(SyncService::class, $mockService);
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $this->actingAs($this->user)
            ->postJson('/sync/verify', ['cursor' => 'abc123'])
            ->assertOk()
            ->assertJsonStructure(['done', 'next_cursor', 'missing', 'deleted', 'checked']);
    }

    public function test_verify_returns_422_for_invalid_cursor(): void
    {
        $this->app->instance(HelloAssoService::class, $this->makeConfiguredHelloAsso());

        $longCursor = str_repeat('x', 501);

        $this->actingAs($this->user)
            ->postJson('/sync/verify', ['cursor' => $longCursor])
            ->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeConfiguredHelloAsso(): HelloAssoService
    {
        $mock = $this->createMock(HelloAssoService::class);
        // Static method — use config instead for isConfigured()
        config(['services.helloasso.client_id' => 'test-id']);
        config(['services.helloasso.client_secret' => 'test-secret']);
        return $mock;
    }
}
