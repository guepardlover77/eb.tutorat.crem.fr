@php
    $allTiers = $tiers ?? collect([
        'LAS 1 - INSCRITS au Tutorat',
        'LAS 1 - INSCRITS AU CREM SANS le Tutorat',
        'LAS 1 - NON INSCRITS au Tutorat',
        'LAS 2/3 - INSCRITS au Tutorat',
        'LAS 2/3 - INSCRITS AU CREM SANS le Tutorat',
        'LAS 2/3 - NON INSCRITS au Tutorat',
        "Récupération sans passer l'épreuve",
        'UE3 + UE4 (LAS 1)',
    ]);

    $recoveryOptions = [
        'LAS 1 - NON-ADHERENT',
        'LAS 2/3 - NON-ADHERENT',
        'LAS 1 - ADHERENT CREM SANS TUTORAT',
        'LAS 2/3 - ADHERENT CREM SANS TUTORAT',
        'LAS 1 - ADHERENT',
        'LAS 2/3 - ADHERENT',
    ];
@endphp

<dialog id="shared-assign-modal"
        class="rounded-xl shadow-xl border p-0 backdrop:bg-black/30 w-full max-w-lg">
    <div class="px-6 py-4 border-b">
        <h3 id="modal-title" class="font-semibold text-gray-800"></h3>
    </div>
    <form id="modal-form" method="POST" class="px-6 py-4 space-y-4">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Prénom</label>
                <input type="text" name="first_name" id="modal-first-name" required
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nom</label>
                <input type="text" name="last_name" id="modal-last-name" required
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" id="modal-email" required
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">N° CREM</label>
                <input type="text" name="crem_number" id="modal-crem"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tarif</label>
                <select name="tier_name" id="modal-tier"
                        class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach($allTiers as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="modal-recovery-row" class="hidden">
            <label class="block text-xs font-medium text-gray-600 mb-1">Option de récupération</label>
            <select name="recovery_option" id="modal-recovery-option"
                    class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">— Aucune —</option>
                @foreach($recoveryOptions as $ro)
                    <option value="{{ $ro }}">{{ $ro }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="is_excluded" value="0">
            <input type="checkbox" name="is_excluded" id="modal-excluded" value="1"
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label for="modal-excluded" class="text-sm text-gray-700">Exclu (récupération)</label>
        </div>

        <hr class="border-gray-100">

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Amphithéâtre</label>
                <select name="amphitheater_id" id="modal-amphi"
                        class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">— Aucun —</option>
                    @foreach($amphitheaters as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">N° de place</label>
                <input type="text" name="seat_number" id="modal-seat"
                       placeholder="ex: 42 ou Table 1"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="button" id="modal-delete-btn"
                    class="px-3 py-2 text-sm text-red-600 hover:text-white hover:bg-red-600 border border-red-200 rounded-lg transition">
                Supprimer
            </button>
            <div class="flex gap-2">
                <button type="button" id="modal-cancel"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border rounded-lg hover:bg-gray-50">
                    Annuler
                </button>
                <button type="submit"
                        class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
                    Enregistrer
                </button>
            </div>
        </div>
    </form>

    <form id="modal-delete-form" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</dialog>

<script>
(function () {
    const modal       = document.getElementById('shared-assign-modal');
    const form        = document.getElementById('modal-form');
    const deleteForm  = document.getElementById('modal-delete-form');
    const title       = document.getElementById('modal-title');
    const firstName   = document.getElementById('modal-first-name');
    const lastName    = document.getElementById('modal-last-name');
    const email       = document.getElementById('modal-email');
    const crem        = document.getElementById('modal-crem');
    const tierSel     = document.getElementById('modal-tier');
    const excludedCb  = document.getElementById('modal-excluded');
    const recoveryRow = document.getElementById('modal-recovery-row');
    const recoverySel = document.getElementById('modal-recovery-option');
    const amphiSel    = document.getElementById('modal-amphi');
    const seatIn      = document.getElementById('modal-seat');
    const deleteBtn   = document.getElementById('modal-delete-btn');
    const RECUP_TIER  = "Récupération sans passer l\u0027épreuve";

    function toggleRecovery() {
        const show = tierSel.value === RECUP_TIER;
        recoveryRow.classList.toggle('hidden', !show);
        if (!show) recoverySel.value = '';
    }

    tierSel.addEventListener('change', toggleRecovery);

    document.querySelectorAll('[data-assign-btn]').forEach(btn => {
        btn.addEventListener('click', () => {
            form.action     = btn.dataset.url;
            deleteForm.action = btn.dataset.deleteUrl;
            title.textContent = 'Modifier — ' + btn.dataset.studentName;
            firstName.value = btn.dataset.firstName;
            lastName.value  = btn.dataset.lastName;
            email.value     = btn.dataset.email;
            crem.value      = btn.dataset.crem;
            tierSel.value   = btn.dataset.tier;
            excludedCb.checked = btn.dataset.excluded === '1';
            recoverySel.value  = btn.dataset.recoveryOption;
            amphiSel.value  = btn.dataset.amphiId;
            seatIn.value    = btn.dataset.seat;
            toggleRecovery();
            modal.showModal();
        });
    });

    deleteBtn.addEventListener('click', () => {
        if (confirm('Supprimer cet étudiant ? Cette action est irréversible.')) {
            deleteForm.submit();
        }
    });

    document.getElementById('modal-cancel').addEventListener('click', () => modal.close());
    modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });
})();
</script>
