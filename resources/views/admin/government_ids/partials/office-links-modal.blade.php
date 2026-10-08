<section class="admin-panel p-6" aria-label="Linked Offices" x-data="governmentIdOffices(@js($officeOptions), @js($initialLinks))">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Linked Offices</h2>
            <p class="mt-1 text-sm text-slate-500">Branches that handle applications for this ID.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" class="admin-secondary" @click="open($event)">Edit offices</button>
            <button type="button" class="admin-primary" @click="openNewOffice($event, @js(route('admin.offices.create', ['embedded' => 1])))">+ Add office</button>
        </div>
    </div>
    <input type="hidden" name="office_links_present" value="1">
    @foreach($errors->get('office_links*') as $messages)
        @foreach((array) $messages as $message)
            <p class="mb-2 text-sm text-red-700" role="alert">{{ $message }}</p>
        @endforeach
    @endforeach
    <p x-show="links.length === 0" class="text-sm text-slate-500">Choose the branches where residents can apply.</p>
    <div class="space-y-3">
        <template x-for="(link, index) in links" :key="index">
            <article class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-medium text-slate-900" x-text="officeFor(link.office_id)?.name ?? 'Office no longer available'"></h3>
                <p x-show="officeFor(link.office_id)?.location !== 'Location not yet researched'" class="mt-1 text-xs text-slate-500" x-text="officeFor(link.office_id)?.location"></p>
                <p x-show="services(link)" class="mt-2 text-xs text-slate-600" x-text="services(link)"></p>
                <p x-show="link.service_notes" class="mt-1 whitespace-pre-line text-xs text-slate-500" x-text="link.service_notes"></p>
                <input type="hidden" :name="'office_links[' + index + '][office_id]'" :value="link.office_id">
                @foreach(['new_application_status', 'renewal_status', 'replacement_status', 'service_notes'] as $field)
                    <input type="hidden" :name="'office_links[' + index + '][{{ $field }}]'" :value="link.{{ $field }}">
                @endforeach
            </article>
        </template>
    </div>
    <p x-show="changed" role="status" class="mt-3 text-xs text-blue-800">Offices updated in this form. Click Save Changes to save them.</p>
    <style>#offices-dialog::backdrop { background: rgb(15 23 42 / .5); }</style>
    <template x-teleport="body">
        <dialog id="offices-dialog" x-ref="dialog" aria-labelledby="offices-dialog-title" @cancel.prevent="cancel()"
            class="m-auto rounded-2xl border-0 bg-white p-0 shadow-xl"
            style="width: min(640px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem);">
            <div class="flex flex-col" style="max-height: calc(100dvh - 2rem);">
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 id="offices-dialog-title" class="text-lg font-semibold text-slate-900" x-text="creatingOffice ? 'Add office' : 'Edit linked offices'"></h2>
                        <p class="mt-1 text-sm text-slate-500">Choose branches and record the services they handle.</p>
                    </div>
                    <button type="button" class="text-2xl text-slate-500" aria-label="Close offices" @click="cancel()">×</button>
                </header>
                <div x-show="!creatingOffice" class="min-h-0 space-y-4 overflow-y-auto px-6 py-5">
                    <p x-ref="error" x-show="error" x-text="error" tabindex="-1" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
                    @if($offices->isEmpty())
                        <p class="text-sm text-slate-600">No offices yet. Create a branch draft first, then return here to link it.</p>
                        <button type="button" class="admin-secondary" @click="createOffice(@js(route('admin.offices.create', ['embedded' => 1])))">Add office</button>
                        <p class="text-xs text-slate-500">The saved office will appear here automatically.</p>
                    @else
                        <label class="block text-sm font-medium">Select a branch
                            <select x-model="selected" class="admin-input mt-2">
                                <option value="">Choose an office</option>
                                <template x-for="office in options" :key="office.id">
                                    <option :value="office.id" :disabled="draft.some(link => link.office_id === office.id)" x-text="office.name + ' — ' + office.location"></option>
                                </template>
                            </select>
                        </label>
                        <button type="button" class="admin-secondary" @click="addOffice()" :disabled="!selected || draft.length >= 100">Link selected office</button>
                        <button type="button" class="admin-secondary" @click="createOffice(@js(route('admin.offices.create', ['embedded' => 1])))">+ Create new office</button>
                    @endif
                    <template x-for="(link, index) in draft" :key="link.office_id">
                        <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold" x-text="officeFor(link.office_id)?.name ?? 'Office no longer available'"></h3>
                                    <p class="mt-1 text-xs text-slate-500" x-text="officeFor(link.office_id)?.status"></p>
                                </div>
                                <button type="button" class="text-xs text-red-700" @click="draft.splice(index, 1)">Remove</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach(['new_application_status' => 'New application', 'renewal_status' => 'Renewal', 'replacement_status' => 'Replacement'] as $field => $label)
                                    <label class="block text-sm font-medium">{{ $label }}
                                        <select x-model="link.{{ $field }}" class="admin-input mt-1">
                                            <option value="unknown">Not specified</option>
                                            <option value="available">Available</option>
                                            <option value="unavailable">Not available</option>
                                        </select>
                                    </label>
                                @endforeach
                            </div>
                            <label class="block text-sm font-medium">Service notes (optional)
                                <textarea x-model="link.service_notes" rows="2" maxlength="10000" class="admin-input mt-1"></textarea>
                            </label>
                        </div>
                    </template>
                    <p class="text-xs text-slate-500">Removing a branch only unlinks it from this ID. Draft and inactive offices remain hidden from residents.</p>
                </div>
                <template x-if="creatingOffice">
                    <div class="min-h-0 overflow-y-auto">
                        <p class="px-6 py-3 text-xs text-slate-500">Save Draft creates the office in the directory. Its link to this ID is saved separately.</p>
                        <iframe x-ref="createFrame" :src="creationUrl" title="Create office" class="w-full border-0" style="height: min(65vh, 650px);"></iframe>
                        <div class="px-6 py-3"><button type="button" @click="backToLinks()" class="admin-secondary">Back to linked offices</button></div>
                    </div>
                </template>
                <footer x-show="!creatingOffice" class="shrink-0 border-t border-slate-200 px-6 py-4">
                    <p class="mb-3 text-xs text-slate-500">Apply offices, then click Save Changes on the page to save.</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="admin-secondary" @click="cancel()">Cancel</button>
                        <button type="button" class="admin-primary" @click="apply()">Apply offices</button>
                    </div>
                </footer>
            </div>
        </dialog>
    </template>
</section>
