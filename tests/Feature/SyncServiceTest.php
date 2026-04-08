<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Student;
use App\Models\SyncLog;
use App\Services\HelloAssoService;
use App\Services\SyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SyncServiceTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function mockHelloAsso(array $items = [], ?string $nextCursor = null): HelloAssoService
    {
        $mock = $this->createMock(HelloAssoService::class);
        $mock->method('fetchPage')->willReturn([
            'items' => $items,
            'next_cursor' => $nextCursor,
        ]);

        return $mock;
    }

    private function makeItem(array $overrides = []): array
    {
        static $id = 100;

        return array_merge([
            'id' => ++$id,
            'state' => 'Processed',
            'name' => 'LAS 1 - INSCRITS au Tutorat',
            'order' => ['id' => 1],
            'payer' => ['email' => "user{$id}@test.com"],
            'user' => ['firstName' => 'Test', 'lastName' => 'User'],
            'customFields' => [
                ['name' => 'Numéro CREM', 'type' => 'Number', 'answer' => '1000'.$id],
            ],
            'options' => [],
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // startSync
    // -----------------------------------------------------------------------

    public function test_start_sync_creates_sync_log(): void
    {
        $mock = $this->mockHelloAsso([$this->makeItem()]);
        $service = new SyncService($mock);

        $result = $service->startSync();

        $this->assertDatabaseCount('sync_logs', 1);
        $this->assertArrayHasKey('log_id', $result);
    }

    public function test_start_sync_inserts_new_students(): void
    {
        $item = $this->makeItem();
        $mock = $this->mockHelloAsso([$item]);
        $service = new SyncService($mock);

        $service->startSync();

        $this->assertDatabaseHas('students', [
            'helloasso_item_id' => $item['id'],
            'first_name' => 'Test',
        ]);
    }

    public function test_start_sync_marks_log_success_when_done(): void
    {
        $mock = $this->mockHelloAsso([$this->makeItem()], null);
        $service = new SyncService($mock);

        $result = $service->startSync();

        $log = SyncLog::find($result['log_id']);
        $this->assertSame('success', $log->status);
        $this->assertNotNull($log->finished_at);
    }

    public function test_start_sync_throws_when_lock_held(): void
    {
        Cache::lock('sync_helloasso', 300)->get();

        $mock = $this->mockHelloAsso();
        $service = new SyncService($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('synchronisation est déjà en cours');

        $service->startSync();
    }

    public function test_start_sync_skips_la_rochelle_7xxx(): void
    {
        $item = $this->makeItem([
            'customFields' => [
                ['name' => 'Numéro CREM', 'type' => 'Number', 'answer' => '70001'],
            ],
        ]);
        $mock = $this->mockHelloAsso([$item]);
        $service = new SyncService($mock);

        $service->startSync();

        $this->assertDatabaseMissing('students', ['crem_number' => '70001']);
    }

    public function test_start_sync_skips_non_processed_items(): void
    {
        $item = $this->makeItem(['state' => 'Pending']);
        $mock = $this->mockHelloAsso([$item]);
        $service = new SyncService($mock);

        $service->startSync();

        $this->assertDatabaseCount('students', 0);
    }

    public function test_start_sync_updates_existing_student(): void
    {
        $item = $this->makeItem();
        Student::create([
            'helloasso_item_id' => $item['id'],
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@test.com',
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
            'synced_at' => now()->subDay(),
        ]);

        $updatedItem = array_merge($item, [
            'user' => ['firstName' => 'New', 'lastName' => 'Name'],
            'payer' => ['email' => 'new@test.com'],
        ]);

        $mock = $this->mockHelloAsso([$updatedItem]);
        $service = new SyncService($mock);
        $result = $service->startSync();

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('students', [
            'helloasso_item_id' => $item['id'],
            'first_name' => 'New',
        ]);
    }

    public function test_start_sync_respects_manual_edit_flag(): void
    {
        $item = $this->makeItem();
        Student::create([
            'helloasso_item_id' => $item['id'],
            'first_name' => 'ManuallyEdited',
            'last_name' => 'User',
            'email' => 'manual@test.com',
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
            'is_manually_edited' => true,
            'synced_at' => now()->subDay(),
        ]);

        $mock = $this->mockHelloAsso([$item]);
        $service = new SyncService($mock);
        $service->startSync();

        // Should not overwrite manually edited fields
        $this->assertDatabaseHas('students', ['first_name' => 'ManuallyEdited']);
    }

    public function test_start_sync_does_not_restore_soft_deleted_student(): void
    {
        $item = $this->makeItem();
        $student = Student::create([
            'helloasso_item_id' => $item['id'],
            'first_name' => 'Deleted',
            'last_name' => 'Student',
            'email' => 'deleted@test.com',
            'tier_name' => 'LAS 1 - INSCRITS au Tutorat',
            'is_excluded' => false,
        ]);
        $student->delete();

        $mock = $this->mockHelloAsso([$item]);
        $service = new SyncService($mock);
        $service->startSync();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    // -----------------------------------------------------------------------
    // continueSync
    // -----------------------------------------------------------------------

    public function test_continue_sync_processes_next_page(): void
    {
        $mock = $this->mockHelloAsso([$this->makeItem()], null);
        $service = new SyncService($mock);

        $log = SyncLog::create([
            'started_at' => now(),
            'status' => 'running',
            'new_records' => 0,
            'updated_records' => 0,
            'continuation_token' => 'token_page2',
        ]);

        $result = $service->continueSync($log->id);

        $this->assertTrue($result['done']);
    }

    public function test_continue_sync_fails_if_log_not_running(): void
    {
        $log = SyncLog::create([
            'started_at' => now(),
            'status' => 'success',
            'new_records' => 0,
            'updated_records' => 0,
        ]);

        $mock = $this->mockHelloAsso();
        $service = new SyncService($mock);

        $this->expectException(ModelNotFoundException::class);
        $service->continueSync($log->id);
    }

    // -----------------------------------------------------------------------
    // verifyPage / checkMissing
    // -----------------------------------------------------------------------

    public function test_check_missing_returns_missing_students(): void
    {
        $mock = $this->mockHelloAsso();
        $service = new SyncService($mock);

        $haItems = [
            ['item_id' => 999, 'first_name' => 'Ghost', 'last_name' => 'User', 'email' => 'ghost@test.com', 'tier_name' => 'LAS 1', 'crem_number' => '10001'],
        ];

        $result = $service->checkMissing($haItems);

        $this->assertCount(1, $result['missing']);
        $this->assertCount(0, $result['deleted']);
    }

    public function test_check_missing_detects_soft_deleted(): void
    {
        $mock = $this->mockHelloAsso();
        $service = new SyncService($mock);

        $student = Student::create([
            'helloasso_item_id' => 888,
            'first_name' => 'Deleted',
            'last_name' => 'Person',
            'email' => 'del@test.com',
            'tier_name' => 'LAS 1',
            'is_excluded' => false,
        ]);
        $student->delete();

        $haItems = [
            ['item_id' => 888, 'first_name' => 'Deleted', 'last_name' => 'Person', 'email' => 'del@test.com', 'tier_name' => 'LAS 1', 'crem_number' => null],
        ];

        $result = $service->checkMissing($haItems);

        $this->assertCount(0, $result['missing']);
        $this->assertCount(1, $result['deleted']);
    }

    public function test_check_missing_returns_empty_when_all_present(): void
    {
        $mock = $this->mockHelloAsso();
        $service = new SyncService($mock);

        Student::create([
            'helloasso_item_id' => 777,
            'first_name' => 'Present',
            'last_name' => 'User',
            'email' => 'present@test.com',
            'tier_name' => 'LAS 1',
            'is_excluded' => false,
        ]);

        $haItems = [
            ['item_id' => 777, 'first_name' => 'Present', 'last_name' => 'User', 'email' => 'present@test.com', 'tier_name' => 'LAS 1', 'crem_number' => null],
        ];

        $result = $service->checkMissing($haItems);

        $this->assertCount(0, $result['missing']);
        $this->assertCount(0, $result['deleted']);
    }
}
