@props(['student', 'amphitheaters'])

<button
    data-assign-btn
    data-student-id="{{ $student->id }}"
    data-student-name="{{ strtoupper($student->last_name) }} {{ $student->first_name }}"
    data-first-name="{{ $student->first_name }}"
    data-last-name="{{ $student->last_name }}"
    data-email="{{ $student->email }}"
    data-crem="{{ $student->crem_number ?? '' }}"
    data-tier="{{ $student->tier_name }}"
    data-excluded="{{ $student->is_excluded ? '1' : '0' }}"
    data-recovery-option="{{ $student->recovery_option ?? '' }}"
    data-amphi-id="{{ $student->amphitheater_id ?? '' }}"
    data-seat="{{ $student->seat_number ?? '' }}"
    data-url="{{ route('students.assign', $student) }}"
    data-delete-url="{{ route('students.destroy', $student) }}"
    class="text-xs px-2 py-1 bg-orange-50 hover:bg-orange-100 text-orange-700 rounded border border-orange-200 transition">
    Modifier
</button>
