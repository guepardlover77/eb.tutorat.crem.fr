<?php

namespace App\Http\Controllers;

use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    private const PLAN_IMAGES = [
        'Côme Bas'     => 'come-bas',
        'Côme Haut'    => 'come-haut',
        'Debré gauche' => 'debre-gauche',
        'Debré droit'  => 'debre-droit',
        'Debré haut'   => 'debre-haut',
        'Rambaud'      => 'rambaud',
        'Beauchamps'   => 'beauchamps',
        'Lefèvre'      => 'lefevre',
        'Tourette'     => 'tourette',
    ];

    public function placement()
    {
        $amphitheaters    = $this->loadAmphitheaters();
        $amphitheaterData = $this->buildAmphiData($amphitheaters);

        return view('public.placement', compact('amphitheaters', 'amphitheaterData'));
    }

    public function placementData()
    {
        $data = $this->buildAmphiData($this->loadAmphitheaters());
        $hash = md5($data->toJson());

        return response()->json(['hash' => $hash, 'amphitheaters' => $data]);
    }

    private function loadAmphitheaters()
    {
        return Amphitheater::orderBy('sort_order')
            ->with(['students' => fn($q) => $q
                ->where('is_excluded', false)
                ->whereNotNull('seat_number')
                ->where(fn($q2) => $q2->whereNull('crem_number')->orWhere('crem_number', 'NOT LIKE', '7%'))
                ->orderBySeat()
                ->select(['id', 'amphitheater_id', 'seat_number', 'crem_number'])
            ])
            ->get()
            ->filter(fn($a) => $a->students->isNotEmpty())
            ->map(fn($a) => tap($a, fn($a) => $a->plan_image = self::PLAN_IMAGES[$a->name] ?? null))
            ->values();
    }

    private function buildAmphiData($amphitheaters)
    {
        return $amphitheaters->map(fn($a) => [
            'name'     => $a->name,
            'students' => $a->students->map(fn($s) => [
                'seat' => $s->seat_number,
                'crem' => $s->crem_number ?? '',
            ])->values(),
        ])->values();
    }

    public function monNumero(Request $request)
    {
        $result = null;

        if ($request->isMethod('post')) {
            $request->validate(['email' => 'required|email']);

            $student = Student::where('email', $request->input('email'))->first();

            if (!$student) {
                $result = ['status' => 'not_found'];
            } elseif ($student->crem_number && !str_starts_with($student->crem_number, '8')) {
                $result = [
                    'status' => 'adherent',
                    'crem_number' => $student->crem_number,
                    'name' => $student->first_name . ' ' . strtoupper($student->last_name),
                ];
            } else {
                $result = [
                    'status' => 'auto',
                    'crem_number' => $student->crem_number,
                    'name' => $student->first_name . ' ' . strtoupper($student->last_name),
                ];
            }
        }

        return view('public.mon-numero', compact('result'));
    }
}
