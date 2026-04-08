<?php

namespace App\Exports\Sheets;

use App\Models\Amphitheater;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AmphiPlacementSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    public function __construct(private readonly Amphitheater $amphitheater) {}

    public function collection()
    {
        return $this->amphitheater
            ->students()
            ->where('is_excluded', false)
            ->where('has_error', false)
            ->whereNotNull('seat_number')
            ->orderBySeat()
            ->get()
            ->map(fn ($s) => [
                'N° Place' => $s->seat_number,
                'N° CREM' => $s->crem_number ?? '—',
            ]);
    }

    public function headings(): array
    {
        return ['N° Place', 'N° CREM'];
    }

    public function title(): string
    {
        return $this->amphitheater->name;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 1);
                $sheet->mergeCells('A1:B1');
                $sheet->setCellValue('A1', 'Amphi : '.$this->amphitheater->name);

                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);

                $sheet->getStyle('A2:B2')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(22);
            },
        ];
    }
}
