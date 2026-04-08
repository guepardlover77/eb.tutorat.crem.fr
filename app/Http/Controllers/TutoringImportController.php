<?php
// app/Http/Controllers/TutoringImportController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TutoringMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TutoringImportController extends Controller
{
    public function index(): View
    {
        $count      = TutoringMember::count();
        $lastImport = TutoringMember::max('created_at');

        return view('admin.tutoring-import', compact('count', 'lastImport'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'excel' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $path        = $request->file('excel')->getPathname();
            $spreadsheet = IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, false, false, false);

            if (empty($rows)) {
                return redirect()->route('admin.tutoring-import')
                    ->with('error', 'Le fichier est vide.');
            }

            $headers = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);

            $cremCol      = $this->findColumnIndex($headers, 'crem');
            $firstNameCol = $this->findColumnIndex($headers, 'prénom') ?? $this->findColumnIndex($headers, 'prenom');
            $lastNameCol  = $this->findColumnIndex($headers, 'nom');

            if ($cremCol === null) {
                return redirect()->route('admin.tutoring-import')
                    ->with('error', 'Aucune colonne CREM détectée dans le fichier. Vérifiez les en-têtes.');
            }

            $members = [];
            $now     = now();

            foreach (array_slice($rows, 1) as $row) {
                $crem = trim((string) ($row[$cremCol] ?? ''));
                if ($crem === '') {
                    continue;
                }

                $members[] = [
                    'crem_number' => $crem,
                    'first_name'  => $firstNameCol !== null ? trim((string) ($row[$firstNameCol] ?? '')) ?: null : null,
                    'last_name'   => $lastNameCol !== null ? trim((string) ($row[$lastNameCol] ?? '')) ?: null : null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            DB::transaction(function () use ($members): void {
                TutoringMember::query()->delete();
                if (!empty($members)) {
                    TutoringMember::insert($members);
                }
            });

            return redirect()->route('admin.tutoring-import')
                ->with('success', count($members) . ' membres importés avec succès.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.tutoring-import')
                ->with('error', 'Impossible de lire le fichier Excel. Assurez-vous qu\'il n\'est pas corrompu ou protégé par un mot de passe.');
        }
    }

    private function findColumnIndex(array $headers, string $needle): ?int
    {
        foreach ($headers as $index => $header) {
            if (str_contains($header, $needle)) {
                // Avoid matching "prénom" when looking for "nom"
                if ($needle === 'nom' && (str_contains($header, 'prénom') || str_contains($header, 'prenom'))) {
                    continue;
                }
                return $index;
            }
        }

        return null;
    }
}
