<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HelloAssoService;
use App\Services\TierResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class InscriptionController extends Controller
{
    public function __construct(private readonly TierResolverService $tierResolver) {}

    public function index(): View
    {
        return view('public.inscriptions', [
            'helloassoConfigured' => HelloAssoService::isConfigured(),
        ]);
    }

    public function checkTier(Request $request): RedirectResponse
    {
        if (! HelloAssoService::isConfigured()) {
            session()->flash('error', 'Les inscriptions ne sont pas disponibles pour le moment. Contactez l\'association.');

            return redirect()->route('inscriptions.index');
        }

        $validated = $request->validate([
            'crem_number' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'las_level' => ['nullable', 'string', 'in:las1,las2'],
        ]);

        $cremNumber = $validated['crem_number'] ?: null;
        $lasLevel = $validated['las_level'] ?? null;

        try {
            $result = $this->tierResolver->resolve($cremNumber, $lasLevel);
            $formSlug = config("services.helloasso.inscription_forms.{$result->tierKey}");

            if ($formSlug === null) {
                session()->flash('error', 'Ce formulaire d\'inscription n\'est pas encore disponible. Contactez l\'association.');

                return redirect()->route('inscriptions.index');
            }

            session()->flash('tier_key', $result->tierKey);
            session()->flash('tier_label', $result->tierLabel);
            session()->flash('form_slug', $formSlug);
        } catch (InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->route('inscriptions.index')->withInput();
        }

        return redirect()->route('inscriptions.index');
    }
}
