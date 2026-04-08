<x-layouts.app title="Récupération — Options">
    @php
        $optionColors = [
            'LAS 1 - NON-ADHERENT'              => 'orange',
            'LAS 2/3 - NON-ADHERENT'            => 'amber',
            'LAS 1 - ADHERENT CREM SANS TUTORAT'  => 'blue',
            'LAS 2/3 - ADHERENT CREM SANS TUTORAT' => 'indigo',
            'LAS 1 - ADHERENT'                  => 'green',
            'LAS 2/3 - ADHERENT'                => 'teal',
        ];
        $optionPrices = [
            'LAS 1 - NON-ADHERENT'              => '22 €',
            'LAS 2/3 - NON-ADHERENT'            => '14 €',
            'LAS 1 - ADHERENT CREM SANS TUTORAT'  => '14 €',
            'LAS 2/3 - ADHERENT CREM SANS TUTORAT' => '8 €',
            'LAS 1 - ADHERENT'                  => 'Gratuit',
            'LAS 2/3 - ADHERENT'                => 'Gratuit',
        ];
        $total = collect($groups)->sum(fn($g) => $g->count()) + $noOption->count();
    @endphp

    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Récupération — Options choisies</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $total }} inscription(s) avec le tarif "Récupération sans passer l'épreuve"</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">← Retour</a>
        </div>

        {{-- Vue d'ensemble --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50">
                <h2 class="font-semibold text-gray-700 text-sm">Vue d'ensemble</h2>
            </div>
            <div class="divide-y">
                @foreach($options as $option)
                @php $color = $optionColors[$option]; $count = $groups[$option]->count(); @endphp
                <div class="px-5 py-2.5 flex items-center justify-between">
                    <span class="text-sm text-gray-700">{{ $option }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-{{ $color }}-600 bg-{{ $color }}-50 border border-{{ $color }}-200 px-2 py-0.5 rounded">{{ $optionPrices[$option] }}</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $count }}</span>
                    </div>
                </div>
                @endforeach
                <div class="px-5 py-2.5 flex items-center justify-between bg-gray-50">
                    <span class="text-sm text-gray-500 italic">Sans option sélectionnée</span>
                    <span class="text-sm font-semibold text-gray-600">{{ $noOption->count() }}</span>
                </div>
                <div class="px-5 py-2.5 flex items-center justify-between font-medium">
                    <span class="text-sm text-gray-800">Total</span>
                    <span class="text-sm font-bold text-gray-900">{{ $total }}</span>
                </div>
            </div>
        </div>

        {{-- Info banner --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
            Ces étudiants ne passent pas l'épreuve. L'option sélectionnée détermine le montant remboursé ou la cotisation applicable.
            Un re-sync est nécessaire pour peupler les options si la colonne vient d'être ajoutée.
        </div>

        {{-- Tables per option --}}
        @foreach($options as $option)
            @php
                $students = $groups[$option];
                $color = $optionColors[$option];
                $price = $optionPrices[$option];
            @endphp

            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between
                    bg-{{ $color }}-50 border-{{ $color }}-100">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                            bg-{{ $color }}-100 text-{{ $color }}-800 border border-{{ $color }}-200">
                            {{ $students->count() }} étudiant(s)
                        </span>
                        <span class="font-semibold text-{{ $color }}-900 text-sm">{{ $option }}</span>
                    </div>
                    <span class="text-xs font-medium text-{{ $color }}-600 bg-{{ $color }}-100 px-2 py-0.5 rounded">
                        {{ $price }}
                    </span>
                </div>

                @if($students->isEmpty())
                    <p class="px-5 py-4 text-sm text-gray-400 italic">Aucun étudiant pour cette option.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-400 uppercase tracking-wide">
                                <tr>
                                    <th class="px-4 py-2.5 text-left">N° CREM</th>
                                    <th class="px-4 py-2.5 text-left">Nom</th>
                                    <th class="px-4 py-2.5 text-left">Prénom</th>
                                    <th class="px-4 py-2.5 text-left">Email</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($students as $s)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-2.5 font-mono font-semibold text-gray-700">{{ $s->crem_number ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ strtoupper($s->last_name) }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $s->first_name }}</td>
                                    <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $s->email }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- No option table --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-3 border-b flex items-center justify-between bg-gray-50 border-gray-100">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                        bg-gray-100 text-gray-600 border border-gray-200">
                        {{ $noOption->count() }} étudiant(s)
                    </span>
                    <span class="font-semibold text-gray-700 text-sm">Sans option sélectionnée</span>
                </div>
                <a href="{{ route('export.recuperation-no-option-emails') }}"
                   class="text-xs px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white font-medium rounded-lg transition">
                    Exporter les mails ({{ $noOption->count() }})
                </a>
            </div>

            @if($noOption->isEmpty())
                <p class="px-5 py-4 text-sm text-gray-400 italic">Aucun étudiant dans ce cas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-medium text-gray-400 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-2.5 text-left">N° CREM</th>
                                <th class="px-4 py-2.5 text-left">Nom</th>
                                <th class="px-4 py-2.5 text-left">Prénom</th>
                                <th class="px-4 py-2.5 text-left">Email</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($noOption as $s)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5 font-mono font-semibold text-gray-700">{{ $s->crem_number ?? '—' }}</td>
                                <td class="px-4 py-2.5 font-medium text-gray-900">{{ strtoupper($s->last_name) }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ $s->first_name }}</td>
                                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $s->email }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-layouts.app>
