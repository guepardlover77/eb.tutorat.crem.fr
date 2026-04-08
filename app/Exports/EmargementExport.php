<?php

namespace App\Exports;

use App\Models\Amphitheater;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithRowFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class EmargementExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    private int $studentCount = 0;

    public function __construct(private readonly Amphitheater $amphitheater) {}

    public function collection()
    {
        $i = 1;
        $students = $this->amphitheater
            ->students()
            ->orderBySeat()
            ->get();

        $this->studentCount = $students->count();

        return $students->map(fn($s) => [
            'N°'        => $i++,
            'N° Place'  => $s->seat_number,
            'N° CREM'   => $s->crem_number ?? '—',
            'Nom'       => strtoupper($s->last_name),
            'Prénom'    => $s->first_name,
            'Signature' => '',
        ]);
    }

    public function headings(): array
    {
        return ['N°', 'N° Place', 'N° CREM', 'Nom', 'Prénom', 'Signature'];
    }

    public function title(): string
    {
        return 'Émargement - ' . $this->amphitheater->name;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->studentCount + 1;

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            ],
        ];

        // Alternating row colors + borders
        for ($row = 2; $row <= $lastRow; $row++) {
            $styles[$row] = [
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $row % 2 === 0 ? 'F0F4FF' : 'FFFFFF'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ];
        }

        // Make rows taller for signatures
        $sheet->getDefaultRowDimension()->setRowHeight(25);

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 10,
            'C' => 12,
            'D' => 22,
            'E' => 22,
            'F' => 30,
        ];
    }
}
