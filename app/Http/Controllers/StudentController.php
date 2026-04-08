<?php

namespace App\Http\Controllers;

use App\Constants\StudentConstants;
use App\Models\Amphitheater;
use App\Models\ManualPlacementLog;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with('amphitheater')->orderBy('last_name');

        if ($request->filled('amphi')) {
            $query->where('amphitheater_id', $request->amphi);
        }
        if ($request->filled('tier')) {
            $query->where('tier_name', $request->tier);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($q2) => $q2->where('last_name', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('crem_number', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
            );
        }
        if ($request->filled('status')) {
            match ($request->status) {
                'placed' => $query->whereNotNull('amphitheater_id'),
                'unplaced' => $query->where('is_excluded', false)->whereNull('amphitheater_id'),
                'excluded' => $query->where('is_excluded', true),
                'errors' => $query->where('has_error', true),
                default => null,
            };
        }

        $students = $query->paginate(50)->withQueryString();
        $amphitheaters = Amphitheater::orderBy('sort_order')->get();
        $tiers = Student::distinct()->orderBy('tier_name')->pluck('tier_name');

        return view('students.index', compact('students', 'amphitheaters', 'tiers'));
    }

    public function recuperation()
    {
        $all = Student::where('tier_name', StudentConstants::EXCLUDED_TIER)
            ->orderBy('last_name')
            ->get()
            ->groupBy('recovery_option');

        $groups = [];
        foreach (StudentConstants::RECOVERY_OPTIONS as $option) {
            $groups[$option] = $all->get($option, collect());
        }

        return view('students.recuperation', [
            'groups' => $groups,
            'noOption' => $all->get(null, collect()),
            'options' => StudentConstants::RECOVERY_OPTIONS,
        ]);
    }

    public function errors()
    {
        $students = Student::where('has_error', true)
            ->with('amphitheater')
            ->orderBy('last_name')
            ->get();

        return view('students.errors', compact('students'));
    }

    public function assign(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'crem_number' => 'nullable|string|max:10',
            'tier_name' => 'required|string|max:200',
            'is_excluded' => 'nullable|boolean',
            'recovery_option' => 'nullable|string|in:'.implode(',', StudentConstants::RECOVERY_OPTIONS),
            'amphitheater_id' => 'nullable|exists:amphitheaters,id',
            'seat_number' => 'nullable|string|max:20',
        ]);

        $isExcluded = (bool) ($validated['is_excluded'] ?? false);
        $newAmphiId = $validated['amphitheater_id'] ?? null;
        $newSeat = $validated['seat_number'] ?: null;

        $occupant = ($newAmphiId && $newSeat)
            ? Student::where('amphitheater_id', $newAmphiId)
                ->where('seat_number', $newSeat)
                ->where('id', '!=', $student->id)
                ->first()
            : null;

        DB::transaction(function () use ($student, $occupant, $validated, $isExcluded, $newAmphiId, $newSeat) {
            if ($occupant) {
                ManualPlacementLog::create([
                    'student_id' => $occupant->id,
                    'from_amphitheater' => $occupant->amphitheater?->name,
                    'from_seat' => $occupant->seat_number,
                    'to_amphitheater' => $student->amphitheater?->name,
                    'to_seat' => $student->seat_number,
                ]);

                $occupant->update([
                    'amphitheater_id' => $student->amphitheater_id,
                    'seat_number' => $student->seat_number,
                    'is_manually_placed' => $student->amphitheater_id !== null,
                ]);
            }

            ManualPlacementLog::create([
                'student_id' => $student->id,
                'from_amphitheater' => $student->amphitheater?->name,
                'from_seat' => $student->seat_number,
                'to_amphitheater' => $newAmphiId ? Amphitheater::find($newAmphiId)?->name : null,
                'to_seat' => $newSeat,
            ]);

            $student->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'crem_number' => $validated['crem_number'] ?? null,
                'tier_name' => $validated['tier_name'],
                'is_excluded' => $isExcluded,
                'recovery_option' => $isExcluded ? ($validated['recovery_option'] ?? null) : null,
                'amphitheater_id' => $newAmphiId,
                'seat_number' => $newSeat,
                'is_manually_placed' => $newAmphiId !== null,
                'is_manually_edited' => true,
            ]);
        });

        $name = strtoupper($validated['last_name']).' '.$validated['first_name'];

        $message = $occupant
            ? $name.' ↔ '.strtoupper($occupant->last_name).' '.$occupant->first_name.' — places interverties.'
            : $name.' — mis à jour.';

        return back()->with('success', $message);
    }

    public function destroy(Student $student): RedirectResponse
    {
        $name = strtoupper($student->last_name).' '.$student->first_name;
        $student->delete();

        return redirect()->route('students.index')->with('success', $name.' — supprimé.');
    }

    public function manualPlacements()
    {
        $students = Student::where('is_manually_placed', true)
            ->with(['amphitheater', 'manualPlacementLogs'])
            ->orderBy('last_name')
            ->get();

        $logs = ManualPlacementLog::with('student')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('students.manual-placements', compact('students', 'logs'));
    }

    public function byAmphi(Amphitheater $amphi)
    {
        $students = $amphi->students()
            ->orderBySeat()
            ->get();

        $amphitheaters = Amphitheater::orderBy('sort_order')->get();

        return view('students.amphi', compact('students', 'amphi', 'amphitheaters'));
    }
}
