const clone = value => JSON.parse(JSON.stringify(value));
const blankDoc = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
const doc = value => typeof value === 'string' ? ({ type: 'doc', content: [{ type: 'paragraph', ...(value ? { content: [{ type: 'text', text: value }] } : {}) }] }) : (value || blankDoc());
const scenarioFields = ['id', 'application_type', 'application_type_custom', 'applicant_type', 'applicant_type_custom', 'min_age', 'max_age'];
const stepFields = ['id', 'title', 'short_description', 'type'];
const pick = (row, fields) => Object.fromEntries(fields.filter(key => row[key] !== undefined).map(key => [key, row[key]]));
const applicationNames = { new: 'First-Time Application', renewal: 'Renewal', replacement: 'Replacement', custom: 'Other / Custom' };
const applicantNames = { all: 'All Applicants', adult: 'Adult', minor: 'Minor', custom: 'Custom Applicant' };
export const blockNames = { instructions: 'Instructions', checklist: 'Things to prepare', reminder: 'Reminder', official_link: 'Official link', requirements: 'Existing requirements', fees: 'Existing fees', offices: 'Linked offices', processing_time: 'Processing time', custom: 'Custom information' };

export default function governmentIdGuide(config) {
    return {
        scenarios: [], selected: '', sequence: 0, ready: false, error: '',
        addingScenario: false, scenarioDraft: {}, modal: false, draft: null, stepIndex: -1,
        activeBlock: -1, choosingBlock: false, blockNames,
        init() {
            this.scenarios = (config.scenarios || []).map(row => this.prepare(row));
            if (config.oldPayload !== null) {
                try {
                    const previous = JSON.parse(config.oldPayload);
                    if (!Array.isArray(previous)) throw new Error();
                    previous.forEach(row => {
                        if (!row || !Array.isArray(row.steps)) throw new Error();
                        const restored = this.prepare(row);
                        restored.dirty = true;
                        const index = this.scenarios.findIndex(item => row.id && item.id === row.id);
                        if (index < 0) this.scenarios.push(restored); else this.scenarios.splice(index, 1, restored);
                    });
                } catch { this.error = 'Some submitted guide data could not be restored. Please review before saving.'; }
            }
            this.selected = this.scenarios[0]?.key || '';
            this.ready = true;
            this.form = this.$el.closest('form');
            this.submitHandler = event => {
                if (this.modal || this.addingScenario) {
                    event.preventDefault();
                    this.error = 'Finish or cancel the open guide editor before saving the ID.';
                }
            };
            this.form?.addEventListener('submit', this.submitHandler);
        },
        destroy() { this.form?.removeEventListener('submit', this.submitHandler); if (this.modal) document.body.style.overflow = this.previousOverflow || ''; },
        prepare(row) {
            return { ...clone(row), key: row.id ? 'saved-' + row.id : 'new-' + (++this.sequence), dirty: false,
                steps: (Array.isArray(row.steps) ? row.steps : []).filter(step => step && typeof step === 'object').map(step => ({ ...step,
                    blocks: (Array.isArray(step.blocks) ? step.blocks : []).filter(block => block && typeof block === 'object').map(block => this.prepareBlock(block)),
                })),
            };
        },
        prepareBlock(block) {
            const content = block.content && typeof block.content === 'object' ? clone(block.content) : {};
            if (['instructions', 'checklist'].includes(block.type)) content.items = (Array.isArray(content.items) ? content.items : []).map(item => ({ body: doc(typeof item === 'string' ? item : item?.body), key: ++this.sequence }));
            else if (['reminder', 'custom'].includes(block.type)) content.body = doc(content.body);
            else if (block.type !== 'official_link') content.intro = doc(content.intro);
            return { ...clone(block), content, key: ++this.sequence };
        },
        get current() { return this.scenarios.find(row => row.key === this.selected); },
        get payload() {
            return JSON.stringify(this.scenarios.filter(row => row.dirty).map(row => ({ ...pick(row, scenarioFields),
                steps: row.steps.map(step => ({ ...pick(step, stepFields), blocks: step.blocks.map(block => {
                    const content = clone(block.content);
                    if (Array.isArray(content.items)) content.items = content.items.map(item => ({ body: item.body }));
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
            const scenario = this.prepare({ ...row, steps: [] }); scenario.dirty = true;
            this.scenarios.push(scenario); this.selected = scenario.key; this.addingScenario = false; this.error = '';
        },
        scenarioChanged(data) {
            const index = this.scenarios.findIndex(row => row.id === data.id);
            if (data.deleted) {
                if (index >= 0 && this.scenarios[index].dirty) { this.error = 'A scenario with guide changes was deleted. Reload the page before continuing.'; return; }
                this.scenarios = this.scenarios.filter(row => row.id !== data.id);
                if (!this.current) this.selected = this.scenarios[0]?.key || '';
            } else if (index >= 0) Object.assign(this.scenarios[index], pick(data, scenarioFields), { label: data.label });
            else { const row = this.prepare({ ...pick(data, scenarioFields), label: data.label, steps: [] }); this.scenarios.push(row); if (!this.selected) this.selected = row.key; }
        },
        editStep(index = -1) {
            if (!this.current) return;
            this.stepIndex = index;
            this.draft = index < 0 ? { title: '', short_description: '', type: 'general', blocks: [] } : clone(this.current.steps[index]);
            this.originalDraft = JSON.stringify(this.draft); this.activeBlock = -1; this.choosingBlock = false; this.error = '';
            this.returnFocus = document.activeElement; this.previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; this.modal = true;
            this.$nextTick(() => this.$refs.stepTitle?.focus());
        },
        close(force = false) {
            if (!force && JSON.stringify(this.draft) !== this.originalDraft && !window.confirm('Discard changes to this step?')) return;
            this.modal = false; this.draft = null; this.error = ''; document.body.style.overflow = this.previousOverflow || ''; this.returnFocus?.focus();
        },
        applyStep() {
            if (!this.draft.title.trim()) { this.error = 'Give this step a short title.'; this.$refs.stepTitle?.focus(); return; }
            for (const block of this.draft.blocks) {
                if (['instructions', 'checklist'].includes(block.type) && !block.content.items.length) { this.error = 'Add at least one item to each list.'; return; }
                if (block.type === 'official_link') {
                    try { const url = new URL(block.content.url); if (!['http:', 'https:'].includes(url.protocol) || !block.content.label?.trim()) throw new Error(); }
                    catch { this.error = 'Give each official link a label and a valid http:// or https:// address.'; return; }
                }
            }
            const step = clone(this.draft); step.title = step.title.trim();
            if (this.stepIndex < 0) this.current.steps.push(step); else this.current.steps.splice(this.stepIndex, 1, step);
            this.current.dirty = true; this.close(true);
            this.$dispatch('notify', { type: 'info', message: 'Guide step updated. Save the ID to keep it.' });
        },
        removeStep(index) { if (window.confirm('Remove this step? The change is saved when you save the ID.')) { this.current.steps.splice(index, 1); this.current.dirty = true; this.$dispatch('notify', { type: 'info', message: 'Guide step removed from this form. Save the ID to keep the change.' }); } },
        move(list, index, direction) { const next = index + direction; if (next < 0 || next >= list.length) return; const item = list.splice(index, 1)[0]; list.splice(next, 0, item); },
        moveStep(index, direction) { this.move(this.current.steps, index, direction); this.current.dirty = true; },
        addBlock(type) {
            const content = ['instructions', 'checklist'].includes(type) ? { items: [{ body: blankDoc() }] } : type === 'official_link' ? { label: '', url: '', description: '' } : ['reminder', 'custom'].includes(type) ? { body: blankDoc() } : { intro: blankDoc() };
            this.draft.blocks.push(this.prepareBlock({ type, section_title: '', content })); this.activeBlock = this.draft.blocks.length - 1; this.choosingBlock = false;
        },
        removeBlock(index) { if (window.confirm('Remove this information block?')) { this.draft.blocks.splice(index, 1); this.activeBlock = -1; } },
        moveBlock(index, direction) { this.move(this.draft.blocks, index, direction); this.activeBlock = -1; },
        addItem(block) { block.content.items.push({ body: blankDoc(), key: ++this.sequence }); },
        trap(event) {
            if (event.key !== 'Tab') return;
            const elements = [...this.$refs.dialog.querySelectorAll('button, input, textarea, select, a[href], [tabindex="0"], [contenteditable="true"]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = elements[0], last = elements[elements.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        },
    };
}
