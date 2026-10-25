@php
    $applicationTypes = \App\Models\GovernmentIdRequirementSet::APPLICATION_TYPES;

    $defaultApplicationType = array_key_first($applicationTypes);

    $applicationLabel = function ($case) use ($applicationTypes) {
        if ($case->application_type === 'custom') {
            return $case->application_type_custom ?: 'Other';
        }

        return $applicationTypes[$case->application_type]
            ?? \Illuminate\Support\Str::headline($case->application_type);
    };
@endphp

<section class="space-y-6">

    {{-- ADD APPLICATION TYPE --}}
    <section class="admin-panel space-y-5 p-6">

        <div>
            <h3 class="text-lg font-semibold text-slate-900">
                Add Application Type
            </h3>

            <p class="mt-1 text-sm text-slate-600">
                Choose the type of application supported for this ID.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('admin.government-ids.wizard.cases', $governmentId) }}"
            class="space-y-5"
            x-data="{
                application: @js(old('application_type', $defaultApplicationType))
            }"
        >
            @csrf

            {{-- Kept internally for the current adult-focused study scope --}}
            <input
                type="hidden"
                name="applicant_type"
                value="adult"
            >

            <div>
                <label
                    for="application_type"
                    class="mb-2 block text-sm font-medium text-slate-900"
                >
                    Application Type
                </label>

                <select
                    id="application_type"
                    name="application_type"
                    x-model="application"
                    class="admin-input"
                    required
                >
                    @foreach($applicationTypes as $key => $label)
                        <option value="{{ $key }}">
                            {{ $key === 'custom' ? 'Other' : $label }}
                        </option>
                    @endforeach
                </select>

                @error('application_type')
                    <p class="mt-1 text-sm text-red-700">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            {{-- CUSTOM APPLICATION TYPE --}}
            <div
                x-show="application === 'custom'"
                x-cloak
            >
                <label
                    for="application_type_custom"
                    class="mb-2 block text-sm font-medium text-slate-900"
                >
                    Application Type Name
                </label>

                <input
                    id="application_type_custom"
                    name="application_type_custom"
                    value="{{ old('application_type_custom') }}"
                    maxlength="255"
                    class="admin-input"
                    :disabled="application !== 'custom'"
                    :required="application === 'custom'"
                    placeholder="e.g. Replacement due to Lost ID"
                >

                @error('application_type_custom')
                    <p class="mt-1 text-sm text-red-700">
                        {{ $message }}
                    </p>
                @enderror
            </div>


            <div>
                <button
                    type="submit"
                    class="admin-primary"
                >
                    + Add Application Type
                </button>
            </div>

        </form>

    </section>


    {{-- SAVED APPLICATION TYPES --}}
    <section class="admin-panel space-y-4 p-6">

        <div class="flex flex-wrap items-end justify-between gap-3">

            <div>
                <h3 class="text-lg font-semibold text-slate-900">
                    Added Application Types
                </h3>

                <p class="mt-1 text-sm text-slate-600">
                    Requirements and application steps can be added for each type.
                </p>
            </div>

            @if($governmentId->requirementSets->isNotEmpty())
                <span
                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600"
                >
                    {{ $governmentId->requirementSets->count() }}
                    {{ \Illuminate\Support\Str::plural(
                        'type',
                        $governmentId->requirementSets->count()
                    ) }}
                    added
                </span>
            @endif

        </div>


        @forelse($governmentId->requirementSets as $case)

            @php
                $label = $applicationLabel($case);
            @endphp

            <div
                class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4"
            >

                <div class="min-w-0">

                    <p class="font-semibold text-slate-900">
                        {{ $label }}
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        {{ $case->groups->count() }}
                        {{ \Illuminate\Support\Str::plural(
                            'requirement group',
                            $case->groups->count()
                        ) }}
                    </p>

                </div>


                <div
                    x-data="{ showRemoveModal: false }"
                    class="shrink-0"
                >

                    {{-- REMOVE BUTTON --}}
                    <button
                        type="button"
                        @click="showRemoveModal = true"
                        class="group flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                        title="Remove application type"
                        aria-label="Remove {{ $label }}"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="h-[18px] w-[18px]"
                            aria-hidden="true"
                        >
                            <path d="M3 6h18" />
                            <path d="M8 6V4h8v2" />
                            <path d="M19 6l-1 14H6L5 6" />
                            <path d="M10 11v5" />
                            <path d="M14 11v5" />
                        </svg>
                    </button>


                    {{-- REMOVE CONFIRMATION MODAL --}}
                    <div
                        x-show="showRemoveModal"
                        x-cloak
                        @keydown.escape.window="showRemoveModal = false"
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="remove-application-type-title-{{ $case->id }}"
                    >

                        {{-- BACKDROP --}}
                        <div
                            class="absolute inset-0 bg-slate-900/40 backdrop-blur-[1px]"
                            @click="showRemoveModal = false"
                            x-transition.opacity
                        ></div>


                        {{-- MODAL --}}
                        <div
                            x-show="showRemoveModal"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
                            @click.stop
                        >

                            <div class="flex items-start gap-4">

                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-500"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="h-5 w-5"
                                        aria-hidden="true"
                                    >
                                        <path d="M3 6h18" />
                                        <path d="M8 6V4h8v2" />
                                        <path d="M19 6l-1 14H6L5 6" />
                                        <path d="M10 11v5" />
                                        <path d="M14 11v5" />
                                    </svg>
                                </div>


                                <div class="min-w-0">

                                    <h3
                                        id="remove-application-type-title-{{ $case->id }}"
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        Remove application type?
                                    </h3>

                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        <strong class="font-medium text-slate-800">
                                            {{ $label }}
                                        </strong>
                                        will be removed from this ID.
                                    </p>


                                    @if($case->groups->count() > 0)
                                        <p class="mt-2 text-sm leading-6 text-red-600">
                                            This application type currently has
                                            {{ $case->groups->count() }}
                                            {{ \Illuminate\Support\Str::plural(
                                                'requirement group',
                                                $case->groups->count()
                                            ) }}.

                                            Removing it may also affect its connected
                                            requirements.
                                        </p>
                                    @endif

                                </div>

                            </div>


                            {{-- ACTIONS --}}
                            <div class="mt-6 flex justify-end gap-3">

                                <button
                                    type="button"
                                    @click="showRemoveModal = false"
                                    class="admin-secondary"
                                >
                                    Cancel
                                </button>


                                <form
                                    method="POST"
                                    action="{{ route(
                                        'admin.government-ids.wizard.cases.destroy',
                                        [$governmentId, $case]
                                    ) }}"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-200"
                                    >
                                        Remove
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        @empty

            <div
                class="rounded-xl border border-dashed border-slate-300 p-6 text-center"
            >
                <p class="font-medium text-slate-700">
                    No application types added yet.
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Add the first application type using the form above.
                </p>
            </div>

        @endforelse

    </section>

</section>