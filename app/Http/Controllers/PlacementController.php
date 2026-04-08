<?php

namespace App\Http\Controllers;

use App\Services\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlacementController extends Controller
{
    public function __construct(private readonly PlacementService $placement) {}

    public function run(): RedirectResponse
    {
        try {
            $result = $this->placement->run();

            return redirect('/')->with('success',
                "Placement effectué : {$result['placed']} placés, {$result['unplaced']} non placés, {$result['errors']} erreurs."
            );
        } catch (\Throwable $e) {
            Log::error('Placement failed', ['exception' => $e]);

            return redirect('/')->with('error', 'Erreur lors du placement. Consultez les logs.');
        }
    }

    public function assignNumbers(): RedirectResponse
    {
        try {
            $count = $this->placement->assignAutoNumbers();

            return redirect('/')->with('success', "{$count} numéro(s) CREM 8xxx attribué(s).");
        } catch (\Throwable $e) {
            Log::error('Assign numbers failed', ['exception' => $e]);

            return redirect('/')->with('error', 'Erreur lors de l\'attribution des numéros. Consultez les logs.');
        }
    }

    public function reset(Request $request): RedirectResponse
    {
        $includeManual = $request->boolean('include_manual', true);
        $this->placement->reset($includeManual);
        $msg = $includeManual ? 'Tous les placements réinitialisés.' : 'Placements automatiques réinitialisés (placements manuels conservés).';

        return redirect('/')->with('success', $msg);
    }
}
