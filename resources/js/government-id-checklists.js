const copy = value => JSON.parse(JSON.stringify(value));

export default function governmentIdChecklists(config) {
    return {
        checklists: config.checklists || [],
        deferred: !!config.deferred,
        ready: false,
        restoreError: false,
        formError: '',
        submitting: false,
        options: config.options,
        formats: config.formats,
        conditions: config.conditions,
        applications: config.applications,
        applicants: config.applicants,
        draft: null,
        editing: null,
        editingIndex: null,
        qualifications: config.qualifications,
        editable: config.editable,
        baseline: '',
        saving: false,
        deleting: null,
        errors: [],
        sequence: 0,
        opener: null,
        beforeUnload: null,
        previousOverflow: null,
        init() {
            if (this.deferred) {
                if (config.oldPayload !== null && config.oldPayload !== undefined) {
                    try {
                        const rows = JSON.parse(config.oldPayload);
                        if (!Array.isArray(rows) || rows.length > 50) throw new Error();
                        const keys = new Set();
                        this.checklists = rows.map(row => {
                            if (!row || typeof row.client_key !== 'string' || !/^[a-zA-Z0-9_-]{1,80}$/.test(row.client_key)
                                || keys.has(row.client_key) || !Array.isArray(row.groups)) throw new Error();
                            keys.add(row.client_key);
                            return { ...row, label: this.checklistLabel(row), groups: row.groups.map(group => this.decorate(group)) };
                        });
                    } catch {
                        this.checklists = [];
                        this.restoreError = true;
                        this.formError = 'The submitted requirements could not be restored. Your original submission is retained; review it before creating the ID.';
                    }
                }
                this.form = this.$el.closest('form');
                this.submitHandler = event => {
                    if (this.draft || this.restoreError) {
                        event.preventDefault();
                        this.formError = this.restoreError ? this.formError : 'Apply or cancel the open checklist before creating the ID.';
                        return;
                    }
                    if (this.$refs.pendingPayload) this.$refs.pendingPayload.value = this.creationPayload;
                    this.submitting = true;
                    queueMicrotask(() => { if (event.defaultPrevented) this.submitting = false; });
                };
                this.form?.addEventListener('submit', this.submitHandler);
                this.$nextTick(() => this.checklists.forEach(row => this.$dispatch('guide-scenario-changed', row)));
            }
            this.ready = true;
            this.beforeUnload = event => {
                if (!this.submitting && (this.dirty() || this.saving || (this.deferred && this.checklists.length > 0))) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.beforeUnload);
        },
        destroy() {
            window.removeEventListener('beforeunload', this.beforeUnload);
            this.form?.removeEventListener('submit', this.submitHandler);
            if (this.$refs.dialog?.open) this.$refs.dialog.close();
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
        },
        get creationPayload() {
            if (this.restoreError) return config.oldPayload;
            return JSON.stringify(this.checklists.map(row => ({ client_key: row.client_key, ...this.payload(row) })));
        },
        discardUnreadableDrafts() {
            if (!window.confirm('Discard the unreadable requirement submission and start the checklists again?')) return;
            this.restoreError = false;
            this.formError = '';
            this.checklists = [];
        },
        checklistLabel(row) {
            let applicant = this.applicants[row.applicant_type] || 'Applicant';
            if (row.applicant_type === 'custom') {
                const min = row.min_age, max = row.max_age;
                const hasMin = min !== null && min !== undefined && min !== '';
                const hasMax = max !== null && max !== undefined && max !== '';
                const age = hasMin && hasMax ? 'Ages ' + min + '–' + max : hasMin ? 'Age ' + min + ' and above' : hasMax ? 'Age ' + max + ' and below' : 'No age restriction';
                applicant = row.applicant_type_custom ? row.applicant_type_custom + ' (' + age + ')' : age;
            }
            return applicant + ' • ' + (row.application_type === 'custom' ? row.application_type_custom : this.applications[row.application_type]);
        },
        acceptGuideScenario(row) {
            if (!this.deferred || this.restoreError || !row.client_key) return;
            const existing = this.checklists.find(item => item.client_key === row.client_key);
            if (existing) return;
            const fields = ['application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age'];
            this.checklists.push({ ...Object.fromEntries(fields.map(field => [field, row[field] ?? null])),
                client_key: row.client_key, label: row.label || this.checklistLabel(row), groups: [],
            });
        },
        sameScenario(one, two) {
            const signature = row => JSON.stringify([
                row.application_type, row.application_type === 'custom' ? (row.application_type_custom || '').trim() : '',
                row.applicant_type, row.applicant_type === 'custom' ? (row.applicant_type_custom || '').trim() : '',
                row.applicant_type === 'custom' && row.min_age != null && row.min_age !== '' ? Number(row.min_age) : null,
                row.applicant_type === 'custom' && row.max_age != null && row.max_age !== '' ? Number(row.max_age) : null,
            ]);
            return signature(one) === signature(two);
        },
        applyDraft() {
            const row = this.draft;
            const errors = [];
            if (!Object.hasOwn(this.applications, row.application_type)) errors.push('Select an application type.');
            if (!Object.hasOwn(this.applicants, row.applicant_type)) errors.push('Select an applicant type.');
            if (row.application_type === 'custom' && !row.application_type_custom?.trim()) errors.push('Name the custom application type.');
            if (row.applicant_type === 'custom') {
                for (const field of ['min_age', 'max_age']) {
                    const value = row[field];
                    if (value !== null && value !== undefined && value !== '' && (!Number.isInteger(Number(value)) || Number(value) < 0 || Number(value) > 65535)) errors.push('Use whole ages between 0 and 65535.');
                }
                if (row.min_age != null && row.min_age !== '' && row.max_age != null && row.max_age !== '' && Number(row.max_age) < Number(row.min_age)) errors.push('Maximum age cannot be below minimum age.');
            }
            if (this.checklists.length >= 50 && !row.client_key) errors.push('Use at most 50 checklists per ID.');
            if (errors.length) { this.errors = errors; this.$nextTick(() => this.$refs.errors?.focus()); return; }
            if (!row.client_key) {
                const existing = this.checklists.find(item => this.sameScenario(item, row));
                if (existing?.groups.length) {
                    this.errors = ['This applicant/application already has a checklist. Edit that checklist instead.'];
                    this.$nextTick(() => this.$refs.errors?.focus());
                    return;
                }
                if (existing) row.client_key = existing.client_key;
            }
            if (!row.client_key) {
                do { row.client_key = 'checklist-' + (++this.sequence); }
                while (this.checklists.some(item => item.client_key === row.client_key));
            }
            if (row.application_type !== 'custom') row.application_type_custom = null;
            if (row.applicant_type !== 'custom') {
                row.applicant_type_custom = null;
                row.min_age = row.applicant_type === 'adult' ? 18 : null;
                row.max_age = row.applicant_type === 'minor' ? 17 : null;
            } else {
                for (const field of ['min_age', 'max_age']) {
                    row[field] = row[field] === null || row[field] === undefined || row[field] === '' ? null : Number(row[field]);
                }
            }
            row.label = this.checklistLabel(row);
            const index = this.checklists.findIndex(item => item.client_key === row.client_key);
            const applied = copy(row);
            if (index < 0) this.checklists.push(applied); else this.checklists.splice(index, 1, applied);
            this.formError = '';
            this.$dispatch('guide-scenario-changed', applied);
            this.$dispatch('notify', { type: 'info', message: 'Checklist applied. Create ID to save it with the other sections.' });
            this.close(true);
        },
        itemName(item) {
            if (item.type === 'custom') return item.custom_name || 'Custom requirement';
            const id = item.type === 'government_id' ? item.government_id_id : item.document_id;
            return this.options.find(option => option.type === item.type && String(option.id) === String(id))?.name || item.name || 'Select a requirement';
        },
        conditionLabel(group) {
            return group.condition_type === 'custom' ? group.condition_custom : this.conditions[group.condition_type];
        },
        submission(item) {
            const format = item.submission_format;
            if (!format || format === 'not_specified') return '';
            let label = format === 'custom' ? item.submission_format_custom : this.formats[format];
            if (item.copies && config.countableFormats.includes(format)) {
                const singular = String(item.copies) === '1';
                label += ' · ' + item.copies + ' ' + (format === 'original_photocopy'
                    ? (singular ? 'photocopy' : 'photocopies') : (singular ? 'copy' : 'copies'));
            }
            return label || '';
        },
        ways(group) {
            return group.ways || [{
                id: null, required_count: group.rule === 'choose_one' ? 1 : group.items.length,
                qualification_type: 'none', qualification_scope: 'every',
                qualification_custom: '', items: group.items,
            }];
        },
        requirementName(group) {
            const ways = this.ways(group);
            return group.title || (ways.length === 1 && ways[0].items.length === 1
                ? this.itemName(ways[0].items[0]) : 'Required items');
        },
        waySummary(way) {
            const count = Number(way.required_count);
            if (way.items.length === 1 && count === 1) return this.itemName(way.items[0]);
            if (count === way.items.length) return 'Provide all ' + count + ' listed items';
            return 'Choose ' + count + ' of ' + way.items.length + ' accepted items';
        },
        qualification(way) {
            if (!way.qualification_type || way.qualification_type === 'none') return '';
            const label = way.qualification_type === 'custom' ? way.qualification_custom : this.qualifications[way.qualification_type];
            return (Number(way.required_count) > 1 && way.qualification_scope === 'at_least_one'
                ? 'At least one selected item must: ' : 'Every selected item must: ') + label;
        },
        decorate(group) {
            const result = copy(group);
            result.key = ++this.sequence;
            result.conditional = result.condition_type !== 'always';
            result.advancedOpen = false;
            result.ways = this.ways(result).map(way => ({
                ...way, key: ++this.sequence, search: '', type: way.items[0]?.type || 'document',
                mode: way.items.length <= 1 && Number(way.required_count) === 1 ? 'specific' : 'accepted',
                qualificationOpen: false,
                items: way.items.map(item => ({ ...item, key: ++this.sequence, details: false })),
            }));
            delete result.items;
            return result;
        },
        open(checklist, event) {
            if (!this.editable || this.saving || this.deleting || this.restoreError) return;
            this.opener = event?.currentTarget || document.activeElement;
            this.errors = [];
            this.editing = null;
            this.draft = checklist ? copy(checklist) : {
                id: null, application_type: '', application_type_custom: '',
                applicant_type: '', applicant_type_custom: '', min_age: '', max_age: '', groups: [],
            };
            this.draft.groups = this.draft.groups.map(group => this.decorate(group));
            this.baseline = JSON.stringify(this.payload());
            this.$nextTick(() => {
                this.$refs.dialog.showModal();
                this.previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                this.$refs.application?.focus();
            });
        },
        dirty() {
            return this.draft !== null && (this.editing !== null || JSON.stringify(this.payload()) !== this.baseline);
        },
        close(force = false) {
            if (this.saving) return;
            if (!force && this.dirty() && !window.confirm('Discard your unsaved checklist changes?')) return;
            this.$refs.dialog.close();
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
            this.previousOverflow = null;
            this.draft = null;
            this.editing = null;
            this.errors = [];
            this.$nextTick(() => this.opener?.focus());
        },
        newWay() {
            return {
                id: null, key: ++this.sequence, required_count: 1,
                qualification_type: 'none', qualification_scope: 'every', qualification_custom: '',
                mode: 'specific', search: '', qualificationOpen: false, items: [],
            };
        },
        addRequirement() {
            if (this.editing || this.draft.groups.length >= 100) return;
            this.editingIndex = null;
            this.errors = [];
            this.editing = {
                id: null, key: ++this.sequence, title: '', rule: 'all', condition_type: 'always',
                condition_custom: '', conditional: false, choosing: true, advancedOpen: false, ways: [this.newWay()],
            };
            this.focusEditor();
        },
        requirementEditorTitle() {
            if (!this.editing) return this.draft?.id || this.draft?.client_key ? 'Edit checklist' : 'Add checklist';
            if (this.editing.choosing) return 'What requirement will you add?';
            const verb = this.editingIndex === null ? 'Add' : 'Edit';
            if (this.editing.conditional) return verb + ' a conditional requirement';
            if (this.editing.ways.length > 1) return verb + ' requirement alternatives';
            return this.editing.ways[0].mode === 'accepted'
                ? verb + ' accepted choices' : verb + ' a specific requirement';
        },
        chooseRequirementType(type) {
            if (!this.editing?.choosing || !['specific', 'accepted', 'conditional'].includes(type)) return;
            const group = this.editing;
            const way = group.ways[0];
            if (type !== 'conditional' && group.ways.length > 1) {
                this.errors = ['This requirement has several alternatives. Resume editing to adjust them first, or choose a conditional requirement to keep them.'];
                this.$nextTick(() => this.$refs.errors?.focus());
                return;
            }
            if (type === 'specific' && (way.items.length > 1 || Number(way.required_count) !== 1)) {
                this.errors = ['To use one specific item, resume editing, keep one item, and set the number needed to 1. Your entries have been kept.'];
                this.$nextTick(() => this.$refs.errors?.focus());
                return;
            }
            group.conditional = type === 'conditional';
            if (type !== 'conditional') way.mode = type;
            this.resumeRequirement();
        },
        chooseAgain() {
            if (!this.editing) return;
            this.editing.choosing = true;
            this.errors = [];
            this.focusEditor();
        },
        resumeRequirement() {
            if (!this.editing) return;
            this.editing.choosing = false;
            this.editing.visited = true;
            this.errors = [];
            this.focusEditor();
        },
        editRequirement(index) {
            if (this.editing) return;
            this.editingIndex = index;
            this.editing = copy(this.draft.groups[index]);
            this.editing.advancedOpen = false;
            this.editing.choosing = false;
            this.editing.visited = true;
            this.errors = [];
            this.focusEditor();
        },
        focusEditor() {
            this.$nextTick(() => {
                if (this.$refs.dialogBody) this.$refs.dialogBody.scrollTop = 0;
                const panel = this.$refs.dialog?.querySelector('[aria-label="Requirement editor"]');
                const control = [...(panel?.querySelectorAll('input, select, textarea, button') || [])]
                    .find(element => element.offsetParent !== null);
                control?.focus();
            });
        },
        returnToChecklist() {
            this.$nextTick(() => this.$refs.addRequirementButton?.focus());
        },
        cancelRequirement() {
            if (this.editing?.visited && !window.confirm('Discard changes to this requirement and return to the checklist?')) return;
            this.editing = null;
            this.errors = [];
            this.returnToChecklist();
        },
        removeRequirement(index) {
            if (this.editing || !window.confirm('Remove this requirement from the checklist?')) return;
            this.draft.groups.splice(index, 1);
        },
        addWay() {
            if (this.editing.ways.length >= 10) return;
            if (this.editing.ways.some(way => !way.items.length)) {
                this.errors = ['Select the items for the existing option before adding another.'];
                this.$nextTick(() => this.$refs.errors?.focus());
                return;
            }
            this.editing.advancedOpen = true;
            this.editing.ways.push(this.newWay());
            this.errors = [];
        },
        removeWay(index) {
            if (this.editing.ways.length <= 1) return;
            if (!window.confirm('Remove this alternative and its selected items from this requirement?')) return;
            this.editing.ways.splice(index, 1);
        },
        allowAcceptedItems(way) {
            // Keep the chosen record and its submission details when adding alternatives.
            way.mode = 'accepted';
        },
        useSpecificItem(way) {
            // Never silently discard accepted items or lower an existing required count.
            if (way.items.length > 1 || Number(way.required_count) !== 1) return;
            way.mode = 'specific';
            way.search = '';
        },
        isSelected(way, option) {
            const field = option.type === 'government_id' ? 'government_id_id' : 'document_id';
            return way.items.some(item => item.type === option.type
                && String(item[field]) === String(option.id));
        },
        matches(way) {
            const search = way.search.trim().toLocaleLowerCase();
            if (!search) return [];
            // Keep selected results in place so researchers can continue down the list.
            return this.options.filter(option => option.name.toLocaleLowerCase().includes(search)).slice(0, 20);
        },
        addItem(way, option = null, event = null) {
            if (way.items.length >= 50 || (way.mode === 'specific' && way.items.length > 0)
                || (option && this.isSelected(way, option))) return;
            const searchInput = event?.currentTarget?.closest('[data-requirement-search]')?.querySelector('input[type="search"]');
            const item = {
                id: null, key: ++this.sequence, type: option?.type || 'custom',
                government_id_id: option?.type === 'government_id' ? option.id : null,
                document_id: option?.type === 'document' ? option.id : null,
                custom_name: '', submission_format: 'not_specified', submission_format_custom: '',
                copies: '', quantity: '', instructions: '', details: false,
            };
            if (way.mode === 'specific') {
                way.items = [item];
                way.search = '';
            } else {
                way.items.push(item);
                if (option) this.$nextTick(() => searchInput?.focus({ preventScroll: true }));
            }
        },
        removeItem(way, index) {
            way.items.splice(index, 1);
        },
        finishRequirement() {
            const group = this.editing;
            if (!group) return true;
            if (group.choosing) return false;
            const errors = [];
            if (group.ways.some(way => way.mode === 'accepted') || group.ways.length > 1) {
                if (!group.title?.trim()) errors.push('Give this requirement a name, such as Proof of Identity.');
            }
            if (group.conditional && (group.condition_type === 'always'
                || (group.condition_type === 'custom' && !group.condition_custom?.trim()))) {
                errors.push('Select or describe when this requirement applies.');
            }
            group.ways.forEach((way, index) => {
                if (!way.items.length) errors.push(group.ways.length === 1
                    ? 'Search and select an item, or add a custom item.'
                    : 'Way ' + (index + 1) + ': select what the applicant needs.');
                if (!Number.isInteger(Number(way.required_count)) || Number(way.required_count) < 1
                    || Number(way.required_count) > way.items.length) {
                    errors.push('The number needed must be between 1 and the number of accepted items.');
                }
                if (way.qualification_type === 'custom' && !way.qualification_custom?.trim()) errors.push('Describe the custom qualification.');
                way.items.forEach(item => {
                    if (item.type === 'custom' && !item.custom_name?.trim()) errors.push('Enter the custom requirement name.');
                    if (item.submission_format === 'custom' && !item.submission_format_custom?.trim()) errors.push('Describe the submission format.');
                    for (const field of ['copies', 'quantity']) {
                        if (item[field] !== '' && item[field] !== null && item[field] !== undefined
                            && (!Number.isInteger(Number(item[field])) || Number(item[field]) < 1 || Number(item[field]) > 65535)) {
                            errors.push('Copies and quantities must be whole numbers between 1 and 65535.');
                        }
                    }
                });
            });
            if (errors.length) {
                if (group.ways.length > 1) group.advancedOpen = true;
                this.errors = [...new Set(errors)];
                this.$nextTick(() => this.$refs.errors?.focus());
                return false;
            }
            if (!group.conditional) { group.condition_type = 'always'; group.condition_custom = ''; }
            if (this.editingIndex === null) this.draft.groups.push(copy(group));
            else this.draft.groups.splice(this.editingIndex, 1, copy(group));
            this.editing = null;
            this.errors = [];
            this.returnToChecklist();
            return true;
        },
        payload(d = this.draft) {
            if (!d) return null;
            return {
                application_type: d.application_type,
                application_type_custom: d.application_type_custom || null,
                applicant_type: d.applicant_type,
                applicant_type_custom: d.applicant_type_custom || null,
                min_age: d.min_age === '' ? null : d.min_age,
                max_age: d.max_age === '' ? null : d.max_age,
                groups: d.groups.map(group => ({
                    id: group.id || null, title: group.title || null, rule: group.rule,
                    condition_type: group.condition_type, condition_custom: group.condition_custom || null,
                    ways: group.ways.map(way => ({
                        id: way.id || null, required_count: way.required_count,
                        qualification_type: way.qualification_type,
                        qualification_scope: way.qualification_scope,
                        qualification_custom: way.qualification_custom || null,
                        items: way.items.map(item => ({
                            id: item.id || null, type: item.type,
                            government_id_id: item.government_id_id || null,
                            document_id: item.document_id || null,
                            custom_name: item.custom_name || null,
                            submission_format: item.submission_format,
                            submission_format_custom: item.submission_format_custom || null,
                            copies: item.copies === '' ? null : item.copies,
                            quantity: item.quantity === '' ? null : item.quantity,
                            instructions: item.instructions || null,
                        })),
                    })),
                })),
            };
        },
        async request(url, method, body) {
            const response = await fetch(url, {
                method, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = response.status === 422 ? Object.values(data.errors || {}).flat() : [];
                throw { messages: errors.length ? errors : [
                    response.status === 419 || response.status === 401
                        ? 'Your session expired. Copy any unsaved notes before refreshing and signing in again.'
                        : response.status === 404
                            ? 'This checklist or one of its entries no longer exists. Your entries are still here; close and refresh to load the latest version.'
                            : 'Unable to save this change. Your entries have been kept. Please try again.',
                ] };
            }
            return data;
        },
        updateHistory(history) {
            if (!history) return;
            for (const [key, value] of Object.entries(history)) {
                const target = document.querySelector('[data-checklist-history="' + key + '"]');
                if (target) target.textContent = value;
            }
            document.querySelector('[data-checklist-history="fallback"]')?.remove();
        },
        async save() {
            // Only the checklist overview saves to the server. Enter in the item editor stays local.
            if (this.saving || this.editing) return;
            if (this.deferred) { this.applyDraft(); return; }
            this.errors = [];
            this.saving = true;
            try {
                const id = this.draft.id;
                const data = await this.request(config.url + (id ? '/' + id : ''), id ? 'PUT' : 'POST', this.payload());
                const index = this.checklists.findIndex(checklist => checklist.id === data.checklist.id);
                if (index < 0) this.checklists.push(data.checklist);
                else this.checklists.splice(index, 1, data.checklist);
                this.updateHistory(data.history);
                this.$dispatch('notify', { type: 'success', message: 'Checklist saved.' });
                this.$dispatch('guide-scenario-changed', data.checklist);
                this.saving = false;
                this.close(true);
            } catch (error) {
                this.errors = error.messages || ['Could not reach the server. Your entries have been kept. Please try again.'];
                this.$nextTick(() => this.$refs.errors?.focus());
            } finally {
                this.saving = false;
            }
        },
        async deleteChecklist(checklist) {
            if (this.deleting || this.saving || !window.confirm('Delete "' + checklist.label + '" and its requirements? The IDs and Documents in the directory will remain.')) return;
            if (this.deferred) {
                const removal = new CustomEvent('draft-checklist-removing', { cancelable: true, detail: checklist });
                if (!window.dispatchEvent(removal)) {
                    this.$dispatch('notify', { type: 'error', message: 'This checklist has guide steps. Remove its steps before deleting the scenario.' });
                    return;
                }
                this.checklists = this.checklists.filter(item => item.client_key !== checklist.client_key);
                this.$dispatch('guide-scenario-changed', { client_key: checklist.client_key, deleted: true });
                this.$dispatch('notify', { type: 'info', message: 'Checklist removed from this form.' });
                return;
            }
            this.deleting = checklist.id;
            try {
                const data = await this.request(config.url + '/' + checklist.id, 'DELETE');
                this.checklists = this.checklists.filter(item => item.id !== checklist.id);
                this.updateHistory(data.history);
                this.$dispatch('notify', { type: 'success', message: 'Checklist deleted.' });
                this.$dispatch('guide-scenario-changed', { id: checklist.id, deleted: true });
            } catch (error) {
                this.$dispatch('notify', { type: 'error', message: (error.messages || ['Could not delete the checklist. Please try again.']).join(' ') });
            } finally {
                this.deleting = null;
            }
        },
    };
}
