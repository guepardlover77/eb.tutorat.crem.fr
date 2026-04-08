<?php

namespace App\Services;

use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlacementService
{

    private const LAS1_TIERS = [
        'LAS 1 - INSCRITS au Tutorat',
        'LAS 1 - INSCRITS AU CREM SANS le Tutorat',
        'LAS 1 - NON INSCRITS au Tutorat',
        'UE3 + UE4 (LAS 1)',
    ];

    private const LAS2_TIERS = [
        'LAS 2/3 - INSCRITS au Tutorat',
        'LAS 2/3 - INSCRITS AU CREM SANS le Tutorat',
        'LAS 2/3 - NON INSCRITS au Tutorat',
    ];

    // Members = have CREM card (Tutorat OR CREM-sans-Tutorat)
    private const MEMBER_TIERS = [
        'LAS 1 - INSCRITS au Tutorat',
        'LAS 1 - INSCRITS AU CREM SANS le Tutorat',
        'LAS 2/3 - INSCRITS au Tutorat',
        'LAS 2/3 - INSCRITS AU CREM SANS le Tutorat',
        'UE3 + UE4 (LAS 1)',
    ];

    private function autoPlacementQuery()
    {
        return Student::where('is_excluded', false)
            ->where('is_manually_placed', false)
            ->where(fn($q) => $q->whereNull('crem_number')->orWhere('crem_number', 'NOT LIKE', '7%'));
    }

    public function run(): array
    {
        return DB::transaction(function () {
            // Reset only auto-placements; keep manual overrides intact
            Student::where('is_manually_placed', false)
                ->update(['amphitheater_id' => null, 'seat_number' => null]);

            $students = $this->autoPlacementQuery()->get();

            // Step 1: detect errors (batch updates)
            $this->detectErrorsBatch($students);

            // Reload after error updates (error flags changed)
            $students = $this->autoPlacementQuery()->get();

            // Step 2: deduplicate by person (email) — keep best record per email
            $students = $this->deduplicateByEmail($students);

            // Step 3: build groups
            $groupDebre   = $this->buildDebreGroup($students);
            $groupCome    = $this->buildComeGroup($students);
            $groupBeauch  = $this->buildBeauchGroup($students);
            $groupRambaud = $this->buildRambaudGroup($students);

            // Step 3: fill amphitheaters in priority order
            $this->fillAmphis(['Debré gauche', 'Debré droit', 'Debré haut'], $groupDebre);

            $overflowCome = $this->fillAmphis(['Côme Bas', 'Côme Haut'], $groupCome);

            // Beauchamps: LAS 2/3 non-members first, then Come overflow
            $beauchQueue  = $groupBeauch->concat($overflowCome);
            $overflowBeauch = $this->fillAmphis(['Beauchamps'], $beauchQueue);

            $overflowRambaud = $this->fillAmphis(['Rambaud'], $groupRambaud);

            // Tourette: mix LAS 2/3 overflow + LAS 1 overflow
            $tourQueue = $overflowBeauch->concat($overflowRambaud);
            $overflowTourette = $this->fillAmphis(['Tourette'], $tourQueue);

            // Lefèvre: final overflow
            $this->fillAmphis(['Lefèvre'], $overflowTourette);

            $this->assignSeats();

            Cache::forget('crem_error_count');

            return [
                'placed'   => Student::whereNotNull('amphitheater_id')->count(),
                'unplaced' => Student::where('is_excluded', false)->whereNull('amphitheater_id')->count(),
                'errors'   => Student::where('has_error', true)->count(),
            ];
        });
    }

    private function assignSeats(): void
    {
        $amphis = Amphitheater::whereNotNull('seat_layout')
            ->with(['students' => fn($q) => $q->orderByRaw("CAST(crem_number AS INTEGER) ASC")])
            ->get();

        foreach ($amphis as $amphi) {
            $allSeats = $amphi->seat_layout;

            $reservedSeats = $amphi->students
                ->where('is_manually_placed', true)
                ->whereNotNull('seat_number')
                ->pluck('seat_number')
                ->all();

            $available = array_values(array_diff($allSeats, $reservedSeats));

            $nonManual = $amphi->students->where('is_manually_placed', false)->values();

            if ($nonManual->isEmpty()) {
                continue;
            }

            // Batch update via CASE/WHEN instead of per-student update
            $cases = [];
            $ids = [];
            foreach ($nonManual as $i => $student) {
                $ids[] = $student->id;
                $seat = $available[$i] ?? null;
                $seatSql = $seat === null ? 'NULL' : DB::getPdo()->quote($seat);
                $cases[] = "WHEN {$student->id} THEN {$seatSql}";
            }

            $caseSql = implode(' ', $cases);
            $idList = implode(',', $ids);
            DB::statement("UPDATE students SET seat_number = CASE id {$caseSql} END WHERE id IN ({$idList})");
        }
    }

    public function assignAutoNumbers(): int
    {
        return DB::transaction(function () {
            $maxAuto = Student::where('crem_number', 'LIKE', '8%')
                ->selectRaw('MAX(CAST(crem_number AS INTEGER)) as max_num')
                ->value('max_num');

            $next = max(8001, ($maxAuto ?? 0) + 1);

            $students = Student::whereNull('crem_number')
                ->where('is_excluded', false)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            if ($students->isEmpty()) {
                return 0;
            }

            $cases = [];
            $ids = [];
            foreach ($students as $student) {
                $ids[] = $student->id;
                $cases[] = "WHEN {$student->id} THEN '{$next}'";
                $next++;
            }

            $caseSql = implode(' ', $cases);
            $idList = implode(',', $ids);
            DB::statement("UPDATE students SET crem_number = CASE id {$caseSql} END WHERE id IN ({$idList})");

            return $students->count();
        });
    }

    public function reset(bool $includeManual = true): void
    {
        if ($includeManual) {
            Student::query()->update(['amphitheater_id' => null, 'seat_number' => null, 'is_manually_placed' => false]);
        } else {
            Student::where('is_manually_placed', false)
                ->update(['amphitheater_id' => null, 'seat_number' => null]);
        }
    }

    private function detectErrorsBatch(Collection $students): void
    {
        $err1Ids = $students
            ->filter(fn(Student $s) => $s->cremPrefix() === '1' && in_array($s->tier_name, self::LAS2_TIERS))
            ->pluck('id');

        $err9Ids = $students
            ->filter(fn(Student $s) => $s->cremPrefix() === '9' && in_array($s->tier_name, self::LAS1_TIERS))
            ->pluck('id');

        // Detect duplicate emails (appearing more than once)
        $duplicateEmails = $students
            ->filter(fn(Student $s) => $s->email !== null)
            ->groupBy(fn(Student $s) => strtolower(trim($s->email)))
            ->filter(fn($group) => $group->count() > 1)
            ->keys();

        $dupIds = $students
            ->filter(fn(Student $s) => $duplicateEmails->contains(strtolower(trim($s->email ?? ''))))
            ->pluck('id');

        $errorIds = $err1Ids->merge($err9Ids)->merge($dupIds)->unique();
        $okIds    = $students->pluck('id')->diff($errorIds);

        if ($err1Ids->isNotEmpty()) {
            Student::whereIn('id', $err1Ids)->update([
                'has_error'     => true,
                'error_message' => 'Numéro CREM commençant par 1 incompatible avec le tarif LAS 2/3',
            ]);
        }
        if ($err9Ids->isNotEmpty()) {
            Student::whereIn('id', $err9Ids)->update([
                'has_error'     => true,
                'error_message' => 'Numéro CREM commençant par 9 incompatible avec le tarif LAS 1',
            ]);
        }
        if ($dupIds->isNotEmpty()) {
            // Only mark as duplicate those not already flagged for prefix error
            $pureUniqueIds = $dupIds->diff($err1Ids)->diff($err9Ids);
            if ($pureUniqueIds->isNotEmpty()) {
                Student::whereIn('id', $pureUniqueIds)->update([
                    'has_error'     => true,
                    'error_message' => 'Adresse email en doublon',
                ]);
            }
        }
        if ($okIds->isNotEmpty()) {
            Student::whereIn('id', $okIds)->update(['has_error' => false, 'error_message' => null]);
        }
    }

    private function deduplicateByEmail(Collection $students): Collection
    {
        // Emails already used by manually placed students — auto students with same email are duplicates
        $manualEmails = Student::where('is_manually_placed', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->unique();

        $excludedIds = collect();

        // Exclude auto students whose email matches a manually placed student
        foreach ($students as $s) {
            if ($s->email && $manualEmails->contains(strtolower(trim($s->email)))) {
                $excludedIds->push($s->id);
                Student::where('id', $s->id)->update([
                    'has_error'     => true,
                    'error_message' => 'Doublon email — déjà placé manuellement',
                ]);
            }
        }

        $remaining = $students->reject(fn(Student $s) => $excludedIds->contains($s->id));

        $groups = $remaining
            ->filter(fn(Student $s) => $s->email !== null && $s->email !== '')
            ->groupBy(fn(Student $s) => strtolower(trim($s->email)));

        foreach ($groups as $email => $group) {
            if ($group->count() <= 1) {
                continue;
            }

            // If all students in the group have different non-null CREM numbers,
            // they are distinct people (e.g., siblings with same parent email)
            $cremNumbers = $group
                ->filter(fn(Student $s) => $s->crem_number !== null)
                ->pluck('crem_number')
                ->unique();

            if ($cremNumbers->count() === $group->count() && $cremNumbers->count() > 1) {
                continue;
            }

            // Pick the best record to keep:
            // 1. Adherent CREM (1xxx, 9xxx) > auto 8xxx > no CREM
            // 2. Non-excluded over excluded
            // 3. Highest ID (most recent) as tiebreaker
            $sorted = $group->sortByDesc(function (Student $s) {
                $cremScore = match (true) {
                    $s->crem_number !== null && !str_starts_with($s->crem_number, '8') => 2,
                    $s->crem_number !== null => 1,
                    default => 0,
                };
                $excludedScore = $s->is_excluded ? 0 : 1;
                return sprintf('%d_%d_%010d', $excludedScore, $cremScore, $s->id);
            });

            $kept = $sorted->first();
            $duplicates = $sorted->slice(1);

            $dupIds = $duplicates->pluck('id');
            $excludedIds = $excludedIds->merge($dupIds);

            $keptName = strtoupper($kept->last_name) . ' ' . $kept->first_name;
            Student::whereIn('id', $dupIds)->update([
                'has_error'     => true,
                'error_message' => "Doublon email — exclu du placement (retenu : {$keptName}, CREM {$kept->crem_number})",
            ]);
        }

        return $students->reject(fn(Student $s) => $excludedIds->contains($s->id))->values();
    }

    // Debré: All LAS1 members (Tutorat or CREM-sans-Tutorat), sorted CREM 1xxx first then 7xxx then others
    private function buildDebreGroup(Collection $students): Collection
    {
        $memberLas1 = [
            'LAS 1 - INSCRITS au Tutorat',
            'LAS 1 - INSCRITS AU CREM SANS le Tutorat',
            'UE3 + UE4 (LAS 1)',
        ];

        return $students
            ->filter(fn(Student $s) => in_array($s->tier_name, $memberLas1))
            ->sortBy(function (Student $s) {
                $prefix = $s->cremPrefix();
                $order  = match ($prefix) {
                    '1'   => 0,
                    '7'   => 1,
                    null  => 3,
                    default => 2,
                };
                return sprintf('%d_%06d', $order, (int) $s->crem_number);
            })
            ->values();
    }

    // Côme: LAS2/3 members (Tutorat or CREM-sans-Tutorat), CREM NOT starting with 1
    private function buildComeGroup(Collection $students): Collection
    {
        return $students
            ->filter(function (Student $s) {
                $isMemberLas2 = in_array($s->tier_name, [
                    'LAS 2/3 - INSCRITS au Tutorat',
                    'LAS 2/3 - INSCRITS AU CREM SANS le Tutorat',
                ]);
                $prefix = $s->cremPrefix();
                // Exclude errors (CREM 1xxx with LAS2 tariff - already flagged)
                return $isMemberLas2 && $prefix !== '1';
            })
            ->sortBy(fn(Student $s) => $s->crem_number ? (int) $s->crem_number : PHP_INT_MAX)
            ->values();
    }

    // Beauchamps: LAS2/3 non-members
    private function buildBeauchGroup(Collection $students): Collection
    {
        return $students
            ->filter(fn(Student $s) => $s->tier_name === 'LAS 2/3 - NON INSCRITS au Tutorat')
            ->sortBy(fn(Student $s) => $s->crem_number ? (int) $s->crem_number : PHP_INT_MAX)
            ->values();
    }

    // Rambaud: LAS1 non-members
    private function buildRambaudGroup(Collection $students): Collection
    {
        return $students
            ->filter(fn(Student $s) => $s->tier_name === 'LAS 1 - NON INSCRITS au Tutorat')
            ->sortBy(fn(Student $s) => $s->crem_number ? (int) $s->crem_number : PHP_INT_MAX)
            ->values();
    }

    private function fillAmphis(array $amphiNames, Collection $students): Collection
    {
        $idx = 0;
        $queue = $students->values();

        $amphis = Amphitheater::whereIn('name', $amphiNames)
            ->withCount('students')
            ->get()
            ->keyBy('name');

        foreach ($amphiNames as $name) {
            $amphi = $amphis[$name] ?? null;
            if (!$amphi) continue;

            $currentCount = $amphi->students_count;
            $limit = $amphi->seat_layout ? count($amphi->seat_layout) : $amphi->capacity;

            $batchIds = [];
            while ($idx < $queue->count() && $currentCount < $limit) {
                $batchIds[] = $queue[$idx]->id;
                $idx++;
                $currentCount++;
            }

            if (!empty($batchIds)) {
                Student::whereIn('id', $batchIds)->update(['amphitheater_id' => $amphi->id]);
            }
        }

        return $queue->slice($idx)->values();
    }
}
