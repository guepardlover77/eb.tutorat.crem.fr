<x-layouts.app :title="'Émargement'">

<div x-data="attendance()" x-init="init()">

    {{-- Summary bar --}}
    <div class="mb-4 flex items-center justify-between bg-white rounded-lg shadow px-5 py-3">
        <div class="text-sm text-gray-600">
            Total :
            <span class="font-bold text-lg text-blue-800" x-text="totalPresent"></span>
            <span class="text-gray-400">/</span>
            <span class="font-semibold text-gray-700" x-text="totalStudents"></span>
            <span class="text-gray-500 ml-1">présents</span>
        </div>
        <div class="text-xs text-gray-400" x-show="lastUpdated" x-text="'Mis à jour : ' + lastUpdated"></div>
    </div>

    {{-- Amphitheater tabs --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <template x-for="amphi in amphis" :key="amphi.id">
            <button
                @click="selectTab(amphi.id)"
                :class="activeTab === amphi.id
                    ? 'bg-blue-800 text-white shadow-md'
                    : 'bg-white text-gray-700 hover:bg-gray-100'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
            >
                <span x-text="amphi.name"></span>
                <span
                    class="text-xs px-2 py-0.5 rounded-full"
                    :class="activeTab === amphi.id ? 'bg-blue-600 text-blue-100' : 'bg-gray-200 text-gray-600'"
                    x-text="amphi.present + '/' + amphi.total"
                ></span>
            </button>
        </template>
    </div>

    {{-- Loading indicator --}}
    <div x-show="loading" class="text-center py-8 text-gray-400 text-sm">Chargement...</div>

    {{-- Students table --}}
    <div x-show="!loading" class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 w-20">Place</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 w-24">CREM</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Nom</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Prénom</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600 w-28">Présence</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="student in students" :key="student.id">
                    <tr
                        :class="student.is_present ? 'bg-green-50' : ''"
                        class="border-b last:border-0 hover:bg-gray-50 transition-colors"
                    >
                        <td class="px-4 py-2.5 text-gray-700 font-mono" x-text="student.seat_number || '—'"></td>
                        <td class="px-4 py-2.5 text-gray-700 font-mono" x-text="student.crem_number || '—'"></td>
                        <td class="px-4 py-2.5 font-medium text-gray-900" x-text="student.last_name"></td>
                        <td class="px-4 py-2.5 text-gray-700" x-text="student.first_name"></td>
                        <td class="px-4 py-2.5 text-center">
                            <button
                                @click="togglePresence(student)"
                                :class="student.is_present
                                    ? 'bg-green-500 hover:bg-green-600'
                                    : 'bg-gray-300 hover:bg-gray-400'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                :disabled="student._toggling"
                            >
                                <span
                                    :class="student.is_present ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"
                                ></span>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div x-show="students.length === 0 && !loading" class="px-4 py-8 text-center text-gray-400 text-sm">
            Aucun étudiant placé dans cet amphithéâtre.
        </div>
    </div>

    {{-- Reset button --}}
    <div class="mt-4 flex justify-end">
        <button
            @click="confirmReset = true"
            class="px-4 py-2 text-sm bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
        >
            Réinitialiser l'émargement
        </button>
    </div>

    {{-- Reset confirmation modal --}}
    <div x-show="confirmReset" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4" @click.away="confirmReset = false">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmer la réinitialisation</h3>
            <p class="text-sm text-gray-600 mb-4">
                Remettre tous les étudiants de
                <strong x-text="activeAmphiName"></strong>
                à « absent » ?
            </p>
            <div class="flex justify-end gap-3">
                <button @click="confirmReset = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Annuler</button>
                <button
                    @click="resetAmphi()"
                    class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function attendance() {
    return {
        activeTab: null,
        amphis: @json($amphis),
        students: [],
        loading: false,
        confirmReset: false,
        lastUpdated: null,
        _debounceTimers: {},

        get totalPresent() {
            return this.amphis.reduce((sum, a) => sum + a.present, 0);
        },
        get totalStudents() {
            return this.amphis.reduce((sum, a) => sum + a.total, 0);
        },
        get activeAmphiName() {
            const a = this.amphis.find(a => a.id === this.activeTab);
            return a ? a.name : '';
        },

        init() {
            if (this.amphis.length) {
                this.activeTab = this.amphis[0].id;
                this.loadStudents();
            }
        },

        selectTab(amphiId) {
            this.activeTab = amphiId;
            this.loadStudents();
        },

        async loadStudents() {
            this.loading = true;
            try {
                const res = await fetch(`/emargement/${this.activeTab}/data`);
                if (!res.ok) throw new Error(`Erreur ${res.status}`);
                const data = await res.json();
                this.students = data.students;
                const amphi = this.amphis.find(a => a.id === this.activeTab);
                if (amphi) {
                    amphi.present = data.present;
                    amphi.total = data.total;
                }
                this.lastUpdated = new Date().toLocaleTimeString('fr-FR');
            } catch (e) {
                window.showToast('Impossible de charger les étudiants.', 'error');
            }
            this.loading = false;
        },

        async togglePresence(student) {
            if (student._toggling) return;

            const key = student.id;
            if (this._debounceTimers[key]) {
                clearTimeout(this._debounceTimers[key]);
            }

            student.is_present = !student.is_present;
            const amphi = this.amphis.find(a => a.id === this.activeTab);
            if (amphi) amphi.present += student.is_present ? 1 : -1;

            this._debounceTimers[key] = setTimeout(async () => {
                student._toggling = true;
                try {
                    const res = await fetch(`/emargement/${student.id}/toggle`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    if (!res.ok) {
                        student.is_present = !student.is_present;
                        if (amphi) amphi.present += student.is_present ? 1 : -1;
                        window.showToast('Erreur lors de la mise à jour de la présence.', 'error');
                    }
                } catch {
                    student.is_present = !student.is_present;
                    if (amphi) amphi.present += student.is_present ? 1 : -1;
                    window.showToast('Connexion perdue. Réessayez.', 'error');
                }
                student._toggling = false;
                delete this._debounceTimers[key];
            }, 300);
        },

        async resetAmphi() {
            this.confirmReset = false;
            try {
                const res = await fetch(`/emargement/${this.activeTab}/reset`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                if (res.ok) {
                    this.students.forEach(s => s.is_present = false);
                    const amphi = this.amphis.find(a => a.id === this.activeTab);
                    if (amphi) amphi.present = 0;
                }
            } catch (e) {
                window.showToast('Erreur lors de la réinitialisation.', 'error');
            }
        },
    };
}
</script>

</x-layouts.app>
