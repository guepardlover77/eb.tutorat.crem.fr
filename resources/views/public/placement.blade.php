<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon placement — Examen Blanc CREM</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/logo-192.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Figtree', 'ui-sans-serif', 'system-ui'] },
                    colors: {
                        brand: { red: '#CC2929', 'red-dark': '#A81E1E' },
                    },
                },
            },
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }

        /* ---- sticky split layout ---- */
        .split-layout {
            display: flex;
            align-items: flex-start;
        }
        .panel-left {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            width: 50%;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-right: 1px solid #e5e7eb;
            z-index: 5;
        }
        .panel-right {
            width: 50%;
            min-height: 100vh;
            background: #f3f4f6;
        }

        /* hide scrollbar on tab row */
        .tabs-row { scrollbar-width: none; }
        .tabs-row::-webkit-scrollbar { display: none; }

        /* table rows scroll within left panel */
        .table-scroll { flex: 1; overflow-y: auto; }

        /* ---- mobile: stack vertically ---- */
        @media (max-width: 768px) {
            .split-layout { flex-direction: column; }
            .panel-left {
                position: relative;
                width: 100%;
                height: auto;
                overflow-y: visible;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
            }
            .panel-right { width: 100%; min-height: auto; }
        }
    </style>
</head>

<body class="font-sans antialiased bg-gray-100" x-data="placementApp()" x-init="init()">

    {{-- ===== NAV ===== --}}
    <nav class="bg-[#CC2929] text-white shadow-md z-20 relative">
        <div class="px-5 py-3 flex items-center gap-3">
            <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5 shrink-0">
            <span class="text-base font-bold tracking-tight">CREM · Examen Blanc S2</span>
        </div>
    </nav>

    @if($amphitheaters->isEmpty())
        <div class="flex items-center justify-center min-h-[60vh]">
            <p class="text-gray-400 text-lg">Les placements ne sont pas encore disponibles.</p>
        </div>
    @else

    {{-- ===== SPLIT LAYOUT ===== --}}
    <div class="split-layout">

        {{-- ===== LEFT PANEL : TABLE (sticky) ===== --}}
        <div class="panel-left">

            {{-- Search --}}
            <div class="px-5 pt-5 pb-4 border-b border-gray-100 shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Rechercher</p>
                    <p x-show="lastUpdatedSec !== null"
                       x-text="updatedLabel()"
                       x-bind:class="justUpdated ? 'text-green-500' : 'text-gray-300'"
                       class="text-xs transition-colors duration-700"></p>
                </div>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                    <input
                        type="text"
                        x-model="search"
                        x-on:input="onSearch()"
                        placeholder="Votre numéro CREM…"
                        autocomplete="off"
                        class="w-full pl-9 pr-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#CC2929] focus:border-transparent text-sm transition"
                    >
                </div>

                <template x-if="result && result !== 'not_found'">
                    <div class="mt-3 flex items-center gap-3 px-4 py-2.5 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-green-800 leading-tight">
                                Place <strong x-text="result.seat" class="text-base"></strong>
                                <span class="text-green-600">·</span>
                                <span x-text="result.amphi"></span>
                            </p>
                        </div>
                        <button x-on:click="activeTab = result.tabIndex"
                                class="text-xs px-2.5 py-1 bg-green-700 hover:bg-green-800 text-white rounded-md transition shrink-0">
                            Voir
                        </button>
                    </div>
                </template>

                <template x-if="result === 'not_found'">
                    <p class="mt-3 text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-4 py-2.5">
                        Numéro introuvable — contactez le tutorat.
                    </p>
                </template>
            </div>

            {{-- Tabs --}}
            <div class="tabs-row flex overflow-x-auto border-b border-gray-100 shrink-0">
                @foreach($amphitheaters as $i => $amphi)
                <button
                    x-on:click="activeTab = {{ $i }}"
                    x-bind:class="activeTab === {{ $i }}
                        ? 'border-b-2 border-[#CC2929] text-[#CC2929] font-semibold bg-white'
                        : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-3 text-xs whitespace-nowrap transition-colors shrink-0">
                    {{ $amphi->name }}
                </button>
                @endforeach
            </div>

            {{-- Table header (sticky within panel) --}}
            <div class="shrink-0 bg-gray-50 border-b border-gray-100 grid grid-cols-2">
                <div class="px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wider">N° Place</div>
                <div class="px-5 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wider">N° CREM</div>
            </div>

            {{-- Table body (scrollable within panel) --}}
            <div class="table-scroll">
                <template x-for="(student, idx) in (amphitheaters[activeTab] ? amphitheaters[activeTab].students : [])" :key="idx">
                    <div
                        x-bind:class="isHighlighted(student.crem)
                            ? 'bg-amber-50 border-l-4 border-amber-400'
                            : 'hover:bg-gray-50 border-l-4 border-transparent'"
                        class="grid grid-cols-2 border-b border-gray-50 transition-colors">
                        <div class="px-5 py-2.5 font-mono text-sm font-semibold text-gray-900" x-text="student.seat"></div>
                        <div class="px-5 py-2.5 text-sm text-gray-500" x-text="student.crem || '—'"></div>
                    </div>
                </template>
            </div>

            {{-- Footer count --}}
            <div class="shrink-0 px-5 py-3 border-t border-gray-100 bg-gray-50">
                <p class="text-xs text-gray-400"
                   x-text="amphitheaters[activeTab]
                       ? amphitheaters[activeTab].students.length + ' étudiant(s) — ' + amphitheaters[activeTab].name
                       : ''"></p>
            </div>

        </div>
        {{-- /LEFT PANEL --}}

        {{-- ===== RIGHT PANEL : PLAN (scrolls with page) ===== --}}
        <div class="panel-right">
            @foreach($amphitheaters as $i => $amphi)
            <div x-show="activeTab === {{ $i }}" x-cloak class="p-6">
                @if($amphi->plan_image)
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">
                        Plan — {{ $amphi->name }}
                    </p>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm bg-white">
                        <img src="/images/amphis/{{ $amphi->plan_image }}.png"
                             alt="Plan {{ $amphi->name }}"
                             class="block max-w-none"
                             style="image-rendering: crisp-edges;">
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-64 text-gray-300">
                        <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <p class="text-sm">Plan non disponible</p>
                    </div>
                @endif
            </div>
            @endforeach
        </div>
        {{-- /RIGHT PANEL --}}

    </div>
    {{-- /SPLIT LAYOUT --}}

    @endif

    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
    function placementApp() {
        return {
            activeTab: 0,
            search: '',
            result: null,
            amphitheaters: @json($amphitheaterData),
            hash: '',
            lastUpdatedSec: null,
            justUpdated: false,

            init() {
                setInterval(() => this.poll(), 15000);
                setInterval(() => {
                    if (this.lastUpdatedSec !== null) this.lastUpdatedSec++;
                }, 1000);
            },

            async poll() {
                try {
                    const res = await fetch('/placement/data', { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.hash !== this.hash) {
                        this.amphitheaters = data.amphitheaters;
                        this.hash = data.hash;
                        this.lastUpdatedSec = 0;
                        this.justUpdated = true;
                        setTimeout(() => this.justUpdated = false, 1500);
                        if (this.search.trim()) this.onSearch();
                    }
                } catch (e) {}
            },

            onSearch() {
                const q = this.search.trim().toLowerCase();
                if (!q) { this.result = null; return; }
                for (let i = 0; i < this.amphitheaters.length; i++) {
                    const s = this.amphitheaters[i].students.find(s => s.crem.toLowerCase().includes(q));
                    if (s) {
                        this.result = { seat: s.seat, amphi: this.amphitheaters[i].name, tabIndex: i };
                        return;
                    }
                }
                this.result = 'not_found';
            },

            isHighlighted(crem) {
                if (!this.search.trim() || !crem) return false;
                return crem.toLowerCase().includes(this.search.trim().toLowerCase());
            },

            updatedLabel() {
                if (this.lastUpdatedSec === null) return '';
                if (this.lastUpdatedSec < 5) return '· à l\'instant';
                return '· il y a ' + this.lastUpdatedSec + 's';
            },
        };
    }
    </script>

</body>
</html>
