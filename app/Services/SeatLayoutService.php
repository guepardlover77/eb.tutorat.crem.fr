<?php

namespace App\Services;

use App\Models\Amphitheater;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SeatLayoutService
{
    private const EXCEL_FILE = 'LAS - Plan des amphis - 25 26.xlsx';

    private const SHEET_MAPPING = [
        'COME BAS' => 'Côme Bas',
        'COME HAUT' => 'Côme Haut',
        'DEBRE GAUCHE' => 'Debré gauche',
        'DEBRE DROIT' => 'Debré droit',
        'DEBRE HAUT' => 'Debré haut',
        'RAMBAUD' => 'Rambaud',
        'TOURETTE' => 'Tourette',
        'BEAUCHAMP' => 'Beauchamps',
        'LEFEVRE' => 'Lefèvre',
    ];

    public function importAll(): array
    {
        $path = base_path(self::EXCEL_FILE);

        // PhpSpreadsheet emits harmless XML warnings for empty relationship entries.
        // Temporarily restore PHP's default handler so they don't become exceptions.
        $previous = set_error_handler(null);
        $spreadsheet = IOFactory::load($path);
        set_error_handler($previous);
        $results = [];

        foreach (self::SHEET_MAPPING as $sheetName => $amphiName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $results[$amphiName] = ['status' => 'sheet_not_found'];

                continue;
            }

            $seats = $this->extractSeats($sheet);

            Amphitheater::where('name', $amphiName)
                ->update(['seat_layout' => $seats]);

            $results[$amphiName] = ['status' => 'ok', 'count' => count($seats)];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $results;
    }

    private function extractSeats(Worksheet $sheet): array
    {
        $seats = [];

        $maxRow = min($sheet->getHighestRow(), 200);
        $maxCol = min(Coordinate::columnIndexFromString($sheet->getHighestColumn()), 50);
        $maxColLetter = Coordinate::stringFromColumnIndex($maxCol);

        foreach ($sheet->getRowIterator(1, $maxRow) as $row) {
            foreach ($row->getCellIterator('A', $maxColLetter) as $cell) {
                if (! $this->isOrangeCell($sheet, $cell->getCoordinate())) {
                    continue;
                }
                $val = $cell->getCalculatedValue();
                if (is_numeric($val) && (int) $val > 0) {
                    $seats[] = (int) $val;
                } elseif (preg_match('/^table\s*(\d+)$/i', trim((string) $val), $m)) {
                    $seats[] = 'Table '.$m[1];
                }
            }
        }

        $seats = array_unique($seats);
        usort($seats, function ($a, $b) {
            $aIsString = is_string($a);
            $bIsString = is_string($b);
            if ($aIsString && $bIsString) {
                return strnatcmp($a, $b);
            }
            if ($aIsString) {
                return -1;
            }
            if ($bIsString) {
                return 1;
            }

            return $a <=> $b;
        });

        return array_values($seats);
    }

    private function isOrangeCell(Worksheet $sheet, string $coordinate): bool
    {
        $fill = $sheet->getStyle($coordinate)->getFill();
        if ($fill->getFillType() !== Fill::FILL_SOLID) {
            return false;
        }
        $rgb = $fill->getStartColor()->getRGB();
        $r = hexdec(substr($rgb, 0, 2));
        $g = hexdec(substr($rgb, 2, 2));
        $b = hexdec(substr($rgb, 4, 2));

        // Orange: strong red, moderate green (less than red), low blue
        return $r >= 180 && $g >= 60 && $g < $r && $b < 100;
    }
}
