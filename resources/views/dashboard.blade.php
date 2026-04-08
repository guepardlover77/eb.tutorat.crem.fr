<x-layouts.app title="Dashboard">
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
                @if($helloassoConfigured)
                    @if($lastSync)
                        <p class="text-sm text-gray-500 mt-1">
                            Dernière sync : {{ $lastSync->finished_at?->diffForHumans() ?? 'en cours' }}
                            @if($lastSync->status === 'failed')
                                <span class="text-red-500 font-medium">— Échec</span>
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-gray-500 mt-1">Aucune synchronisation effectuée</p>
                    @endif
                @endif
            </div>
            @if($helloassoConfigured)
            <div>
                <button id="sync-btn"
                        onclick="startSync()"
                        title="Récupérer les nouvelles inscriptions et mises à jour depuis HelloAsso"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow transition">
                    Synchroniser HelloAsso
                </button>
                <div id="sync-progress" class="hidden mt-2 text-sm text-gray-600 space-y-1">
                    <div class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span id="sync-status">Connexion à HelloAsso…</span>
                    </div>
                </div>
            </div>

            <script>
            async function startSync() {
                const btn      = document.getElementById('sync-btn');
                const progress = document.getElementById('sync-progress');
                const status   = document.getElementById('sync-status');
                const csrf     = document.querySelector('meta[name="csrf-token"]')?.content
                              || '{{ csrf_token() }}';

                btn.disabled   = true;
                btn.textContent = 'Synchronisation…';
                progress.classList.remove('hidden');

                async function postJson(url, body) {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    if (!r.ok) throw new Error(await r.text());
                    return r.json();
                }

                try {
                    let data = await postJson('{{ route('sync.run') }}', {});
                    status.textContent = `Page 1 — ${data.new} nouveaux, ${data.updated} mis à jour…`;

                    while (!data.done) {
                        data = await postJson('{{ route('sync.chunk') }}', { log_id: data.log_id });
                        status.textContent = `En cours — ${data.new} nouveaux, ${data.updated} mis à jour…`;
                    }

                    status.textContent = `✓ Terminé — ${data.new} nouveaux, ${data.updated} mis à jour.`;
                    btn.textContent    = 'Synchroniser HelloAsso';
                    btn.disabled       = false;
                    setTimeout(() => location.reload(), 1500);
                } catch (e) {
                    status.textContent = '✗ Erreur : ' + e.message;
                    btn.textContent    = 'Synchroniser HelloAsso';
                    btn.disabled       = false;
                }
            }
            </script>
            @endif
        </div>

        @unless($helloassoConfigured)
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <p class="text-amber-800 text-sm font-medium">HelloAsso non configuré</p>
            <p class="text-amber-700 text-sm mt-1">Les clés <code class="bg-amber-100 px-1 rounded">HELLOASSO_CLIENT_ID</code> et <code class="bg-amber-100 px-1 rounded">HELLOASSO_CLIENT_SECRET</code> ne sont pas définies dans le fichier <code class="bg-amber-100 px-1 rounded">.env</code>. La synchronisation et la vérification HelloAsso sont désactivées.</p>
        </div>
        @endunless

        {{-- Stats cards --}}
        <div class="flex gap-3">
            <div class="flex-1 bg-white rounded-xl shadow-sm border p-4 min-w-0">
                <p class="text-2xl font-bold text-blue-700">{{ number_format($stats['total']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Inscrits</p>
            </div>
            <div class="flex-1 bg-white rounded-xl shadow-sm border p-4 min-w-0">
                <p class="text-2xl font-bold text-green-600">{{ number_format($stats['placed']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Placés</p>
            </div>
            <div class="flex-1 bg-white rounded-xl shadow-sm border p-4 min-w-0">
                <p class="text-2xl font-bold text-amber-500">{{ number_format($stats['unplaced']) }}</p>
                <p class="text-xs text-gray-500 mt-1">À placer</p>
            </div>
            @if($stats['manual'] > 0)
            <a href="{{ route('students.manual-placements') }}"
               class="flex-1 bg-orange-50 rounded-xl shadow-sm border border-orange-200 p-4 min-w-0 block hover:bg-orange-100 transition">
                <p class="text-2xl font-bold text-orange-600">{{ number_format($stats['manual']) }}</p>
                <p class="text-xs text-orange-500 mt-1">Manuels</p>
            </a>
            @endif
            <div class="flex-1 bg-white rounded-xl shadow-sm border p-4 min-w-0">
                <p class="text-2xl font-bold text-gray-400">{{ number_format($stats['excluded']) }}</p>
                <p class="text-xs text-gray-500 mt-1">Récupérations</p>
            </div>
            @if($stats['errors'] > 0)
            <a href="{{ route('students.errors') }}"
               class="flex-1 bg-red-50 rounded-xl shadow-sm border border-red-200 p-4 min-w-0 block hover:bg-red-100 transition">
                <p class="text-2xl font-bold text-red-600">{{ number_format($stats['errors']) }}</p>
                <p class="text-xs text-red-500 mt-1">Erreurs</p>
            </a>
            @endif
        </div>

        {{-- Placement actions --}}
        <div class="bg-white rounded-xl shadow-sm border p-5 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('placement.run') }}">
                @csrf
                <button title="Répartir automatiquement les étudiants dans les amphis selon leur tarif et numéro CREM"
                        class="px-5 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium rounded-lg border border-blue-200 transition">
                    Lancer le placement
                </button>
            </form>
            @if($stats['manual'] > 0)
            @php $manualCount = $stats['manual']; @endphp
            <form method="POST" action="{{ route('placement.reset') }}"
                  onsubmit="return confirm('Réinitialiser les placements automatiques ? Les {{ $manualCount }} placement(s) manuel(s) seront conservés.')">
                @csrf
                <input type="hidden" name="include_manual" value="0">
                <button title="Retirer tous les placements automatiques, les placements manuels restent en place"
                        class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg border transition">
                    Réinitialiser (garder manuel)
                </button>
            </form>
            <form method="POST" action="{{ route('placement.reset') }}"
                  onsubmit="return confirm('Réinitialiser TOUS les placements, y compris les {{ $manualCount }} placement(s) manuel(s) ?')">
                @csrf
                <input type="hidden" name="include_manual" value="1">
                <button title="Retirer TOUS les placements, y compris les placements manuels"
                        class="px-5 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 font-medium rounded-lg border border-red-200 transition">
                    Réinitialiser tout
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('placement.reset') }}"
                  onsubmit="return confirm('Réinitialiser tous les placements ?')">
                @csrf
                <button title="Retirer tous les placements et repartir de zéro"
                        class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg border transition">
                    Réinitialiser
                </button>
            </form>
            @endif
            <form method="POST" action="{{ route('placement.assign-numbers') }}"
                  onsubmit="return confirm('Attribuer des numéros CREM 8xxx aux étudiants sans numéro ?')">
                @csrf
                <button title="Attribuer automatiquement un numéro CREM 8001, 8002… aux étudiants non-adhérents qui n'en ont pas"
                        class="px-5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium rounded-lg border border-amber-200 transition">
                    Attribuer numéros 8xxx
                </button>
            </form>
            @if($helloassoConfigured)
            <button onclick="startVerify()"
                    id="verify-btn"
                    title="Comparer la base avec HelloAsso pour détecter les acheteurs manquants ou supprimés"
                    class="px-5 py-2.5 bg-teal-50 hover:bg-teal-100 text-teal-700 font-medium rounded-lg border border-teal-200 transition">
                Vérification HelloAsso
            </button>
            @endif
            @if($stats['errors'] > 0)
            <a href="{{ route('students.errors') }}"
               title="Voir les étudiants avec une incohérence entre numéro CREM et tarif, ou un email en doublon"
               class="px-5 py-2.5 bg-red-100 hover:bg-red-200 text-red-700 font-medium rounded-lg border border-red-300 transition">
                Voir {{ $stats['errors'] }} erreur(s)
            </a>
            @endif
        </div>

        {{-- Amphitheaters breakdown --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b">
                <h2 class="font-semibold text-gray-800">Répartition par amphi</h2>
            </div>
            <div class="divide-y">
                @foreach($amphitheaters as $row)
                @php $a = $row['model']; @endphp
                <div class="px-5 py-3 flex items-center gap-4">
                    <div class="w-32 text-sm font-medium text-gray-700 truncate">{{ $a->name }}</div>
                    <div class="flex-1">
                        <div class="bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div class="h-3 rounded-full transition-all
                                {{ $row['fill_rate'] >= 95 ? 'bg-red-500' : ($row['fill_rate'] >= 75 ? 'bg-amber-400' : 'bg-blue-500') }}"
                                style="width: {{ min($row['fill_rate'], 100) }}%"></div>
                        </div>
                    </div>
                    <div class="w-28 text-sm text-gray-600 text-right">
                        {{ $row['placed'] }}/{{ $a->seatCount() }} places
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('students.amphi', $a) }}"
                           class="text-xs px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded border border-blue-200">
                            Voir
                        </a>
                        @if($row['placed'] > 0)
                        <a href="{{ route('export.amphi', $a) }}"
                           class="text-xs px-2 py-1 bg-green-50 hover:bg-green-100 text-green-700 rounded border border-green-200">
                            Liste
                        </a>
                        <a href="{{ route('export.emargement', $a) }}"
                           class="text-xs px-2 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded border border-purple-200">
                            Émarg.
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Modale vérification placement étudiants --}}
    <dialog id="placement-check-modal"
            class="rounded-xl shadow-xl border p-0 backdrop:bg-black/30 w-full max-w-2xl">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Étudiants à vérifier avant export</h3>
            <button onclick="document.getElementById('placement-check-modal').close()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-4">
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mb-4">
                Ces étudiants ne seront pas inclus dans le fichier Excel. Traitez les problèmes avant de générer.
            </p>
            <div class="overflow-y-auto max-h-80">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">N° CREM</th>
                            <th class="px-3 py-2 text-left">Nom</th>
                            <th class="px-3 py-2 text-left">Amphi assigné</th>
                            <th class="px-3 py-2 text-left">Raison</th>
                        </tr>
                    </thead>
                    <tbody id="placement-errors-tbody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
            <button onclick="document.getElementById('placement-check-modal').close()"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg border transition">
                Annuler
            </button>
            <a href="{{ route('export.student-placement') }}"
               class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                Générer quand même
            </a>
        </div>
    </dialog>

    @if($helloassoConfigured)
    {{-- Modale vérification HelloAsso --}}
    <dialog id="verify-modal"
            class="rounded-xl shadow-xl border p-0 backdrop:bg-black/30 w-full max-w-3xl">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Vérification HelloAsso</h3>
            <button onclick="document.getElementById('verify-modal').close()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-4">
            <div id="verify-progress" class="text-sm text-gray-600 mb-4">
                <div class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-teal-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span id="verify-status">Récupération des données HelloAsso…</span>
                </div>
            </div>
            <div id="verify-result" class="hidden">
                <div id="verify-ok" class="hidden bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4">
                    <p class="text-green-700 font-medium" id="verify-ok-text"></p>
                </div>
                <div id="verify-warn" class="hidden">
                    <div id="verify-missing-section" class="hidden mb-4">
                        <p class="text-sm font-medium text-red-700 mb-2" id="verify-missing-title"></p>
                        <div class="overflow-y-auto max-h-60">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">N° CREM</th>
                                        <th class="px-3 py-2 text-left">Nom</th>
                                        <th class="px-3 py-2 text-left">Email</th>
                                        <th class="px-3 py-2 text-left">Tarif</th>
                                    </tr>
                                </thead>
                                <tbody id="verify-missing-tbody" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="verify-deleted-section" class="hidden mb-4">
                        <p class="text-sm font-medium text-amber-700 mb-2" id="verify-deleted-title"></p>
                        <div class="overflow-y-auto max-h-60">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">N° CREM</th>
                                        <th class="px-3 py-2 text-left">Nom</th>
                                        <th class="px-3 py-2 text-left">Email</th>
                                        <th class="px-3 py-2 text-left">Supprimé le</th>
                                    </tr>
                                </thead>
                                <tbody id="verify-deleted-tbody" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end">
            <button onclick="document.getElementById('verify-modal').close()"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg border transition">
                Fermer
            </button>
        </div>
    </dialog>

    <script>
    async function startVerify() {
        const modal    = document.getElementById('verify-modal');
        const progress = document.getElementById('verify-progress');
        const result   = document.getElementById('verify-result');
        const status   = document.getElementById('verify-status');
        const csrf     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

        // Reset state
        progress.classList.remove('hidden');
        result.classList.add('hidden');
        document.getElementById('verify-ok').classList.add('hidden');
        document.getElementById('verify-warn').classList.add('hidden');
        document.getElementById('verify-missing-section').classList.add('hidden');
        document.getElementById('verify-deleted-section').classList.add('hidden');

        modal.showModal();

        let allMissing = [];
        let allDeleted = [];
        let totalChecked = 0;
        let cursor = null;
        let page = 0;

        try {
            do {
                page++;
                status.textContent = `Page ${page} — ${totalChecked} acheteurs vérifiés…`;

                const resp = await fetch('{{ route('sync.verify') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ cursor }),
                });
                if (!resp.ok) throw new Error(await resp.text());
                const data = await resp.json();

                allMissing = allMissing.concat(data.missing);
                allDeleted = allDeleted.concat(data.deleted);
                totalChecked += data.checked;
                cursor = data.next_cursor;
            } while (cursor);

            progress.classList.add('hidden');
            result.classList.remove('hidden');

            if (allMissing.length === 0 && allDeleted.length === 0) {
                const ok = document.getElementById('verify-ok');
                ok.classList.remove('hidden');
                document.getElementById('verify-ok-text').textContent =
                    `Tout est OK — ${totalChecked} acheteurs HelloAsso vérifiés, aucun manquant.`;
            } else {
                document.getElementById('verify-warn').classList.remove('hidden');

                if (allMissing.length > 0) {
                    const sec = document.getElementById('verify-missing-section');
                    sec.classList.remove('hidden');
                    document.getElementById('verify-missing-title').textContent =
                        `${allMissing.length} acheteur(s) absent(s) de la base :`;
                    document.getElementById('verify-missing-tbody').innerHTML = allMissing.map(s => `
                        <tr class="hover:bg-red-50">
                            <td class="px-3 py-2 font-mono text-gray-700">${s.crem_number || '—'}</td>
                            <td class="px-3 py-2 text-gray-800">${s.last_name.toUpperCase()} ${s.first_name}</td>
                            <td class="px-3 py-2 text-gray-600">${s.email}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">${s.tier_name}</td>
                        </tr>
                    `).join('');
                }

                if (allDeleted.length > 0) {
                    const sec = document.getElementById('verify-deleted-section');
                    sec.classList.remove('hidden');
                    document.getElementById('verify-deleted-title').textContent =
                        `${allDeleted.length} acheteur(s) supprimé(s) manuellement :`;
                    document.getElementById('verify-deleted-tbody').innerHTML = allDeleted.map(s => `
                        <tr class="hover:bg-amber-50">
                            <td class="px-3 py-2 font-mono text-gray-700">${s.crem_number || '—'}</td>
                            <td class="px-3 py-2 text-gray-800">${s.last_name.toUpperCase()} ${s.first_name}</td>
                            <td class="px-3 py-2 text-gray-600">${s.email}</td>
                            <td class="px-3 py-2 text-amber-600 text-xs">${s.deleted_at}</td>
                        </tr>
                    `).join('');
                }
            }
        } catch (e) {
            status.textContent = '✗ Erreur : ' + e.message;
        }
    }
    </script>
    @endif

    <script>
    async function checkStudentPlacement() {
        try {
            const resp = await fetch('{{ route('export.student-placement.check') }}', {
                headers: { 'Accept': 'application/json' },
            });
            const { students } = await resp.json();

            if (students.length === 0) {
                window.location.href = '{{ route('export.student-placement') }}';
                return;
            }

            const tbody = document.getElementById('placement-errors-tbody');
            tbody.innerHTML = students.map(s => `
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-gray-700">${s.crem}</td>
                    <td class="px-3 py-2 text-gray-800">${s.nom}</td>
                    <td class="px-3 py-2 text-gray-600">${s.amphi}</td>
                    <td class="px-3 py-2 text-red-600">${s.raison}</td>
                </tr>
            `).join('');

            document.getElementById('placement-check-modal').showModal();
        } catch (e) {
            alert('Erreur lors de la vérification : ' + e.message);
        }
    }
    </script>

</x-layouts.app>
