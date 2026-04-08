<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Examen Blanc CREM' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        @keyframes toast-in  { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toast-out { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(8px); } }
        .toast-enter { animation: toast-in .2s ease forwards; }
        .toast-leave { animation: toast-out .2s ease forwards; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans antialiased">

    {{-- Navigation --}}
    <nav class="bg-blue-800 text-white shadow-lg" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            {{-- Brand --}}
            <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight hover:text-blue-200 shrink-0">
                CREM · Examen Blanc S2
            </a>

            {{-- Desktop links --}}
            <div class="hidden sm:flex items-center gap-5">
                <a href="{{ route('students.index') }}" class="text-sm hover:text-blue-200 {{ request()->routeIs('students.index') ? 'text-white font-semibold underline underline-offset-4' : '' }}">Étudiants</a>
                <a href="{{ route('students.recuperation') }}" class="text-sm hover:text-blue-200 {{ request()->routeIs('students.recuperation') ? 'text-white font-semibold underline underline-offset-4' : '' }}">Récupération</a>
                <a href="{{ route('students.manual-placements') }}" class="text-sm hover:text-blue-200 {{ request()->routeIs('students.manual-placements') ? 'text-white font-semibold underline underline-offset-4' : '' }}">Placements manuels</a>
                <a href="{{ route('attendance.index') }}" class="text-sm hover:text-blue-200 {{ request()->routeIs('attendance.*') ? 'text-white font-semibold underline underline-offset-4' : '' }}">Émargement</a>
                @php $errorCount = \Illuminate\Support\Facades\Cache::remember('crem_error_count', 300, fn() => \App\Models\Student::where('has_error', true)->count()); @endphp
                @if($errorCount > 0)
                    <a href="{{ route('students.errors') }}" class="text-sm text-red-300 hover:text-red-100 font-medium {{ request()->routeIs('students.errors') ? 'underline underline-offset-4' : '' }}">
                        Erreurs ({{ $errorCount }})
                    </a>
                @endif
                <a href="/placement" target="_blank" class="text-sm hover:text-blue-200">Placement public</a>
            </div>

            {{-- Right side: logout + hamburger --}}
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                    @csrf
                    <button class="text-sm hover:text-blue-200">Déconnexion</button>
                </form>

                {{-- Hamburger (mobile only) --}}
                <button @click="open = !open" class="sm:hidden p-1 rounded hover:bg-blue-700 transition" aria-label="Menu">
                    <svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile dropdown --}}
        <div x-show="open" x-cloak @click.away="open = false"
             class="sm:hidden border-t border-blue-700 bg-blue-800 px-4 py-3 space-y-2">
            <a href="{{ route('students.index') }}" class="block text-sm py-1.5 hover:text-blue-200 {{ request()->routeIs('students.index') ? 'font-semibold underline underline-offset-4' : '' }}">Étudiants</a>
            <a href="{{ route('students.recuperation') }}" class="block text-sm py-1.5 hover:text-blue-200 {{ request()->routeIs('students.recuperation') ? 'font-semibold underline underline-offset-4' : '' }}">Récupération</a>
            <a href="{{ route('students.manual-placements') }}" class="block text-sm py-1.5 hover:text-blue-200 {{ request()->routeIs('students.manual-placements') ? 'font-semibold underline underline-offset-4' : '' }}">Placements manuels</a>
            <a href="{{ route('attendance.index') }}" class="block text-sm py-1.5 hover:text-blue-200 {{ request()->routeIs('attendance.*') ? 'font-semibold underline underline-offset-4' : '' }}">Émargement</a>
            @if($errorCount > 0)
                <a href="{{ route('students.errors') }}" class="block text-sm py-1.5 text-red-300 hover:text-red-100 font-medium">Erreurs ({{ $errorCount }})</a>
            @endif
            <a href="/placement" target="_blank" class="block text-sm py-1.5 hover:text-blue-200">Placement public</a>
            <div class="pt-2 border-t border-blue-700">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm hover:text-blue-200">Déconnexion</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        {{-- Flash messages (auto-dismiss) --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mb-4 px-4 py-3 bg-green-100 border border-green-300 text-green-800 rounded-lg text-sm flex justify-between items-center">
                <span>✓ {{ session('success') }}</span>
                <button @click="show = false" class="text-green-600 hover:text-green-800 ml-4">&times;</button>
            </div>
        @endif
        @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
                 class="mb-4 px-4 py-3 bg-red-100 border border-red-300 text-red-800 rounded-lg text-sm flex justify-between items-center">
                <span>✗ {{ session('error') }}</span>
                <button @click="show = false" class="text-red-600 hover:text-red-800 ml-4">&times;</button>
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Toast container --}}
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <script>
    window.showToast = function(message, type = 'error') {
        const container = document.getElementById('toast-container');
        const colors = {
            error:   'bg-red-600 text-white',
            success: 'bg-green-600 text-white',
            info:    'bg-blue-600 text-white',
            warning: 'bg-amber-500 text-white',
        };
        const el = document.createElement('div');
        el.className = `toast-enter pointer-events-auto px-4 py-3 rounded-lg shadow-lg text-sm max-w-xs ${colors[type] || colors.error}`;
        el.textContent = message;
        container.appendChild(el);
        setTimeout(() => {
            el.classList.remove('toast-enter');
            el.classList.add('toast-leave');
            el.addEventListener('animationend', () => el.remove());
        }, 4000);
    };
    </script>

</body>
</html>
