<?php

namespace App\Exports\Sheets;

use App\Models\Amphitheater;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RecapPlacementSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function collection()
    {
        $amphiIds = Amphitheater::orderBy('sort_order')->pluck('id');

        return Student::where('is_excluded', false)
            ->where('has_error', false)
            ->whereNotNull('seat_number')
            ->whereNotNull('amphitheater_id')
            ->with('amphitheater')
            ->get()
            ->sortBy(fn($s) => $amphiIds->search($s->amphitheater_id))
            ->values()
            ->map(fn($s) => [
                'N° Place' => $s->seat_number,
                'N° CREM'  => $s->crem_number ?? '—',
                'Amphi'    => $s->amphitheater->name,
            ]);
    }

    public function headings(): array
    {
        return ['N° Place', 'N° CREM', 'Amphi'];
    }

    public function title(): string
    {
        return 'Récapitulatif';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 14,
            'C' => 20,
        ];
    }
}
