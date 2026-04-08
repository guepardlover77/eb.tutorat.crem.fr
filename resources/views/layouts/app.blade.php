<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Examen Blanc CREM – Tutorat Poitiers' }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/logo-192.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    <!-- Tailwind CDN avec charte graphique Tutorat Poitiers -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Figtree', 'ui-sans-serif', 'system-ui'],
                    },
                    colors: {
                        brand: {
                            red: '#CC2929',
                            'red-dark': '#A81E1E',
                            'red-light': '#E8453E',
                            green: '#3A8C3A',
                            'green-dark': '#2D6E2D',
                            'green-light': '#4CAF50',
                            dark: '#1C1C1C',
                            gray: '#F5F5F5',
                        },
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-brand-gray min-h-screen font-sans antialiased">

    <nav class="bg-brand-red text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5">
                    <span class="text-lg font-bold tracking-tight">CREM · Examen Blanc S2</span>
                </a>
                <a href="{{ route('students.index') }}" class="text-sm hover:text-red-200 transition-colors">Étudiants</a>
                @php $errorCount = \App\Models\Student::where('has_error', true)->count(); @endphp
                @if($errorCount > 0)
                    <a href="{{ route('students.errors') }}" class="text-sm text-yellow-300 hover:text-yellow-100 font-medium transition-colors">
                        ⚠ Erreurs CREM ({{ $errorCount }})
                    </a>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-sm hover:text-red-200 transition-colors">Déconnexion</button>
            </form>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if(session('success'))
            <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-800 rounded-lg text-sm font-medium">
                ✓ {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-800 rounded-lg text-sm font-medium">
                ✗ {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-12 py-4 text-center text-xs text-gray-400 border-t border-gray-200">
        <div class="flex items-center justify-center gap-2">
            <img src="/logo-tutorat.png" alt="" class="h-5 w-5 opacity-60">
            <span>Tutorat Poitiers — CREM Examen Blanc</span>
        </div>
    </footer>

</body>
</html>
