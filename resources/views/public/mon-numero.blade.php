<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon numéro CREM — Examen Blanc</title>

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
                },
            },
        }
    </script>
</head>

<body class="font-sans antialiased bg-gray-100 min-h-screen">

    <nav class="bg-[#CC2929] text-white shadow-md">
        <div class="max-w-2xl mx-auto px-5 py-3 flex items-center gap-3">
            <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5 shrink-0">
            <span class="text-base font-bold tracking-tight">CREM · Examen Blanc S2</span>
        </div>
    </nav>

    <div class="max-w-lg mx-auto mt-12 px-4">
        <div class="bg-white rounded-2xl shadow-sm border p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Mon numéro CREM</h1>
            <p class="text-sm text-gray-500 mb-6">
                Si vous n'êtes pas adhérent au CREM, un numéro vous a été attribué automatiquement pour l'examen blanc.
            </p>

            <form method="POST" action="{{ route('public.mon-numero') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Adresse email</label>
                    <input type="email" name="email" id="email" required
                           value="{{ old('email', request('email')) }}"
                           placeholder="votre.email@example.com"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#CC2929] focus:border-transparent">
                    @error('email')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="w-full px-4 py-2.5 bg-[#CC2929] hover:bg-[#A81E1E] text-white font-medium rounded-lg transition">
                    Rechercher
                </button>
            </form>

            @if($result)
                <div class="mt-6">
                    @if($result['status'] === 'not_found')
                        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            <p class="text-sm text-red-700 font-medium">Adresse email introuvable</p>
                            <p class="text-xs text-red-600 mt-1">
                                Vérifiez l'adresse saisie ou contactez le tutorat.
                            </p>
                        </div>

                    @elseif($result['status'] === 'adherent')
                        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                            <p class="text-sm text-blue-800">
                                <strong>{{ $result['name'] }}</strong>, vous êtes adhérent(e) au CREM.
                            </p>
                            <p class="text-xs text-blue-600 mt-1">
                                Votre numéro CREM est celui inscrit sur votre carte : <strong class="font-mono">{{ $result['crem_number'] }}</strong>
                            </p>
                        </div>

                    @elseif($result['status'] === 'auto')
                        @if($result['crem_number'])
                            <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4 text-center">
                                <p class="text-sm text-green-800 mb-2">
                                    <strong>{{ $result['name'] }}</strong>
                                </p>
                                <p class="text-xs text-green-600 mb-3">Votre numéro CREM attribué :</p>
                                <p class="text-4xl font-bold font-mono text-green-700">{{ $result['crem_number'] }}</p>
                                <p class="text-xs text-green-600 mt-3">
                                    Notez ce numéro, il vous sera demandé le jour de l'examen.
                                </p>
                            </div>
                        @else
                            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                <p class="text-sm text-amber-800">
                                    <strong>{{ $result['name'] }}</strong>, aucun numéro ne vous a encore été attribué.
                                </p>
                                <p class="text-xs text-amber-600 mt-1">
                                    Les numéros seront attribués prochainement. Revenez plus tard.
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <a href="{{ route('public.placement') }}" class="hover:text-gray-600 underline">Voir les placements</a>
        </p>
    </div>

</body>
</html>
