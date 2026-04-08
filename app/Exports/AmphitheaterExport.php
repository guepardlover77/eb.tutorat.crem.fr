<?php

namespace App\Exports;

use App\Models\Amphitheater;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AmphitheaterExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly Amphitheater $amphitheater) {}

    public function collection()
    {
        return $this->amphitheater
            ->students()
            ->orderBySeat()
            ->get()
            ->map(fn ($s) => [
                'N° Place' => $s->seat_number,
                'N° CREM' => $s->crem_number ?? '—',
                'Nom' => strtoupper($s->last_name),
                'Prénom' => $s->first_name,
                'Amphi' => $this->amphitheater->name,
                'Tarif' => $s->tier_name,
            ]);
    }

    public function headings(): array
    {
        return ['N° Place', 'N° CREM', 'Nom', 'Prénom', 'Amphi', 'Tarif'];
    }

    public function title(): string
    {
        return $this->amphitheater->name;
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
            'A' => 10,
            'B' => 12,
            'C' => 20,
            'D' => 20,
            'E' => 16,
            'F' => 35,
        ];
    }
}
