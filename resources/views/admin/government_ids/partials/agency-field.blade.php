<div
    x-data="agencySelector(
        @js(
            $agencies->map(fn ($agency) => [
                'id' => $agency->id,
                'name' => $agency->name,
                'acronym' => $agency->acronym,
            ])->values()
        ),
        @js((string) old('agency_id', $selectedAgencyId ?? '')),
        @js(route('admin.agencies.store')),
        @js(csrf_token())
    )"
>

    <label for="agency_id" class="mb-2 block text-sm font-medium">
        Issuing Agency
    </label>

    <div class="flex items-center gap-3">

        <select
            id="agency_id"
            name="agency_id"
            x-model="selected"
            class="admin-input"
        >
            <option value="">Select issuing agency</option>

            <template x-for="agency in agencies" :key="agency.id">
                <option
                    :value="String(agency.id)"
                    x-text="
                        agency.acronym
                            ? `${agency.name} (${agency.acronym})`
                            : agency.name
                    "
                ></option>
            </template>
        </select>

        <button
            type="button"
            class="admin-secondary whitespace-nowrap"
            @click="openModal()"
        >
            + Add Agency
        </button>

    </div>

    @error('agency_id')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror


    {{-- ADD AGENCY MODAL --}}
    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
        @keydown.escape.window="closeModal()"
    >

        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl"
            @click.outside="closeModal()"
        >

            <div class="mb-6">

                <h3 class="text-lg font-semibold text-slate-900">
                    Add Issuing Agency
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    The new agency will be saved to the agency directory and selected automatically.
                </p>

            </div>


            <div class="space-y-5">

                <div>

                    <label class="mb-2 block text-sm font-medium">
                        Agency Name *
                    </label>

                    <input
                        type="text"
                        x-model="form.name"
                        class="admin-input"
                        placeholder="e.g. Department of Foreign Affairs"
                    >

                </div>


                <div>

                    <label class="mb-2 block text-sm font-medium">
                        Acronym
                    </label>

                    <input
                        type="text"
                        x-model="form.acronym"
                        class="admin-input"
                        placeholder="e.g. DFA"
                    >

                </div>


                <div>

                    <label class="mb-2 block text-sm font-medium">
                        Official Website
                    </label>

                    <input
                        type="url"
                        x-model="form.official_website"
                        class="admin-input"
                        placeholder="https://..."
                    >

                </div>


                <div
                    x-show="errorMessage"
                    class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                    x-text="errorMessage"
                ></div>

            </div>


            <div class="mt-7 flex justify-end gap-3">

                <button
                    type="button"
                    class="admin-secondary"
                    @click="closeModal()"
                    :disabled="saving"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="admin-primary"
                    @click="saveAgency()"
                    :disabled="saving"
                >
                    <span x-show="!saving">
                        Add Agency
                    </span>

                    <span x-show="saving">
                        Saving...
                    </span>
                </button>

            </div>

        </div>
    </div>

</div>


<script>
    function agencySelector(
        initialAgencies,
        initialSelected,
        storeUrl,
        csrfToken
    ) {
        return {
            agencies: initialAgencies,
            selected: initialSelected,

            showModal: false,
            saving: false,
            errorMessage: '',

            form: {
                name: '',
                acronym: '',
                official_website: '',
            },

            openModal() {
                this.errorMessage = '';
                this.showModal = true;
            },

            closeModal() {
                if (this.saving) {
                    return;
                }

                this.showModal = false;
                this.errorMessage = '';

                this.form = {
                    name: '',
                    acronym: '',
                    official_website: '',
                };
            },

            async saveAgency() {
                this.errorMessage = '';

                if (!this.form.name.trim()) {
                    this.errorMessage = 'Agency name is required.';
                    return;
                }

                this.saving = true;

                try {
                    const response = await fetch(storeUrl, {
                        method: 'POST',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },

                        body: JSON.stringify(this.form),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data.errors) {
                            const firstError = Object.values(data.errors)[0];

                            this.errorMessage = Array.isArray(firstError)
                                ? firstError[0]
                                : firstError;

                            return;
                        }

                        this.errorMessage =
                            data.message ?? 'Unable to add agency.';

                        return;
                    }

                    this.agencies.push(data.agency);

                    this.agencies.sort((a, b) =>
                        a.name.localeCompare(b.name)
                    );

                    this.selected = String(data.agency.id);

                    this.saving = false;
                    this.closeModal();

                } catch (error) {
                    this.errorMessage =
                        'Something went wrong while adding the agency.';
                } finally {
                    this.saving = false;
                }
            },
        };
    }
</script>