<?php

namespace App\Http\Controllers;

use App\Models\Amphitheater;
use App\Models\Student;
use App\Models\SyncLog;
use App\Services\HelloAssoService;

class DashboardController extends Controller
{
    public function index()
    {
        $row = Student::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_excluded = 0 THEN 1 ELSE 0 END) as to_place,
            SUM(CASE WHEN is_excluded = 1 THEN 1 ELSE 0 END) as excluded,
            SUM(CASE WHEN amphitheater_id IS NOT NULL THEN 1 ELSE 0 END) as placed,
            SUM(CASE WHEN has_error = 1 THEN 1 ELSE 0 END) as errors,
            SUM(CASE WHEN is_excluded = 0 AND amphitheater_id IS NULL THEN 1 ELSE 0 END) as unplaced,
            SUM(CASE WHEN is_manually_placed = 1 THEN 1 ELSE 0 END) as manual
        ")->first();

        $stats = [
            'total'    => (int) ($row->total ?? 0),
            'to_place' => (int) ($row->to_place ?? 0),
            'excluded' => (int) ($row->excluded ?? 0),
            'placed'   => (int) ($row->placed ?? 0),
            'errors'   => (int) ($row->errors ?? 0),
            'unplaced' => (int) ($row->unplaced ?? 0),
            'manual'   => (int) ($row->manual ?? 0),
        ];

        $amphitheaters = Amphitheater::withCount('students as placed_count')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($a) => [
                'model'     => $a,
                'placed'    => $a->placed_count,
                'fill_rate' => $a->seatCount() > 0
                    ? round(($a->placed_count / $a->seatCount()) * 100, 1)
                    : 0,
            ]);

        $lastSync = SyncLog::latest()->first();
        $helloassoConfigured = HelloAssoService::isConfigured();

        return view('dashboard', compact('stats', 'amphitheaters', 'lastSync', 'helloassoConfigured'));
    }
}
