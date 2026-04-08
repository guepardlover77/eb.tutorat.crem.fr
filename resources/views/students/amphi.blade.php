<x-layouts.app title="{{ $amphi->name }}">
    <div class="space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $amphi->name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $students->count() }}/{{ $amphi->seatCount() }} places occupées
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                @foreach($amphitheaters as $a)
                <a href="{{ route('students.amphi', $a) }}"
                   class="px-3 py-1.5 text-xs rounded-lg border transition
                          {{ $a->id === $amphi->id ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                    {{ $a->name }}
                </a>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('export.amphi', $amphi) }}"
               class="px-4 py-2 bg-green-50 hover:bg-green-100 text-green-700 text-sm font-medium rounded-lg border border-green-200 transition">
                Exporter la liste
            </a>
            <a href="{{ route('export.emargement', $amphi) }}"
               class="px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm font-medium rounded-lg border border-purple-200 transition">
                Feuille d'émargement
            </a>
            <a href="{{ route('dashboard') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Retour</a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left w-8">#</th>
                            <th class="px-4 py-3 text-left">N° Place</th>
                            <th class="px-4 py-3 text-left">N° CREM</th>
                            <th class="px-4 py-3 text-left">Nom</th>
                            <th class="px-4 py-3 text-left">Prénom</th>
                            <th class="px-4 py-3 text-left">Tarif</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $i => $s)
                        <tr class="hover:bg-blue-50 {{ $s->has_error ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-2.5 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-4 py-2.5 font-mono font-bold text-blue-700">{{ $s->seat_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono font-medium">{{ $s->crem_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ strtoupper($s->last_name) }}</td>
                            <td class="px-4 py-2.5">{{ $s->first_name }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-500">{{ $s->tier_name }}</td>
                            <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $s->email }}</td>
                            <td class="px-4 py-2.5">
                                <x-assign-modal :student="$s" :amphitheaters="$amphitheaters" />
                                @if($s->is_manually_placed)
                                    <span class="ml-1 text-xs text-orange-600 font-medium">Manuel</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                                Aucun étudiant placé dans cet amphi.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('students._edit-modal', ['amphitheaters' => $amphitheaters])
</x-layouts.app>
