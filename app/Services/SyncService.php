<?php

namespace App\Services;

use App\Constants\StudentConstants;
use App\Models\Student;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncService
{
    public function __construct(private readonly HelloAssoService $helloAsso) {}

    public function startSync(): array
    {
        $lock = Cache::lock('sync_helloasso', 300);

        if (! $lock->get()) {
            throw new \RuntimeException('Une synchronisation est déjà en cours.');
        }

        try {
            // Abandon any stale running sync older than 10 minutes
            SyncLog::where('status', 'running')
                ->where('started_at', '<', now()->subMinutes(10))
                ->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Timeout']);

            $log = SyncLog::create([
                'started_at' => now(),
                'status' => 'running',
                'new_records' => 0,
                'updated_records' => 0,
            ]);

            $result = $this->processPage($log, null);

            if ($result['done']) {
                $lock->release();
            }

            return $result;
        } catch (\Throwable $e) {
            $lock->release();
            throw $e;
        }
    }

    public function continueSync(int $logId): array
    {
        $log = SyncLog::where('id', $logId)->where('status', 'running')->firstOrFail();

        $result = $this->processPage($log, $log->continuation_token);

        if ($result['done']) {
            Cache::lock('sync_helloasso')->forceRelease();
        }

        return $result;
    }

    private function processPage(SyncLog $log, ?string $cursor): array
    {
        try {
            ['items' => $items, 'next_cursor' => $nextCursor] = $this->helloAsso->fetchPage($cursor);

            [$new, $updated] = DB::transaction(fn () => $this->processItems($items));

            $totalNew = ($log->new_records ?? 0) + $new;
            $totalUpdated = ($log->updated_records ?? 0) + $updated;
            $done = $nextCursor === null;

            if ($done) {
                Cache::forget('crem_error_count');
                $log->update([
                    'finished_at' => now(),
                    'status' => 'success',
                    'new_records' => $totalNew,
                    'updated_records' => $totalUpdated,
                    'continuation_token' => null,
                ]);
            } else {
                $log->update([
                    'new_records' => $totalNew,
                    'updated_records' => $totalUpdated,
                    'continuation_token' => $nextCursor,
                ]);
            }

            return [
                'log_id' => $log->id,
                'done' => $done,
                'new' => $totalNew,
                'updated' => $totalUpdated,
                'next_cursor' => $nextCursor,
            ];
        } catch (\Throwable $e) {
            $log->update([
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('HelloAsso sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function verifyPage(?string $cursor): array
    {
        ['items' => $items, 'next_cursor' => $nextCursor] = $this->helloAsso->fetchPage($cursor);

        $processedItems = array_filter(
            $items,
            fn ($item) => ($item['state'] ?? '') === 'Processed'
        );

        $results = [];

        foreach ($processedItems as $item) {
            $cremNumber = null;
            foreach ($item['customFields'] ?? [] as $field) {
                if (str_contains($field['name'] ?? '', 'CREM') && ($field['type'] ?? '') === 'Number') {
                    $cremNumber = $field['answer'] ?? null;
                }
            }
            $cremNumber = $cremNumber ? ltrim($cremNumber, '0') : null;

            // Skip La Rochelle (7xxx)
            if ($cremNumber && str_starts_with($cremNumber, '7')) {
                continue;
            }

            $results[] = [
                'item_id' => $item['id'],
                'first_name' => $item['user']['firstName'] ?? '',
                'last_name' => $item['user']['lastName'] ?? '',
                'email' => $item['payer']['email'] ?? $item['user']['email'] ?? '',
                'tier_name' => $item['name'] ?? '',
                'crem_number' => $cremNumber,
            ];
        }

        return [
            'items' => $results,
            'next_cursor' => $nextCursor,
        ];
    }

    public function checkMissing(array $helloAssoItems): array
    {
        $itemIds = array_column($helloAssoItems, 'item_id');

        $existing = Student::withTrashed()
            ->whereIn('helloasso_item_id', $itemIds)
            ->get()
            ->keyBy('helloasso_item_id');

        $missing = [];
        $deleted = [];

        foreach ($helloAssoItems as $ha) {
            $student = $existing->get($ha['item_id']);

            if (! $student) {
                $missing[] = $ha;
            } elseif ($student->trashed()) {
                $deleted[] = array_merge($ha, ['deleted_at' => $student->deleted_at->toDateTimeString()]);
            }
        }

        return ['missing' => $missing, 'deleted' => $deleted];
    }

    private function processItems(array $items): array
    {
        $new = 0;
        $updated = 0;

        $processedItems = array_filter(
            $items,
            fn ($item) => ($item['state'] ?? '') === 'Processed'
        );

        if (empty($processedItems)) {
            return [0, 0];
        }

        // Batch-load existing students (including soft-deleted) to avoid N+1
        $itemIds = array_column($processedItems, 'id');
        $existing = Student::withTrashed()
            ->whereIn('helloasso_item_id', $itemIds)
            ->get()
            ->keyBy('helloasso_item_id');

        $detectKeys = ['first_name', 'last_name', 'email', 'tier_name', 'crem_number', 'crem_photo_url', 'is_excluded', 'recovery_option'];
        $toCreate = [];
        $now = now();

        foreach ($processedItems as $item) {
            $cremNumber = null;
            $cremPhotoUrl = null;
            $recoveryOption = null;

            foreach ($item['customFields'] ?? [] as $field) {
                $fieldName = $field['name'] ?? '';
                if (str_contains($fieldName, 'CREM') && ($field['type'] ?? '') === 'Number') {
                    $cremNumber = $field['answer'] ?? null;
                } elseif (str_contains($fieldName, 'Photo') && ($field['type'] ?? '') === 'File') {
                    $cremPhotoUrl = $field['answer'] ?? null;
                }
            }

            foreach ($item['options'] ?? [] as $option) {
                $optName = trim($option['name'] ?? '');
                if (in_array($optName, StudentConstants::RECOVERY_OPTIONS)) {
                    $recoveryOption = $optName;
                    break;
                }
            }

            $tierName = $item['name'] ?? '';
            $isExcluded = $tierName === StudentConstants::EXCLUDED_TIER;
            $email = $item['payer']['email'] ?? $item['user']['email'] ?? '';

            $attributes = [
                'helloasso_order_id' => $item['order']['id'] ?? null,
                'first_name' => $item['user']['firstName'] ?? '',
                'last_name' => $item['user']['lastName'] ?? '',
                'email' => $email,
                'tier_name' => $tierName,
                'crem_number' => $cremNumber ? ltrim($cremNumber, '0') : null,
                'crem_photo_url' => $cremPhotoUrl,
                'is_excluded' => $isExcluded,
                'recovery_option' => $isExcluded ? $recoveryOption : null,
                'synced_at' => $now,
            ];

            $student = $existing->get($item['id']);

            if ($student) {
                // Respect manual deletions — never re-activate a soft-deleted student
                if ($student->trashed()) {
                    continue;
                }

                // Respect manual edits — don't overwrite fields changed in the dashboard
                if ($student->is_manually_edited) {
                    $student->update(['synced_at' => $now]);

                    continue;
                }

                // Preserve auto-assigned 8xxx CREM numbers
                if ($student->crem_number && str_starts_with($student->crem_number, '8') && ! $attributes['crem_number']) {
                    $attributes['crem_number'] = $student->crem_number;
                }

                $changed = array_filter(
                    array_intersect_key($attributes, array_flip($detectKeys)),
                    fn ($value, $key) => (string) $student->$key !== (string) $value,
                    ARRAY_FILTER_USE_BOTH
                );

                if (! empty($changed)) {
                    $student->update($attributes);
                    $updated++;
                } else {
                    $student->update(['synced_at' => $now]);
                }
            } else {
                // Skip La Rochelle students (CREM 7xxx)
                if ($attributes['crem_number'] && str_starts_with($attributes['crem_number'], '7')) {
                    continue;
                }

                $toCreate[] = array_merge($attributes, [
                    'helloasso_item_id' => $item['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $new++;
            }
        }

        if (! empty($toCreate)) {
            Student::insert($toCreate);
        }

        return [$new, $updated];
    }
}
