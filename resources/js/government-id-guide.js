const clone = value => JSON.parse(JSON.stringify(value));
const blankDoc = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const contentFor = type => ['instructions', 'checklist'].includes(type) ? { items: [{ body: blankDoc() }] } : type === 'official_link' ? { label: '', url: '', description: '' } : ['reminder', 'custom'].includes(type) ? { body: blankDoc() } : { intro: blankDoc() };
// Preview only: never write this plain-text projection back into stored content.
const previewText = value => typeof value === 'string' ? value : value?.text || (Array.isArray(value?.content) ? value.content.map(previewText).join(' ') : '');
// A simple note must never flatten headings, links, marks, or lists.
const plainReminder = body => {
    if (typeof body === 'string') return body;
    if (body?.type !== 'doc' || !Array.isArray(body.content)) return null;
    if (body.content.some(node => node.type !== 'paragraph' || Object.keys(node.attrs || {}).length
        || (node.content || []).some(child => child.type !== 'text' || child.marks?.length || Object.keys(child.attrs || {}).length))) return null;
    return body.content.map(node => (node.content || []).map(child => child.text || '').join('')).join('\n');
};
const scenarioFields = ['client_key', 'id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age'];
const stepFields = ['id', 'title', 'short_description', 'type'];
const pick = (row, fields) => Object.fromEntries(fields.filter(key => row[key] !== undefined).map(key => [key, row[key]]));
const applicationNames = { new: 'First-Time Application', renewal: 'Renewal', replacement: 'Replacement', custom: 'Other / Custom' };
const applicantNames = { all: 'All Applicants', adult: 'Adult', minor: 'Minor', custom: 'Custom Applicant' };
const legacyBlockNames = { instructions: 'Instructions', checklist: 'Things to prepare', reminder: 'Reminder', official_link: 'Official link', requirements: 'Existing requirements', fees: 'Existing fees', offices: 'Linked offices', processing_time: 'Processing time', custom: 'Custom information' };
export const blockNames = legacyBlockNames;
const liveReferenceNames = { requirements: 'Requirements', fees: 'Fees', offices: 'Offices', processing_time: 'Processing Time' };

export default function governmentIdGuide(config) {
    let pointerHandle;
    let guideRoot;
    let dragPreview;
    let suppressCardClick = false;
    return {
        scenarios: [], selected: '', sequence: 0, ready: false, error: '',
        stepDrag: null, dragStatus: '', openMenuKey: null,
        deferred: !!config.deferred,
        wizard: !!config.wizard, saving: false, savePromise: null,
        simpleLinkKey: null, simpleReminderKey: null,
        optionalLinkOpen: false, reminderOpen: false,
        addingScenario: false, scenarioDraft: {}, draft: null, stepIndex: -1, advancedOpen: false, fieldErrors: {}, restoreError: false, submitting: false,
        activeBlock: -1, choosingBlock: false, blockNames: { ...legacyBlockNames, ...blockNames }, availableBlockNames: { ...legacyBlockNames, ...blockNames },
        init() {
            // Alpine event expressions bind $el to the event handle, not this section.
            guideRoot = this.$el;
            this.scenarios = (config.scenarios || []).map(row => this.prepare(row));
            if (config.oldPayload !== null && config.oldPayload !== undefined) {
                try {
                    const previous = JSON.parse(config.oldPayload);
                    if (!Array.isArray(previous)) throw new Error();
                    if (previous.some(row => !row || !Array.isArray(row.steps))) throw new Error();
                    previous.forEach(row => {
                        const restored = this.prepare(row);
                        restored.dirty = true;
                        const index = this.scenarios.findIndex(item => row.client_key ? item.client_key === row.client_key : row.id && item.id === row.id);
                        if (index < 0) this.scenarios.push(restored); else this.scenarios.splice(index, 1, restored);
                    });
                } catch { this.restoreError = true; this.error = 'The submitted guide could not be restored. Its original payload has been retained. Reload the page before saving.'; }
            }
            this.selected = this.scenarios[0]?.key || '';
            this.ready = true;
            this.form = this.$el.closest('form');
            this.submitHandler = event => {
                if (this.wizard) {
                    event.preventDefault();
                    if (this.saving) return;
                    if (this.draft) {
                        this.error = 'Finish or cancel the open application step before continuing.';
                        this.$nextTick(() => this.$refs.guideError?.focus());
                        return;
                    }
                    if (this.stepDrag || this.restoreError || this.addingScenario) {
                        this.error ||= 'Finish the open guide action before continuing.';
                        this.$nextTick(() => this.$refs.guideError?.focus());
                        return;
                    }
                    this.savePromise = this.saveWizardGuide();
                    return;
                }
                if (this.stepDrag || this.restoreError || this.addingScenario || (this.draft && !this.applyStep())) {
                    event.preventDefault();
                    this.error ||= 'Finish or cancel the open guide editor before saving this entry.';
                    this.$nextTick(() => this.$refs.guideError?.focus());
                    return;
                }
                // Apply the open card before the browser collects hidden form inputs.
                if (this.$refs.guidePayload) this.$refs.guidePayload.value = this.payload;
                this.submitting = true;
                queueMicrotask(() => { if (event.defaultPrevented) this.submitting = false; });
            };
            this.beforeUnload = event => {
                if (!this.submitting && (this.draft || this.addingScenario || this.scenarios.some(row => row.dirty))) {
                    event.preventDefault(); event.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.beforeUnload);
            this.form?.addEventListener('submit', this.submitHandler);
            if (this.deferred) {
                this.removalHandler = event => {
                    const scenario = this.scenarios.find(row => row.client_key === event.detail.client_key);
                    if (scenario && (scenario.steps.length > 0 || (this.draft && this.current === scenario))) event.preventDefault();
                };
                window.addEventListener('draft-checklist-removing', this.removalHandler);
                this.$nextTick(() => this.scenarios.forEach(row => this.$dispatch('draft-guide-scenario', { ...pick(row, scenarioFields), label: this.label(row) })));
            }
        },
        destroy() {
            this.cancelStepDrag();
            window.removeEventListener('draft-checklist-removing', this.removalHandler);
            window.removeEventListener('beforeunload', this.beforeUnload);
            this.form?.removeEventListener('submit', this.submitHandler);
        },
        prepare(row) {
            row = clone(row);
            if (this.deferred && !row.client_key) {
                do { row.client_key = 'guide-' + (++this.sequence); }
                while (this.scenarios.some(item => item.client_key === row.client_key));
            }
            return { ...row, key: row.client_key ? 'draft-' + row.client_key : row.id ? 'saved-' + row.id : 'new-' + (++this.sequence), dirty: false,
                steps: (Array.isArray(row.steps) ? row.steps : []).filter(step => step && typeof step === 'object').map(step => ({ ...step, key: 'step-' + (++this.sequence),
                    blocks: (Array.isArray(step.blocks) ? step.blocks : []).filter(block => block && typeof block === 'object').map(block => this.prepareBlock(block)),
                })),
            };
        },
        prepareBlock(block) {
            const content = block.content && typeof block.content === 'object' ? clone(block.content) : {};
            // Add UI keys without rewriting existing body strings or rich-text JSON.
            if (['instructions', 'checklist'].includes(block.type)) content.items = (Array.isArray(content.items) ? content.items : []).map(item => ({
                ...(typeof item === 'string' ? { body: item, originalString: item } : clone(item)), key: ++this.sequence,
            }));
            return { ...clone(block), content, key: ++this.sequence };
        },
        get current() { return this.scenarios.find(row => row.key === this.selected); },
        liveReferenceNames,
        isLiveReference(type) { return Object.hasOwn(liveReferenceNames, type); },
        hasReference(type) { return !!this.draft?.blocks.some(block => block.type === type); },
        get referenceEntries() { return (this.draft?.blocks || []).map((block, index) => ({ block, index })).filter(entry => this.isLiveReference(entry.block.type)); },
        get duplicateReferenceLabels() {
            return Object.entries(liveReferenceNames).filter(([type]) => (this.draft?.blocks || []).filter(block => block.type === type).length > 1).map(([, label]) => label);
        },
        get contentBlockNames() { return this.wizard ? Object.fromEntries(Object.entries(this.availableBlockNames).filter(([type]) => !this.isLiveReference(type))) : this.availableBlockNames; },
        previewBlocks(step) {
            const seen = new Set();
            return step.blocks.filter(block => {
                if (!this.isLiveReference(block.type)) return true;
                if (seen.has(block.type)) return false;
                seen.add(block.type); return true;
            });
        },
        // A read-only projection of the same objects: never mark dirty or serialize preview data.
        get previewSteps() {
            const steps = [...(this.current?.steps || [])];
            if (this.draft && this.current?.key === this.draftScenario) {
                if (this.stepIndex < 0) steps.push(this.draft);
                else steps.splice(this.stepIndex, 1, this.draft);
            }
            return steps;
        },
        previewContent(value, limit = 600) {
            const text = previewText(value);
            return text.length > limit ? text.slice(0, limit).trimEnd() + '…' : text;
        },
        previewStepType(step) { return ['general', 'custom'].includes(step.type) ? '' : (config.stepTypes?.[step.type] || ''); },
        previewUrl(value) {
            try {
                if (typeof value !== 'string' || /\s/.test(value) || value.length > 2048) return null;
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch { return null; }
        },
        previewReference(type) {
            const refs = { ...config.previewReferences };
            if (config.requirementsByScenario && this.current?.id != null) {
                refs.requirements = config.requirementsByScenario[this.current.id] || 0;
            }
            switch (type) {
                case 'requirements': return refs.requirements ? refs.requirements + (refs.requirements === 1 ? ' requirement to prepare' : ' requirements to prepare') : 'Requirements not added yet';
                case 'fees': return refs.fees ? 'Processing fees available' : 'Fees not added yet';
                case 'offices': return refs.offices ? refs.offices + (refs.offices === 1 ? ' office available' : ' offices available') : 'Offices not added yet';
                case 'processing_time': return refs.processingTime || 'Processing time not added yet';
                default: return '';
            }
        },
        async saveWizardGuide() {
            this.saving = true; this.error = '';
            this.$dispatch('guide-saving', true);
            try {
                // Submit the same form and payload to the existing wizard persistence path.
                this.$refs.guidePayload.value = this.payload;
                const response = await fetch(this.form.action, {
                    method: 'POST', body: new FormData(this.form), credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) {
                    let data = {};
                    try { data = await response.json(); } catch { /* An HTML server error must not replace the draft. */ }
                    const messages = Object.values(data.errors || {}).flat();
                    throw new Error(messages.join('\n') || data.message || 'The guide could not be saved. Your changes are still here. Please try again.');
                }
                const destination = new URL(config.nextUrl, window.location.href);
                if (!response.redirected || new URL(response.url).href !== destination.href) {
                    throw new Error('The guide save could not be confirmed. Your changes are still here. Reload your session in another tab if needed, then try again.');
                }
                this.submitting = true;
                window.location.assign(destination.href);
            } catch (error) {
                this.submitting = false;
                this.error = error.message || 'The guide could not be saved. Your changes are still here.';
                this.$nextTick(() => this.$refs.guideError?.focus());
            } finally {
                this.saving = false;
                this.$dispatch('guide-saving', false);
            }
        },
        get simpleOfficialLink() { return this.draft?.blocks.find(block => block.key === this.simpleLinkKey && block.type === 'official_link'); },
        get simpleOfficialLabel() { return this.simpleOfficialLink?.content.label || ''; },
        set simpleOfficialLabel(value) {
            let block = this.simpleOfficialLink;
            if (!block) {
                if (!value.trim()) return;
                if (this.draft.blocks.length >= 50) { this.error = 'Use at most 50 content sections per step.'; return; }
                block = this.prepareBlock({ type: 'official_link', section_title: '', content: { label: value, url: '', description: '' } });
                this.draft.blocks.push(block); this.simpleLinkKey = block.key;
            } else block.content.label = value;
        },
        get simpleOfficialUrl() { return this.simpleOfficialLink?.content.url || ''; },
        set simpleOfficialUrl(value) {
            let block = this.simpleOfficialLink;
            if (!value.trim()) {
                if (block) this.draft.blocks.splice(this.draft.blocks.indexOf(block), 1);
                this.simpleLinkKey = null;
                return;
            }
            if (!block) {
                if (this.draft.blocks.length >= 50) { this.error = 'Use at most 50 content sections per step.'; return; }
                block = this.prepareBlock({ type: 'official_link', section_title: '', content: { label: 'Official website', url: value, description: '' } });
                this.draft.blocks.push(block); this.simpleLinkKey = block.key;
            } else block.content.url = value;
        },
        get simpleReminder() { return this.draft?.blocks.find(block => block.key === this.simpleReminderKey && block.type === 'reminder'); },
        get simpleReminderSupported() { return !this.simpleReminder || plainReminder(this.simpleReminder.content.body) !== null; },
        get simpleReminderText() { return this.simpleReminder ? (plainReminder(this.simpleReminder.content.body) ?? '') : ''; },
        set simpleReminderText(value) {
            if (!this.simpleReminderSupported) return;
            let block = this.simpleReminder;
            if (!value.trim()) {
                if (block) this.draft.blocks.splice(this.draft.blocks.indexOf(block), 1);
                this.simpleReminderKey = null;
                return;
            }
            const body = { type: 'doc', content: value.split('\n').map(text => ({ type: 'paragraph', ...(text ? { content: [{ type: 'text', text }] } : {}) })) };
            if (!block) {
                if (this.draft.blocks.length >= 50) { this.error = 'Use at most 50 content sections per step.'; return; }
                block = this.prepareBlock({ type: 'reminder', section_title: '', content: { body } });
                this.draft.blocks.push(block); this.simpleReminderKey = block.key;
            } else block.content.body = body;
        },
        officialLinkHost(step) {
            const url = step.blocks.find(block => block.type === 'official_link')?.content.url;
            try { const parsed = new URL(url); return ['http:', 'https:'].includes(parsed.protocol) ? parsed.hostname : ''; }
            catch { return ''; }
        },
        get payload() {
            if (this.restoreError) return config.oldPayload;
            return JSON.stringify(this.scenarios.filter(row => row.dirty).map(row => ({ ...pick(row, scenarioFields),
                steps: row.steps.map(step => ({ ...pick(step, stepFields), blocks: step.blocks.map(block => {
                    const content = clone(block.content);
                    if (Array.isArray(content.items)) content.items = content.items.map(item => item.originalString !== undefined && item.body === item.originalString ? item.originalString : ({ body: item.body }));
                    return { ...pick(block, ['id', 'type', 'section_title']), content };
                }) })),
            })));
        },
        label(row) {
            if (row.label) return row.label;
            let applicant = applicantNames[row.applicant_type] || 'Applicant';
            if (row.applicant_type === 'custom') {
                const min = row.min_age, max = row.max_age;
                const age = min != null && min !== '' && max != null && max !== '' ? 'Ages ' + min + '–' + max : min != null && min !== '' ? 'Age ' + min + ' and above' : max != null && max !== '' ? 'Age ' + max + ' and below' : 'No age restriction';
                applicant = row.applicant_type_custom ? row.applicant_type_custom + ' (' + age + ')' : age;
            }
            return applicant + ' • ' + (row.application_type === 'custom' ? row.application_type_custom : applicationNames[row.application_type]);
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
        startScenario() { this.scenarioDraft = { application_type: 'new', applicant_type: 'all', application_type_custom: '', applicant_type_custom: '', min_age: null, max_age: null }; this.addingScenario = true; this.error = ''; },
        addScenario() {
            const row = clone(this.scenarioDraft);
            if (row.application_type === 'custom' && !row.application_type_custom.trim()) { this.error = 'Name the custom application type.'; return; }
            for (const key of ['min_age', 'max_age']) {
                if (row[key] === '') row[key] = null;
                if (row[key] !== null && (!Number.isInteger(Number(row[key])) || Number(row[key]) < 0 || Number(row[key]) > 65535)) { this.error = 'Use whole, non-negative ages.'; return; }
                if (row[key] !== null) row[key] = Number(row[key]);
            }
            if (row.applicant_type !== 'custom') { row.min_age = null; row.max_age = null; row.applicant_type_custom = null; }
            if (row.application_type !== 'custom') row.application_type_custom = null;
            if (row.min_age !== null && row.max_age !== null && row.max_age < row.min_age) { this.error = 'Maximum age cannot be below minimum age.'; return; }
            const existing = this.deferred ? this.scenarios.find(item => this.sameScenario(item, row)) : null;
            if (existing) {
                this.selected = existing.key; this.addingScenario = false; this.error = ''; return;
            }
            if (this.deferred && this.scenarios.length >= 50) { this.error = 'Use at most 50 applicant/application scenarios per ID.'; return; }
            const scenario = this.prepare({ ...row, steps: [] }); scenario.dirty = true;
            this.scenarios.push(scenario); this.selected = scenario.key; this.addingScenario = false; this.error = '';
            if (this.deferred) this.$dispatch('draft-guide-scenario', { ...pick(scenario, scenarioFields), label: this.label(scenario) });
        },
        scenarioChanged(data) {
            const matches = row => data.client_key ? row.client_key === data.client_key : data.id != null && row.id === data.id;
            const index = this.scenarios.findIndex(matches);
            if (data.deleted) {
                if (index >= 0 && (this.deferred ? this.scenarios[index].steps.length > 0 : (this.scenarios[index].dirty || (this.draft && this.current === this.scenarios[index])))) { this.error = 'A scenario with guide changes was deleted. Reload the page before continuing.'; return; }
                this.scenarios = this.scenarios.filter(row => !matches(row));
                if (!this.current) this.selected = this.scenarios[0]?.key || '';
            } else if (index >= 0) Object.assign(this.scenarios[index], pick(data, scenarioFields), { label: data.label });
            else { const row = this.prepare({ ...pick(data, scenarioFields), label: data.label, steps: [] }); this.scenarios.push(row); if (!this.selected) this.selected = row.key; }
        },
        summary(step) {
            const text = step.short_description || step.blocks.map(block => {
                const content = block.content;
                return previewText(content.body) || (content.items || []).map(item => previewText(item.body)).join(' ') || previewText(content.intro)
                    || content.label || block.section_title || (this.wizard && this.isLiveReference(block.type) ? liveReferenceNames[block.type] : this.blockNames[block.type]) || 'Additional content';
            }).join(' · ') || 'Expand to add instructions.';
            return text.length > 160 ? text.slice(0, 157) + '…' : text;
        },
        editStep(index = -1) {
            if (!this.current || this.stepDrag || this.draft || this.addingScenario || this.restoreError || (index < 0 && this.current.steps.length >= 100)) return;
            this.stepIndex = index;
            this.draftScenario = this.current.key;
            this.draft = index < 0 ? { key: 'step-' + (++this.sequence), title: '', short_description: '', type: 'general', blocks: [] } : clone(this.current.steps[index]);
            this.originalDraft = JSON.stringify(this.draft);
            this.simpleLinkKey = this.draft.blocks.find(block => block.type === 'official_link')?.key ?? null;
            this.simpleReminderKey = this.draft.blocks.find(block => block.type === 'reminder')?.key ?? null;
            this.optionalLinkOpen = false; this.reminderOpen = false;
            this.activeBlock = -1; this.choosingBlock = false; this.advancedOpen = false; this.fieldErrors = {}; this.error = '';
            this.$nextTick(() => guideRoot.querySelector('[data-guide-title]')?.focus());
        },
        close(force = false) {
            if (!this.draft) return;
            if (!force && JSON.stringify(this.draft) !== this.originalDraft && !window.confirm('Discard changes to this step?')) return;
            const key = this.draft.key;
            this.draft = null; this.error = ''; this.fieldErrors = {};
            this.$nextTick(() => (guideRoot.querySelector('[data-guide-step="' + key + '"]') || this.$refs.addStepButton)?.focus());
        },
        applyStep() {
            if (!this.draft) return true;
            if (this.current?.key !== this.draftScenario) { this.error = 'Return to the original scenario before saving this step.'; return false; }
            this.fieldErrors = {};
            if (!this.draft.title.trim() || this.draft.title.length > 255) this.fieldErrors.title = 'Give this step a title of up to 255 characters.';
            if ((this.draft.short_description || '').length > 2000) this.fieldErrors.instructions = 'Keep these instructions within 2,000 characters. Use additional content for longer instructions.';
            for (const block of this.draft.blocks) {
                let message = '';
                if (['instructions', 'checklist'].includes(block.type) && (!block.content.items.length || block.content.items.length > 100)) message = 'Add between 1 and 100 items to this list.';
                if (block.type === 'checklist' && block.content.items.some(item => typeof item.body === 'string' && (!item.body.trim() || item.body.length > 20000))) message = 'Each checklist item needs text of up to 20,000 characters.';
                if (block.type === 'official_link') {
                    try {
                        const url = new URL(block.content.url);
                        if (!['http:', 'https:'].includes(url.protocol) || /\s/.test(block.content.url) || block.content.url.length > 2048 || !block.content.label?.trim() || block.content.label.length > 255 || (block.content.description || '').length > 2000) throw new Error();
                    } catch { message = 'Enter a link label and a complete http:// or https:// website address. Keep the description within 2,000 characters.'; }
                }
                if (message) {
                    this.fieldErrors['block-' + block.key] = message; this.activeBlock = this.draft.blocks.indexOf(block); this.advancedOpen = true;
                    if (this.wizard && block.key === this.simpleLinkKey) this.optionalLinkOpen = true;
                }
            }
            if (Object.keys(this.fieldErrors).length) {
                this.error = 'Check the highlighted fields in this step. Your changes are still here.';
                this.$nextTick(() => (guideRoot.querySelector('[aria-invalid="true"]') || this.$refs.guideError)?.focus());
                return false;
            }
            const step = clone(this.draft); step.title = step.title.trim();
            if (JSON.stringify(step) !== this.originalDraft || this.stepIndex < 0) {
                if (this.stepIndex < 0) this.current.steps.push(step); else this.current.steps.splice(this.stepIndex, 1, step);
                this.current.dirty = true;
            }
            this.close(true);
            return true;
        },
        removeStep(index) {
            if (!this.current || this.stepDrag || this.draft || this.restoreError) return;
            if (window.confirm(this.wizard ? 'Delete this step and its additional content? Changes are saved when you continue.' : 'Delete this step and its additional content? This is saved when you save the main entry.')) {
                this.current.steps.splice(index, 1); this.current.dirty = true;
                this.$nextTick(() => this.$refs.addStepButton?.focus());
            }
        },
        move(list, index, direction) {
            const next = index + direction;
            if (next < 0 || next >= list.length) return false;
            const item = list.splice(index, 1)[0]; list.splice(next, 0, item); return true;
        },
        reorderStep(from, to) {
            if (!this.current || (this.draft && this.stepIndex < 0) || this.restoreError || this.stepDrag || !Number.isInteger(from) || !Number.isInteger(to)
                || from < 0 || to < 0 || from >= this.current.steps.length || to >= this.current.steps.length || from === to) return false;
            const step = this.current.steps.splice(from, 1)[0];
            this.current.steps.splice(to, 0, step);
            this.current.dirty = true;
            if (this.draft) this.stepIndex = this.current.steps.findIndex(row => row.key === this.draft.key);
            this.dragStatus = step.title + ' moved to step ' + (to + 1) + ' of ' + this.current.steps.length + (this.wizard ? '. Changes are saved when you continue.' : '. Save the main entry to keep this order.');
            return true;
        },
        moveStep(index, direction) {
            if (this.reorderStep(index, index + direction)) {
                const key = this.current.steps[index + direction].key;
                this.$nextTick(() => guideRoot.querySelector('[data-guide-header="' + key + '"]')?.focus());
            }
        },
        cardKeydown(event, index) {
            if (event.key === 'Escape' && this.stepDrag) { event.preventDefault(); this.cancelStepDrag(); return; }
            if (event.target !== event.currentTarget || !event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
            event.preventDefault(); this.moveStep(index, event.key === 'ArrowUp' ? -1 : 1);
        },
        finishCardClick(event) {
            if (suppressCardClick) { event.preventDefault(); event.stopPropagation(); suppressCardClick = false; }
        },
        toggleMenu(key) {
            if (this.stepDrag) return;
            if (this.openMenuKey === key) { this.closeMenu(); return; }
            this.openMenuKey = key;
            this.$nextTick(() => guideRoot.querySelector('[data-step-menu="' + key + '"] [role="menuitem"]:not(:disabled)')?.focus());
        },
        closeMenu(restoreFocus = true) {
            const key = this.openMenuKey; this.openMenuKey = null;
            if (restoreFocus && key) this.$nextTick(() => guideRoot.querySelector('[data-step-menu-trigger="' + key + '"]')?.focus());
        },
        editMenuStep(index) {
            if (this.draft && this.stepIndex === index) this.$nextTick(() => guideRoot.querySelector('[data-guide-title]')?.focus());
            else this.editStep(index);
        },
        menuKeydown(event) {
            if (!['ArrowUp', 'ArrowDown', 'Home', 'End', 'Tab'].includes(event.key)) return;
            if (event.key === 'Tab') { this.closeMenu(false); return; }
            event.preventDefault(); event.stopPropagation();
            const items = [...event.currentTarget.querySelectorAll('[role="menuitem"]:not(:disabled)')];
            const index = items.indexOf(event.target);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowUp' ? -1 : 1) + items.length) % items.length;
            items[next]?.focus();
        },
        startStepDrag(event, key) {
            if ((this.draft && this.stepIndex < 0) || this.restoreError || this.addingScenario || this.stepDrag || !this.current || this.current.steps.length < 2
                || event.isPrimary === false || event.button !== 0 || !this.current.steps.some(step => step.key === key)) return;
            if (event.target?.closest('button, input, textarea, select, a, summary, [contenteditable], [role="menu"]')) return;
            this.closeMenu(false);
            suppressCardClick = false;
            event.preventDefault();
            pointerHandle = event.currentTarget;
            this.stepDrag = { key, scenario: this.current.key, pointerId: event.pointerId,
                startX: event.clientX, startY: event.clientY, active: false, target: null, after: false };
            pointerHandle.focus({ preventScroll: true });
            pointerHandle.setPointerCapture(event.pointerId);
        },
        moveStepDrag(event) {
            const drag = this.stepDrag;
            if (!drag || drag.pointerId !== event.pointerId) return;
            if (this.current?.key !== drag.scenario) { this.cancelStepDrag(); return; }
            event.preventDefault();
            if (!drag.active && Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY) < 6) return;
            drag.active = true;
            suppressCardClick = true;
            if (!dragPreview) {
                dragPreview = document.createElement('div');
                dragPreview.setAttribute('aria-hidden', 'true');
                dragPreview.setAttribute('data-guide-drag-preview', '');
                dragPreview.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;max-width:min(340px,80vw);padding:16px;border:2px solid #2563eb;border-radius:12px;background:white;color:#0f172a;box-shadow:0 12px 28px #0f172a33;white-space:normal;overflow-wrap:anywhere;font-weight:600;';
                dragPreview.textContent = this.current.steps.find(step => step.key === drag.key).title;
                document.body.appendChild(dragPreview);
            }
            dragPreview.style.left = Math.max(8, Math.min(event.clientX + 16, window.innerWidth - dragPreview.offsetWidth - 8)) + 'px';
            dragPreview.style.top = Math.max(8, Math.min(event.clientY + 16, window.innerHeight - dragPreview.offsetHeight - 8)) + 'px';
            const card = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-guide-card]');
            const key = card && guideRoot.contains(card) ? card.dataset.guideCard : null;
            drag.target = this.current.steps.some(step => step.key === key) ? key : null;
            drag.after = !!card && event.clientY > card.getBoundingClientRect().top + card.getBoundingClientRect().height / 2;
            this.dragStatus = drag.target && drag.target !== drag.key
                ? 'Release to place the step ' + (drag.after ? 'after' : 'before') + ' step ' + (this.current.steps.findIndex(step => step.key === drag.target) + 1) + '.'
                : 'Dragging step. Move to another card; press Escape to cancel.';
            // Scroll only while the handle is being dragged near a viewport edge.
            if (event.clientY < 64) window.scrollBy(0, -16);
            else if (event.clientY > window.innerHeight - 64) window.scrollBy(0, 16);
        },
        endStepDrag(event) {
            const drag = this.stepDrag;
            if (!drag || drag.pointerId !== event.pointerId) return;
            event.preventDefault();
            const steps = this.current?.key === drag.scenario ? this.current.steps : [];
            const from = steps.findIndex(step => step.key === drag.key);
            let to = steps.findIndex(step => step.key === drag.target);
            if (to >= 0 && drag.after) to++;
            if (from < to) to--;
            const valid = drag.active && drag.target && drag.target !== drag.key && from >= 0;
            this.cancelStepDrag();
            if (valid && this.reorderStep(from, to)) {
                this.$nextTick(() => guideRoot.querySelector('[data-guide-header="' + drag.key + '"]')?.focus());
            }
        },
        cancelStepDrag() {
            const drag = this.stepDrag;
            this.stepDrag = null;
            dragPreview?.remove(); dragPreview = null;
            const handle = pointerHandle; pointerHandle = null;
            if (drag && handle?.hasPointerCapture(drag.pointerId)) handle.releasePointerCapture(drag.pointerId);
            if (drag) this.dragStatus = 'Drag finished without changing the order.';
        },
        addBlock(type) {
            if (!this.draft || this.draft.blocks.length >= 50 || !Object.hasOwn(this.availableBlockNames, type)) return;
            if (this.wizard && this.isLiveReference(type) && this.hasReference(type)) return;
            const content = contentFor(type);
            this.draft.blocks.push(this.prepareBlock({ type, section_title: '', content })); this.activeBlock = this.draft.blocks.length - 1; this.choosingBlock = false;
        },
        changeBlockType(block, type) {
            if (type === block.type || !Object.hasOwn(this.availableBlockNames, type)) return;
            if (this.wizard && (this.isLiveReference(block.type) || (this.isLiveReference(type) && this.hasReference(type)))) return;
            if (!window.confirm('Change this block type? Its current content will be cleared.')) return;
            const replacement = this.prepareBlock({ type, content: contentFor(type) });
            block.type = type; block.content = replacement.content; block.key = replacement.key;
        },
        removeBlock(index) { if (window.confirm('Remove this information block?')) { this.draft.blocks.splice(index, 1); this.activeBlock = -1; } },
        moveBlock(index, direction) { this.move(this.draft.blocks, index, direction); this.activeBlock = -1; },
        removeItem(block, index) { if (window.confirm('Remove this list item?')) block.content.items.splice(index, 1); },
        addItem(block) { if (block.content.items.length >= 100) return; block.content.items.push({ body: blankDoc(), key: ++this.sequence }); },
    };
}
