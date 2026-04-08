{{-- resources/views/public/inscriptions.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — Examen Blanc</title>

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
            <span class="text-base font-bold tracking-tight">CREM · Inscription Examen Blanc S2</span>
        </div>
    </nav>

    <div class="max-w-lg mx-auto mt-12 px-4">

        @if(!($helloassoConfigured ?? false))
            <div class="bg-white rounded-2xl shadow-sm border p-8 text-center">
                <h1 class="text-xl font-bold text-gray-900 mb-3">Inscriptions indisponibles</h1>
                <p class="text-sm text-gray-600">Les inscriptions ne sont pas ouvertes pour le moment. Contactez l'association pour plus d'informations.</p>
            </div>
        @elseif(session('tier_label'))
            {{-- Étape 2 : paiement --}}
            <div class="bg-white rounded-2xl shadow-sm border p-8">
                <h1 class="text-xl font-bold text-gray-900 mb-1">Votre inscription</h1>
                <p class="text-sm text-gray-500 mb-6">Tarif détecté automatiquement selon votre profil.</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-6">
                    <p class="text-sm text-blue-800 font-medium">{{ session('tier_label') }}</p>
                </div>

                <p class="text-sm text-gray-600 mb-4">
                    Complétez votre inscription et votre paiement via le formulaire ci-dessous.
                </p>

                <iframe
                    id="helloasso-widget"
                    src="https://www.helloasso.com/associations/{{ config('services.helloasso.org_slug') }}/evenements/{{ session('form_slug') }}/widget"
                    style="width:100%;height:750px;border:none;"
                    allow="payment"
                    loading="lazy"
                ></iframe>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                <a href="{{ route('inscriptions.index') }}" class="hover:text-gray-600 underline">
                    Recommencer avec un autre profil
                </a>
            </p>

        @else
            {{-- Étape 1 : identification --}}
            <div class="bg-white rounded-2xl shadow-sm border p-8">
                <h1 class="text-xl font-bold text-gray-900 mb-1">Inscription à l'examen blanc</h1>
                <p class="text-sm text-gray-500 mb-6">
                    Saisissez votre numéro CREM pour que nous détections votre tarif automatiquement.
                </p>

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('inscriptions.check-tier') }}" class="space-y-5" id="inscription-form">
                    @csrf

                    <div>
                        <label for="crem_number" class="block text-sm font-medium text-gray-700 mb-1">
                            Numéro CREM
                        </label>
                        <input type="text" name="crem_number" id="crem_number"
                               value="{{ old('crem_number') }}"
                               placeholder="Ex : 12345"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#CC2929] focus:border-transparent">
                    </div>

                    <div id="no-crem-section" class="hidden">
                        <p class="text-sm font-medium text-gray-700 mb-2">Votre niveau LAS :</p>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="las_level" value="las1"
                                       class="text-[#CC2929] focus:ring-[#CC2929]"
                                       {{ old('las_level') === 'las1' ? 'checked' : '' }}>
                                LAS 1
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="las_level" value="las2"
                                       class="text-[#CC2929] focus:ring-[#CC2929]"
                                       {{ old('las_level') === 'las2' ? 'checked' : '' }}>
                                LAS 2 / LAS 3
                            </label>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" id="no-crem-checkbox"
                               class="rounded text-[#CC2929] focus:ring-[#CC2929]"
                               {{ old('las_level') ? 'checked' : '' }}>
                        Je n'ai pas de numéro CREM
                    </label>

                    <button type="submit"
                            class="w-full px-4 py-2.5 bg-[#CC2929] hover:bg-[#A81E1E] text-white font-medium rounded-lg transition">
                        Continuer
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                <a href="{{ route('public.placement') }}" class="hover:text-gray-600 underline">Voir les placements</a>
            </p>
        @endif

    </div>

    <script>
        const checkbox = document.getElementById('no-crem-checkbox');
        if (checkbox) {
            const noCremSection = document.getElementById('no-crem-section');
            const cremInput = document.getElementById('crem_number');

            function toggleNoCrem() {
                if (checkbox.checked) {
                    noCremSection.classList.remove('hidden');
                    cremInput.value = '';
                    cremInput.disabled = true;
                } else {
                    noCremSection.classList.add('hidden');
                    cremInput.disabled = false;
                }
            }

            checkbox.addEventListener('change', toggleNoCrem);
            toggleNoCrem();
        }
    </script>

</body>
</html>
