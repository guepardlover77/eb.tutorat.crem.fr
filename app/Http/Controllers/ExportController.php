<?php

namespace App\Http\Controllers;

use App\Exports\AmphitheaterExport;
use App\Exports\EmargementExport;
use App\Exports\StudentPlacementExport;
use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class ExportController extends Controller
{
    public function amphi(Amphitheater $amphi)
    {
        $filename = 'liste-'.Str::slug($amphi->name).'.xlsx';

        return Excel::download(new AmphitheaterExport($amphi), $filename);
    }

    public function emargement(Amphitheater $amphi)
    {
        $filename = 'emargement-'.Str::slug($amphi->name).'.xlsx';

        return Excel::download(new EmargementExport($amphi), $filename);
    }

    public function studentPlacementCheck(): JsonResponse
    {
        $students = Student::where('is_excluded', false)
            ->where(fn ($q) => $q->where('has_error', true)->orWhereNull('seat_number'))
            ->with('amphitheater')
            ->get()
            ->map(fn ($s) => [
                'crem' => $s->crem_number ?? '—',
                'nom' => strtoupper($s->last_name).' '.$s->first_name,
                'amphi' => $s->amphitheater?->name ?? 'Non assigné',
                'raison' => $s->has_error ? ($s->error_message ?? 'Erreur détectée') : 'Aucune place assignée',
            ]);

        return response()->json(['students' => $students]);
    }

    public function studentPlacement()
    {
        $filename = 'placement-etudiants-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new StudentPlacementExport, $filename);
    }

    public function recuperationNoOptionEmails()
    {
        $students = Student::where('is_excluded', true)
            ->whereNull('recovery_option')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['email', 'first_name', 'last_name']);

        $lines = $students->map(fn ($s) => $s->email)->filter()->implode("\n");

        return response($lines, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="emails-sans-option-'.now()->format('Y-m-d').'.txt"',
        ]);
    }

    public function allAmphis()
    {
        $zip = new ZipArchive;
        $zipPath = storage_path('app/temp-export-'.time().'.zip');

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return back()->with('error', 'Impossible de créer le fichier ZIP.');
        }

        try {
            foreach (Amphitheater::withCount('students as placed_count')->with('students')->orderBy('sort_order')->get() as $amphi) {
                if ($amphi->placed_count === 0) {
                    continue;
                }

                $slug = Str::slug($amphi->name);

                $zip->addFromString(
                    "listes/liste-{$slug}.xlsx",
                    Excel::raw(new AmphitheaterExport($amphi), \Maatwebsite\Excel\Excel::XLSX)
                );
                $zip->addFromString(
                    "emargements/emargement-{$slug}.xlsx",
                    Excel::raw(new EmargementExport($amphi), \Maatwebsite\Excel\Excel::XLSX)
                );
            }
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipPath);

            return back()->with('error', 'Erreur lors de la génération de l\'export : '.$e->getMessage());
        }

        $zip->close();

        return response()->download($zipPath, 'export-complet-examen-blanc.zip')->deleteFileAfterSend();
    }
}
