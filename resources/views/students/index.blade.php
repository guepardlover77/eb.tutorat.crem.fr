<x-layouts.app title="Étudiants">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Étudiants</h1>
            <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">← Retour</a>
        </div>

        {{-- Filters --}}
        <form id="filter-form" method="GET" class="bg-white border rounded-xl p-4 flex flex-wrap gap-3 items-end shadow-sm">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Recherche</label>
                <input type="text" name="search" id="search-input" value="{{ request('search') }}"
                    placeholder="Nom, prénom, CREM, email…"
                    class="border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500 w-56">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Amphi</label>
                <select name="amphi" onchange="document.getElementById('filter-form').submit()" class="border rounded-lg px-3 py-2 text-sm focus:ring-blue-500">
                    <option value="">Tous</option>
                    @foreach($amphitheaters as $a)
                        <option value="{{ $a->id }}" {{ request('amphi') == $a->id ? 'selected' : '' }}>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tarif</label>
                <select name="tier" onchange="document.getElementById('filter-form').submit()" class="border rounded-lg px-3 py-2 text-sm focus:ring-blue-500">
                    <option value="">Tous</option>
                    @foreach($tiers as $t)
                        <option value="{{ $t }}" {{ request('tier') === $t ? 'selected' : '' }}>
                            {{ $t }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Statut</label>
                <select name="status" onchange="document.getElementById('filter-form').submit()" class="border rounded-lg px-3 py-2 text-sm focus:ring-blue-500">
                    <option value="">Tous</option>
                    <option value="placed"   {{ request('status') === 'placed'   ? 'selected' : '' }}>Placés</option>
                    <option value="unplaced" {{ request('status') === 'unplaced' ? 'selected' : '' }}>Non placés</option>
                    <option value="excluded" {{ request('status') === 'excluded' ? 'selected' : '' }}>Exclus</option>
                    <option value="errors"   {{ request('status') === 'errors'   ? 'selected' : '' }}>Erreurs</option>
                </select>
            </div>
            <a href="{{ route('students.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 border">Réinitialiser</a>
        </form>
        <script>
        (function () {
            let timer;
            document.getElementById('search-input').addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(() => document.getElementById('filter-form').submit(), 400);
            });
        })();
        </script>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-3 border-b text-sm text-gray-500">
                {{ $students->total() }} étudiant(s) trouvé(s)
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">N° Place</th>
                            <th class="px-4 py-3 text-left">N° CREM</th>
                            <th class="px-4 py-3 text-left">Nom</th>
                            <th class="px-4 py-3 text-left">Prénom</th>
                            <th class="px-4 py-3 text-left">Tarif</th>
                            <th class="px-4 py-3 text-left">Amphi</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Statut</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($students as $s)
                        <tr class="{{ $s->has_error ? 'bg-red-50' : ($s->is_excluded ? 'bg-gray-50 opacity-60' : 'hover:bg-blue-50') }}">
                            <td class="px-4 py-2.5 font-mono font-bold text-blue-700">{{ $s->seat_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono">{{ $s->crem_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ strtoupper($s->last_name) }}</td>
                            <td class="px-4 py-2.5">{{ $s->first_name }}</td>
                            <td class="px-4 py-2.5 text-gray-600 text-xs max-w-xs truncate">{{ $s->tier_name }}</td>
                            <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $s->email }}</td>
                            <td class="px-4 py-2.5">
                                @if($s->amphitheater)
                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                        {{ $s->amphitheater->name }}
                                    </span>
                                @elseif($s->is_excluded)
                                    <span class="text-gray-400 text-xs">Exclu</span>
                                @else
                                    <span class="text-amber-500 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($s->has_error)
                                    <span class="text-xs text-red-600 font-medium" title="{{ $s->error_message }}">Erreur</span>
                                @elseif($s->is_excluded)
                                    <span class="text-xs text-gray-400">Récupération</span>
                                @elseif($s->is_manually_placed)
                                    <span class="text-xs text-orange-600 font-medium">Manuel</span>
                                @elseif($s->amphitheater)
                                    <span class="text-xs text-green-600">Placé</span>
                                @else
                                    <span class="text-xs text-amber-500">En attente</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <x-assign-modal :student="$s" :amphitheaters="$amphitheaters" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun étudiant trouvé.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t">
                {{ $students->links() }}
            </div>
        </div>
    </div>

    @include('students._edit-modal', ['amphitheaters' => $amphitheaters, 'tiers' => $tiers])
</x-layouts.app>
