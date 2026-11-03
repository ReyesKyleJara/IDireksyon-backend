export const transactionNames = { copy: 'Certified Copy', new: 'New Application', renewal: 'Renewal', replacement: 'Replacement', correction: 'Correction', retrieval: 'Retrieval', authentication: 'Authentication', custom: 'Other' };
export const methodNames = { outlet: 'In Person', online: 'Online', hybrid: 'Other / Hybrid', partner: 'Service Partner' };
export const suggestOptionName = (transaction, channel, custom = '') => {
    const title = transaction === 'custom' ? custom.trim() : transactionNames[transaction];
    return title && methodNames[channel] ? `${title} — ${methodNames[channel]}` : '';
};
export const formSnapshot = form => JSON.stringify([...new FormData(form)].filter(([name]) => name !== '_catalog_revision' && name !== '_token'));

export default function requestOptionEditor(config = {}) {
    return {
        transaction: config.transaction || '', channel: config.channel || '',
        method: ['outlet', 'online'].includes(config.channel) ? config.channel : (config.channel ? 'other' : ''),
        customTransaction: config.customTransaction || '', name: config.name || '', nameEdited: !!config.name,
        applicant: config.applicant || 'all', cases: config.cases || [],
        submitting: false, baseline: null, changed: false,
        get suggestion() { return config.implicitFirstAcquisition && this.transaction === 'new' ? (methodNames[this.channel] || '') : suggestOptionName(this.transaction, this.channel, this.customTransaction); },
        suggest() { if (!this.nameEdited) this.name = this.suggestion; },
        changeMethod() {
            this.channel = this.method === 'other' ? (['partner', 'hybrid'].includes(this.channel) ? this.channel : 'hybrid') : this.method;
            this.suggest();
        },
        get dirty() { return this.baseline !== null && formSnapshot(this.$el) !== this.baseline; },
        init() {
            this.suggest();
            this.$nextTick(() => { this.baseline = formSnapshot(this.$el); });
            this.beforeUnload = event => { if (!this.submitting && this.dirty) { event.preventDefault(); event.returnValue = ''; } };
            window.addEventListener('beforeunload', this.beforeUnload);
        },
        markChanged() { this.$nextTick(() => { this.changed = this.dirty; }); },
        cancel(event) {
            if (this.dirty && !window.confirm('Discard unsaved request option changes?')) event.preventDefault();
            else this.submitting = true;
        },
        destroy() { window.removeEventListener('beforeunload', this.beforeUnload); },
    };
}

// Existing Fees/Offices keep their own editors; only warn before leaving changed forms.
export function catalogStepChanges() {
    return {
        baseline: null, submitting: false,
        init() {
            this.$nextTick(() => { this.baseline = formSnapshot(this.$el); });
            this.beforeUnload = event => {
                if (!this.submitting && this.baseline !== null && formSnapshot(this.$el) !== this.baseline) { event.preventDefault(); event.returnValue = ''; }
            };
            this.submit = event => {
                this.submitting = true;
                queueMicrotask(() => { if (event.defaultPrevented) this.submitting = false; });
            };
            this.$el.addEventListener('submit', this.submit);
            window.addEventListener('beforeunload', this.beforeUnload);
        },
        destroy() { this.$el.removeEventListener('submit', this.submit); window.removeEventListener('beforeunload', this.beforeUnload); },
    };
}

const clone = value => JSON.parse(JSON.stringify(value));
export const providerCategory = channel => ['outlet', 'in_person', 'in-person', 'in person', 'physical'].includes(channel) ? 'outlet' : (['online', 'web', 'website', 'portal'].includes(channel) ? 'online' : (channel ? 'hybrid' : 'unconfigured'));

export function inlineRequestMethods(config) {
    return {
        methods: config.methods, blank: config.blank, active: null, draft: null, baseline: '', saving: false, errors: [], message: '',
        get dirty() { return this.draft !== null && JSON.stringify(this.draft) !== this.baseline; },
        category(method) { return method.channel.provider?.trim() ? providerCategory(method.channel.channel) : 'unconfigured'; },
        providers(category) { return this.methods.filter(method => this.category(method) === category); },
        init() {
            this.leave = event => { if (this.dirty && !this.saving) { event.preventDefault(); event.returnValue = ''; } };
            window.addEventListener('beforeunload', this.leave);
            this.navigate = event => {
                if (!event.target.closest('a[href]') || !this.dirty || this.saving) return;
                if (!window.confirm('Discard unsaved request method changes?')) event.preventDefault();
                else { this.draft = null; this.active = null; }
            };
            document.addEventListener('click', this.navigate);
        },
        destroy() { window.removeEventListener('beforeunload', this.leave); document.removeEventListener('click', this.navigate); },
        open(method = null) {
            const key = method?.id ?? 'new';
            if (this.active === key) return this.cancel();
            if (this.dirty && !window.confirm('Discard unsaved request method changes?')) return;
            this.active = key; this.draft = clone(method ?? this.blank); this.baseline = JSON.stringify(this.draft); this.errors = []; this.message = '';
        },
        cancel() {
            if (this.saving || (this.dirty && !window.confirm('Discard unsaved request method changes?'))) return;
            this.active = null; this.draft = null; this.errors = [];
        },
        feeSummary() {
            const selected = this.draft.fees.filter(fee => !fee.id || this.selectedFee(fee));
            if (!selected.length) return 'No fee details added';
            const amount = value => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(Number(value));
            return selected.map(fee => {
                const price = fee.type === 'free' ? 'Free' : fee.type === 'fixed' ? (fee.amount_min === '' || fee.amount_min == null ? 'Amount pending' : amount(fee.amount_min)) : fee.type === 'range' ? `${amount(fee.amount_min)}–${amount(fee.amount_max)}` : fee.type === 'options' ? 'Multiple fee options' : 'Varies';
                return `${fee.label || 'New fee'} · ${price}`;
            }).join('; ');
        },
        selectedFee(fee) { return this.draft.channel.fee_ids === null || this.draft.channel.fee_ids.includes(fee.id); },
        toggleFee(fee, selected) {
            if (this.draft.channel.fee_ids === null) this.draft.channel.fee_ids = this.draft.fees.filter(row => row.id).map(row => row.id);
            this.draft.channel.fee_ids = this.draft.channel.fee_ids.filter(id => id !== fee.id);
            if (selected) this.draft.channel.fee_ids.push(fee.id);
        },
        editFees(container) {
            this.$nextTick(() => {
                const controls = [...container.querySelectorAll('input, textarea')]
                    .filter(input => !(input.matches ? input.matches(':disabled') : input.disabled));
                const target = controls.find(input => input.type === 'text' && !input.value.trim())
                    || controls.find(input => input.type === 'number')
                    || controls.find(input => input.type === 'text')
                    || container.querySelector('button[data-add-fee]');
                target?.focus();
                target?.scrollIntoView?.({block: 'nearest'});
            });
        },
        removeFee(index) {
            this.draft.fees.splice(index, 1);
            this.draft.channel.fee_ids = this.draft.fees.filter(fee => fee.id).map(fee => fee.id);
        },
        addFee() { this.draft.fees.push({ label: '', type: 'varies', amount_min: null, amount_max: null, currency: 'PHP', notes: '', is_optional: false, options: [], shared: false }); },
        async remove() {
            if (this.saving || !this.draft.id || !window.confirm('Delete this request method? Saved requirements and application steps prevent deletion.')) return;
            if (this.dirty && !window.confirm('Discard unsaved changes and delete this method?')) return;
            this.saving = true; this.errors = [];
            try {
                const form = this.$el.querySelector('form');
                const payload = new FormData(form);
                payload.set('_method', 'DELETE');
                payload.set('return_wizard', config.wizard || 'edit');
                const response = await fetch(config.deleteUrl.replace('__ID__', this.draft.id), {method: 'POST', headers: {Accept: 'application/json'}, body: payload});
                if (!response.ok) {
                    const result = await response.json();
                    this.errors = Object.values(result.errors || {error: [result.message || 'Unable to delete.']}).flat(); return;
                }
                this.baseline = JSON.stringify(this.draft); window.location.assign(config.returnUrl);
            } catch { this.errors = ['Unable to delete. Your edits are still here.']; }
            finally { this.saving = false; }
        },
        async save(form) {
            if (this.saving || !form.reportValidity()) return;
            this.saving = true; this.errors = [];
            const payload = new FormData(form);
            payload.set('inline_payload', JSON.stringify(this.draft));
            try {
                const response = await fetch(config.saveUrl, { method: 'POST', headers: { Accept: 'application/json' }, body: payload });
                const result = await response.json();
                if (!response.ok) { this.errors = Object.values(result.errors || {error: [result.message || 'Unable to save. Try again.']}).flat(); return; }
                // Reload server-rendered cards so associations, shared fees, and revision tokens are current.
                this.baseline = JSON.stringify(this.draft);
                window.location.assign(config.returnUrl);
            } catch { this.errors = ['Unable to save. Your edits are still here. Check your connection and try again.']; }
            finally { this.saving = false; }
        },
    };
}
