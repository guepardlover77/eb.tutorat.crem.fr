<x-layouts.app title="Erreurs CREM">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-red-700">Erreurs de cohérence CREM</h1>
            <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">← Retour</a>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
            Ces étudiants ont un numéro CREM incohérent avec leur tarif d'inscription.
            Ils ont quand même été placés selon les règles habituelles. Vérifiez manuellement.
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-red-50 text-xs font-medium text-red-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">N° CREM</th>
                            <th class="px-4 py-3 text-left">Nom</th>
                            <th class="px-4 py-3 text-left">Prénom</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Tarif</th>
                            <th class="px-4 py-3 text-left">Amphi</th>
                            <th class="px-4 py-3 text-left">Erreur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-red-100">
                        @forelse($students as $s)
                        <tr class="bg-red-50">
                            <td class="px-4 py-2.5 font-mono font-bold text-red-700">{{ $s->crem_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-medium">{{ strtoupper($s->last_name) }}</td>
                            <td class="px-4 py-2.5">{{ $s->first_name }}</td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $s->email }}</td>
                            <td class="px-4 py-2.5 text-xs text-gray-600">{{ $s->tier_name }}</td>
                            <td class="px-4 py-2.5">
                                {{ $s->amphitheater?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs text-red-600 font-medium">{{ $s->error_message }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                Aucune erreur détectée.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
