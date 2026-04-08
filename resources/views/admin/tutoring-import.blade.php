{{-- resources/views/admin/tutoring-import.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import liste tutorat — Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Figtree', 'ui-sans-serif', 'system-ui'] } } }
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-100 min-h-screen">

    <nav class="bg-[#CC2929] text-white shadow-md">
        <div class="max-w-2xl mx-auto px-5 py-3 flex items-center gap-3">
            <img src="/logo-tutorat.png" alt="Tutorat Poitiers" class="h-8 w-8 rounded-full bg-white p-0.5 shrink-0">
            <span class="text-base font-bold tracking-tight">Admin · Import liste tutorat S2</span>
        </div>
    </nav>

    <div class="max-w-lg mx-auto mt-12 px-4">
        <div class="bg-white rounded-2xl shadow-sm border p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Import de la liste tutorat S2</h1>

            {{-- État actuel --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-6 text-sm text-gray-600">
                @if($count > 0)
                    <p><strong>{{ $count }}</strong> membres dans la liste actuelle.</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Dernier import : {{ \Carbon\Carbon::parse($lastImport)->format('d/m/Y à H:i') }}
                    </p>
                @else
                    <p>Aucune liste chargée pour le moment.</p>
                @endif
            </div>

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-5">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-5">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.tutoring-import.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label for="excel" class="block text-sm font-medium text-gray-700 mb-1">
                        Fichier Excel (.xlsx ou .xls)
                    </label>
                    <p class="text-xs text-gray-500 mb-2">
                        La première ligne doit contenir les en-têtes. La colonne contenant "CREM" est obligatoire. Les colonnes "Prénom" et "Nom" sont optionnelles.
                    </p>
                    <input type="file" name="excel" id="excel" accept=".xlsx,.xls" required
                           class="w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#CC2929] file:text-white hover:file:bg-[#A81E1E] file:cursor-pointer">
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-700">
                    <strong>Attention :</strong> L'import remplace intégralement la liste précédente.
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 bg-[#CC2929] hover:bg-[#A81E1E] text-white font-medium rounded-lg transition">
                    Importer la liste
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-600 underline">← Retour au tableau de bord</a>
        </p>
    </div>

</body>
</html>
