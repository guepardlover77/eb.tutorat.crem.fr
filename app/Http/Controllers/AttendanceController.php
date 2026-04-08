<?php

namespace App\Http\Controllers;

use App\Models\Amphitheater;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index()
    {
        $amphis = Amphitheater::orderBy('sort_order')
            ->withCount([
                'students',
                'students as present_count' => fn($q) => $q->where('is_present', true),
            ])
            ->get()
            ->map(fn(Amphitheater $a) => [
                'id'      => $a->id,
                'name'    => $a->name,
                'present' => $a->present_count,
                'total'   => $a->students_count,
            ]);

        return view('attendance.index', ['amphis' => $amphis]);
    }

    public function data(Amphitheater $amphi): JsonResponse
    {
        $students = $amphi->students()
            ->orderBySeat()
            ->get(['id', 'seat_number', 'crem_number', 'last_name', 'first_name', 'is_present']);

        return response()->json([
            'students' => $students,
            'present'  => $students->where('is_present', true)->count(),
            'total'    => $students->count(),
        ]);
    }

    public function toggle(Student $student): JsonResponse
    {
        $nowPresent = !$student->is_present;

        $student->update([
            'is_present'       => $nowPresent,
            'marked_present_at' => $nowPresent ? now() : null,
        ]);

        return response()->json(['is_present' => $nowPresent]);
    }

    public function resetAmphi(Amphitheater $amphi): JsonResponse
    {
        $amphi->students()->update([
            'is_present'        => false,
            'marked_present_at' => null,
        ]);

        return response()->json(['success' => true]);
    }
}
