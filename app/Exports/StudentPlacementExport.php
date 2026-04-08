<?php

namespace App\Exports;

use App\Exports\Sheets\AmphiPlacementSheet;
use App\Exports\Sheets\RecapPlacementSheet;
use App\Models\Amphitheater;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StudentPlacementExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $sheets = [new RecapPlacementSheet()];

        $amphis = Amphitheater::withCount(['students as placed_count' => fn($q) => $q
            ->where('is_excluded', false)
            ->where('has_error', false)
            ->whereNotNull('seat_number'),
        ])->orderBy('sort_order')->get();

        foreach ($amphis as $amphi) {
            if ($amphi->placed_count > 0) {
                $sheets[] = new AmphiPlacementSheet($amphi);
            }
        }

        return $sheets;
    }
}
