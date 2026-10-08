const copy = value => JSON.parse(JSON.stringify(value));

export default function governmentIdChecklists(config) {
    return {
        checklists: config.checklists,
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
            this.beforeUnload = event => {
                if (this.dirty() || this.saving) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.beforeUnload);
        },
        destroy() {
            window.removeEventListener('beforeunload', this.beforeUnload);
            if (this.$refs.dialog?.open) this.$refs.dialog.close();
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
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
            result.ways = this.ways(result).map(way => ({
                ...way, key: ++this.sequence, search: '', type: way.items[0]?.type || 'document',
                mode: way.items.length === 1 && !result.title ? 'specific' : 'accepted',
                qualificationOpen: false,
                items: way.items.map(item => ({ ...item, key: ++this.sequence, details: false })),
            }));
            delete result.items;
            return result;
        },
        open(checklist, event) {
            if (!this.editable || this.saving || this.deleting) return;
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
                mode: '', type: 'document', search: '', qualificationOpen: false, items: [],
            };
        },
        addRequirement() {
            if (this.editing || this.draft.groups.length >= 100) return;
            this.editingIndex = null;
            this.errors = [];
            this.editing = {
                id: null, key: ++this.sequence, title: '', rule: 'all', condition_type: 'always',
                condition_custom: '', conditional: false, ways: [this.newWay()],
            };
        },
        editRequirement(index) {
            if (this.editing) return;
            this.editingIndex = index;
            this.editing = copy(this.draft.groups[index]);
            this.errors = [];
        },
        cancelRequirement() {
            if (!window.confirm('Discard changes to this requirement?')) return;
            this.editing = null;
            this.errors = [];
        },
        removeRequirement(index) {
            if (this.editing || !window.confirm('Remove this requirement from the checklist?')) return;
            this.draft.groups.splice(index, 1);
        },
        addWay() {
            if (this.editing.ways.length >= 10) return;
            this.editing.ways.push(this.newWay());
        },
        chooseMode(way, mode) {
            way.mode = mode;
            way.items = [];
            way.required_count = 1;
            way.search = '';
        },
        changeType(way) {
            way.items = [];
            way.search = '';
            if (way.type === 'custom') this.addItem(way);
        },
        matches(way) {
            const search = way.search.trim().toLocaleLowerCase();
            if (!search) return [];
            return this.options.filter(option => (
                (way.mode !== 'specific' || option.type === way.type)
                && option.name.toLocaleLowerCase().includes(search)
                && !way.items.some(item => item.type === option.type
                    && String(item[option.type === 'government_id' ? 'government_id_id' : 'document_id']) === String(option.id))
            )).slice(0, 20);
        },
        addItem(way, option = null) {
            if (way.items.length >= 50) return;
            const item = {
                id: null, key: ++this.sequence, type: option?.type || 'custom',
                government_id_id: option?.type === 'government_id' ? option.id : null,
                document_id: option?.type === 'document' ? option.id : null,
                custom_name: '', submission_format: 'not_specified', submission_format_custom: '',
                copies: '', quantity: '', instructions: '', details: false,
            };
            if (way.mode === 'specific') way.items = [item];
            else way.items.push(item);
            way.search = '';
        },
        removeItem(way, index) {
            way.items.splice(index, 1);
        },
        finishRequirement() {
            const group = this.editing;
            if (!group) return true;
            const errors = [];
            if (group.ways.some(way => way.mode === 'accepted') || group.ways.length > 1) {
                if (!group.title.trim()) errors.push('Give this requirement a name, such as Proof of Identity.');
            }
            if (group.conditional && (group.condition_type === 'always'
                || (group.condition_type === 'custom' && !group.condition_custom?.trim()))) {
                errors.push('Select or describe when this requirement applies.');
            }
            group.ways.forEach((way, index) => {
                if (!way.mode || !way.items.length) errors.push('Way ' + (index + 1) + ': select what the applicant needs.');
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
                this.errors = [...new Set(errors)];
                this.$nextTick(() => this.$refs.errors?.focus());
                return false;
            }
            if (!group.conditional) { group.condition_type = 'always'; group.condition_custom = ''; }
            if (this.editingIndex === null) this.draft.groups.push(copy(group));
            else this.draft.groups.splice(this.editingIndex, 1, copy(group));
            this.editing = null;
            this.errors = [];
            return true;
        },
        payload() {
            const d = this.draft;
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
            if (this.saving || !this.finishRequirement()) return;
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
