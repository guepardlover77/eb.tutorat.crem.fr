<x-layouts.app title="Placements manuels">
    <div class="space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Placements manuels</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $students->count() }} étudiant(s) placé(s) manuellement</p>
            </div>
            <a href="{{ route('dashboard') }}"
               class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 border rounded-lg hover:bg-gray-50 transition">
                ← Tableau de bord
            </a>
        </div>

        {{-- Currently manually placed --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-orange-50">
                <h2 class="font-semibold text-orange-800">Placements manuels actuels</h2>
            </div>
            @if($students->isEmpty())
                <p class="px-5 py-4 text-sm text-gray-500">Aucun placement manuel actif.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left">Étudiant</th>
                                <th class="px-5 py-3 text-left">N° CREM</th>
                                <th class="px-5 py-3 text-left">Tarif</th>
                                <th class="px-5 py-3 text-left">Amphi</th>
                                <th class="px-5 py-3 text-left">Place</th>
                                <th class="px-5 py-3 text-left">Dernière modif.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($students as $student)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    {{ strtoupper($student->last_name) }} {{ $student->first_name }}
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $student->crem_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $student->tier_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $student->amphitheater?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $student->seat_number ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-400 text-xs">
                                    {{ $student->manualPlacementLogs->first()?->created_at->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- History --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h2 class="font-semibold text-gray-800">Historique des modifications</h2>
            </div>
            @if($logs->isEmpty())
                <p class="px-5 py-4 text-sm text-gray-500">Aucune modification enregistrée.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left">Date</th>
                                <th class="px-5 py-3 text-left">Étudiant</th>
                                <th class="px-5 py-3 text-left">Avant</th>
                                <th class="px-5 py-3 text-left">Après</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    {{ strtoupper($log->student->last_name) }} {{ $log->student->first_name }}
                                </td>
                                <td class="px-5 py-3 text-gray-500">
                                    @if($log->from_amphitheater)
                                        {{ $log->from_amphitheater }}
                                        @if($log->from_seat) — place {{ $log->from_seat }}@endif
                                    @else
                                        <span class="text-gray-300">Non placé</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if($log->to_amphitheater)
                                        <span class="text-orange-700 font-medium">{{ $log->to_amphitheater }}</span>
                                        @if($log->to_seat) — place {{ $log->to_seat }}@endif
                                    @else
                                        <span class="text-red-400">Désassigné</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-layouts.app>
