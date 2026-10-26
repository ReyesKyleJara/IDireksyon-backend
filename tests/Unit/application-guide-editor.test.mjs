import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import guide from '../../resources/js/government-id-guide.js';
const clone = value => structuredClone(value);
const document = text => ({ type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text, marks: [{ type: 'bold' }] }] }] });
const blocks = () => [
    { id: 101, type: 'instructions', section_title: 'Prepare', content: { items: [{ body: document('Bring your form') }] } },
    { id: 102, type: 'official_link', section_title: 'Appointment', content: { label: 'Book online', url: 'https://example.gov.ph/book', description: 'Choose a date.' } },
    ...['requirements', 'fees', 'offices', 'processing_time'].map((type, i) => ({ id: 103 + i, type, content: { intro: document('Current information') } })),
    { id: 107, type: 'checklist', content: { items: [{ body: 'Sign the form' }, { body: document('Print it') }] } },
    { id: 108, type: 'custom', content: { body: document('Extra details') } },
];
const scenario = (id = 1) => ({ id, application_type: 'new', applicant_type: 'custom', applicant_type_custom: 'Children', min_age: 0, max_age: 17,
    steps: [{ id: 10, title: 'Prepare', short_description: 'Fill out the form', type: 'office_visit', blocks: blocks() }] });
function make(rows = [scenario()], config = {}) {
    const state = guide({ scenarios: rows, oldPayload: null, ...config });
    const events = []; const listeners = {};
    state.$dispatch = (name, detail) => events.push({ name, detail });
    state.$nextTick = fn => fn(); state.$refs = { guidePayload: { value: '' } };
    state.$el = { closest: () => ({ addEventListener: (name, fn) => { listeners[name] = fn; }, removeEventListener() {} }), querySelector: () => null };
    state.events = events; state.listeners = listeners; state.init(); return state;
}
globalThis.window = { confirm: () => true, addEventListener() {}, removeEventListener() {} };
const send = state => { state.current.dirty = true; return JSON.parse(state.payload)[0]; };

test('title and instructions edits preserve every advanced block, ID, scenario and order', () => {
    const input = scenario(); const state = make([input]);
    state.editStep(0); state.draft.title = 'Visit the office'; state.draft.short_description = 'Bring the completed form.';
    assert.equal(state.applyStep(), true);
    const result = JSON.parse(state.payload)[0];
    assert.equal(result.id, 1); assert.equal(result.min_age, 0); assert.equal(result.steps[0].id, 10);
    assert.equal(result.steps[0].type, 'office_visit'); assert.deepEqual(result.steps[0].blocks, input.steps[0].blocks);
    assert.equal(input.steps[0].title, 'Prepare');
});
test('opening and saving unchanged content does not dirty a scenario', () => {
    const state = make(); state.editStep(0); state.applyStep(); assert.equal(state.payload, '[]');
});
test('add with only title and instructions, and repeated saves do not duplicate', () => {
    const state = make(); state.editStep(); state.draft.title = 'Submit'; state.draft.short_description = 'Hand the form to the officer.';
    state.applyStep(); state.applyStep();
    assert.equal(state.current.steps.length, 2);
    assert.deepEqual(JSON.parse(state.payload)[0].steps[1], { title: 'Submit', short_description: 'Hand the form to the officer.', type: 'general', blocks: [] });
});
test('cancel restores existing data and discards a new step', () => {
    const state = make(); state.editStep(0); state.draft.blocks[0].content.items[0].body = document('Changed'); state.close();
    assert.equal(state.payload, '[]'); assert.deepEqual(send(state).steps, scenario().steps);
    state.editStep(); state.draft.title = 'Discard'; state.close(); assert.equal(state.current.steps.length, 1);
});
test('reorder keeps stable keys and IDs; deletion requires confirmation', () => {
    const state = make(); const key = state.current.steps[0].key;
    state.editStep(); state.draft.title = 'Second'; state.applyStep(); state.moveStep(1, -1);
    assert.equal(state.current.steps[1].key, key); assert.equal(state.current.steps[1].id, 10);
    window.confirm = () => false; state.removeStep(1); assert.equal(state.current.steps.length, 2);
    window.confirm = () => true; state.removeStep(0); assert.equal(JSON.parse(state.payload)[0].steps[0].id, 10);
});
test('invalid title, instruction length and official links keep draft and show field errors', () => {
    const state = make(); state.editStep(0); state.draft.title = ' '; state.draft.short_description = 'a'.repeat(2001);
    state.draft.blocks[1].content.url = 'javascript:alert(1)';
    assert.equal(state.applyStep(), false); assert.ok(state.fieldErrors.title); assert.ok(state.fieldErrors.instructions);
    assert.ok(state.fieldErrors['block-' + state.draft.blocks[1].key]); assert.equal(state.advancedOpen, true);
    assert.equal(state.payload, '[]'); assert.ok(state.draft);
});
test('all existing additional content types can be added including official links and formatted text', () => {
    const state = make(); state.editStep();
    for (const type of ['official_link', 'instructions', 'reminder', 'requirements', 'fees', 'offices', 'processing_time', 'custom', 'checklist', 'note', 'warning']) state.addBlock(type);
    const link = state.draft.blocks[0]; link.content = { label: 'Official site', url: 'https://example.gov.ph/', description: '' };
    const serialized = clone(link); state.draft.title = 'Apply';
    state.draft.blocks = [link]; assert.equal(state.applyStep(), true);
    assert.deepEqual(JSON.parse(state.payload)[0].steps[1].blocks[0].content, serialized.content);
});
test('editing a link preserves its ID and cancel keeps the original URL', () => {
    const state = make(); state.editStep(0); state.draft.blocks[1].content.url = 'https://example.gov.ph/new'; state.close();
    assert.equal(send(state).steps[0].blocks[1].content.url, 'https://example.gov.ph/book');
    state.editStep(0); state.draft.blocks[1].content.url = 'https://example.gov.ph/new'; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[0].blocks[1].id, 102);
});
test('scenario boundaries reject adopting an open draft and leave other scenarios untouched', () => {
    const state = make([scenario(), { ...scenario(2), steps: [] }]); state.editStep(0); state.selected = state.scenarios[1].key;
    assert.equal(state.applyStep(), false); assert.equal(state.scenarios[1].steps.length, 0);
    state.selected = state.scenarios[0].key; state.draft.title = 'Updated'; state.applyStep();
    assert.equal(JSON.parse(state.payload).length, 1); assert.equal(JSON.parse(state.payload)[0].id, 1);
});
test('form submission applies an open valid card synchronously; invalid draft prevents submission', () => {
    const state = make(); state.editStep(); state.draft.title = 'New';
    let prevented = false; const event = { preventDefault: () => { prevented = true; }, defaultPrevented: false };
    state.listeners.submit(event); assert.equal(prevented, false); assert.equal(JSON.parse(state.$refs.guidePayload.value)[0].steps.length, 2);
    state.listeners.submit(event); assert.equal(state.current.steps.length, 2);
    state.editStep(); state.listeners.submit(event); assert.equal(prevented, true); assert.ok(state.draft);
});
test('payload restoration after server validation preserves nested content and client keys', () => {
    const row = scenario(); delete row.id; row.client_key = 'scenario-draft';
    const state = make([], { deferred: true, oldPayload: JSON.stringify([row]) });
    assert.deepEqual(JSON.parse(state.payload), [row]);
    assert.equal(state.events[0].detail.client_key, 'scenario-draft');
    const reload = make(JSON.parse(state.payload), { deferred: true }); assert.deepEqual(send(reload), row);
});
test('malformed old payload is retained and blocks saving instead of overwriting saved content', () => {
    const state = make([scenario()], { oldPayload: '{broken' }); let prevented = false;
    state.listeners.submit({ preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true); assert.equal(state.payload, '{broken'); assert.equal(state.current.steps[0].id, 10);
});
test('legacy body strings are not normalized on unrelated edits', () => {
    const row = scenario(); row.steps[0].blocks = [{ id: 200, type: 'custom', content: { body: 'Original plain text' } }];
    const state = make([row]); state.editStep(0); state.draft.title = 'New title'; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[0].blocks[0].content.body, 'Original plain text');
});
test('collapsed summaries show existing formatted instructions as safe preview text without changing them', () => {
    const row = scenario(); row.steps[0].short_description = null;
    const state = make([row]);
    assert.match(state.summary(state.current.steps[0]), /Bring your form/);
    assert.deepEqual(send(state).steps[0].blocks, row.steps[0].blocks);
});
test('existing scenarios are reused and requirements scenario events retain guide content', () => {
    const state = make(); const original = clone(state.current.steps);
    assert.equal(state.scenarios.length, 1);
    assert.equal(typeof state.startScenario, 'function');
    state.scenarioChanged({ id: 1, applicant_type: 'custom', applicant_type_custom: 'Updated children', min_age: 0, max_age: 17, application_type: 'new', label: 'Updated' });
    assert.deepEqual(state.current.steps, original); assert.equal(state.current.id, 1);
});

function reorderFixture() {
    const row = scenario();
    row.steps.push({ id: 20, title: 'Second', short_description: 'Second instructions', type: 'general', blocks: [] },
        { id: 30, title: 'Third', short_description: 'Third instructions', type: 'general', blocks: [] });
    const state = make([row]);
    state.$el.contains = card => !!card;
    let captured = false;
    const handle = { focus() {}, setPointerCapture() { captured = true; }, hasPointerCapture() { return captured; }, releasePointerCapture() { captured = false; } };
    const event = (y, type = 'mouse', extra = {}) => ({ pointerId: 7, pointerType: type, isPrimary: true, button: 0, clientX: 20, clientY: y, currentTarget: handle, preventDefault() {}, ...extra });
    window.scrollBy = () => {}; window.innerHeight = 1000;
    globalThis.document = { elementFromPoint: () => null, body: { appendChild() {} }, createElement: () => ({ setAttribute() {}, style: {}, offsetWidth: 200, offsetHeight: 60, remove() {} }) };
    const target = index => { globalThis.document.elementFromPoint = () => ({ closest: () => ({ dataset: { guideCard: state.current.steps[index].key }, getBoundingClientRect: () => ({ top: 100, height: 100 }) }) }); };
    return { state, event, target, row };
}
for (const type of ['mouse', 'touch']) {
    test(`${type} handle drag commits local order only on release and preserves all content`, () => {
        const { state, event, target, row } = reorderFixture();
        state.startStepDrag(event(100, type), state.current.steps[0].key);
        target(2); state.moveStepDrag(event(180, type));
        assert.equal(state.stepDrag.active, true); assert.equal(state.payload, '[]');
        assert.deepEqual(state.current.steps.map(step => step.id), [10, 20, 30]);
        state.endStepDrag(event(180, type));
        const saved = JSON.parse(state.payload)[0];
        assert.deepEqual(saved.steps.map(step => step.id), [20, 30, 10]);
        assert.deepEqual(saved.steps[2], row.steps[0]); assert.equal(saved.id, row.id);
        assert.equal(state.stepDrag, null); assert.equal(state.draft, null);
        assert.match(state.dragStatus, /moved to step 3/);
    });
}
test('drag before another card works upward and numbering follows current order', () => {
    const { state, event, target } = reorderFixture();
    state.startStepDrag(event(190), state.current.steps[2].key); target(0); state.moveStepDrag(event(110)); state.endStepDrag(event(110));
    assert.deepEqual(state.current.steps.map((step, i) => [i + 1, step.id]), [[1, 30], [2, 10], [3, 20]]);
});
test('click, cancelled drag, drop outside, and secondary pointer leave order unchanged', () => {
    const { state, event, target } = reorderFixture(); const key = state.current.steps[0].key;
    state.startStepDrag(event(100, 'mouse', { button: 2 }), key); assert.equal(state.stepDrag, null);
    state.startStepDrag(event(100), key); state.moveStepDrag(event(102)); state.endStepDrag(event(102)); assert.equal(state.payload, '[]');
    state.startStepDrag(event(100), key); target(2); state.moveStepDrag(event(180)); state.cancelStepDrag(); assert.equal(state.payload, '[]');
    state.startStepDrag(event(100), key); globalThis.document.elementFromPoint = () => null; state.moveStepDrag(event(180)); state.endStepDrag(event(180));
    assert.equal(state.payload, '[]'); assert.equal(state.draft, null);
});
test('drag cannot edit, delete, arrow-move, or submit a card during gesture', () => {
    const { state, event } = reorderFixture(); state.startStepDrag(event(100), state.current.steps[0].key);
    state.editStep(0); state.removeStep(0); state.moveStep(0, 1);
    assert.equal(state.draft, null); assert.equal(state.current.steps.length, 3); assert.equal(state.payload, '[]');
    let prevented = false; state.listeners.submit({ preventDefault() { prevented = true; } }); assert.equal(prevented, true);
    state.cancelStepDrag(); state.editStep(); state.startStepDrag(event(100), state.current.steps[0].key); assert.equal(state.stepDrag, null);
});
test('arrow reordering preserves instructions and content, respects boundaries, and survives reload serialization', () => {
    const { state, row } = reorderFixture(); state.moveStep(0, -1); assert.equal(state.payload, '[]');
    state.moveStep(0, 1); state.moveStep(1, 1); state.moveStep(2, 1);
    const payload = JSON.parse(state.payload);
    assert.deepEqual(payload[0].steps.map(step => step.id), [20, 30, 10]);
    assert.deepEqual(payload[0].steps[2], row.steps[0]);
    const reload = make(payload); assert.deepEqual(send(reload), payload[0]);
});
test('scenario switch during drag cancels without moving steps across scenarios', () => {
    const { state, event, target } = reorderFixture(); state.scenarios.push(state.prepare({ ...scenario(2), steps: [] }));
    state.startStepDrag(event(100), state.current.steps[0].key); target(2);
    state.selected = state.scenarios[1].key; state.moveStepDrag(event(180));
    assert.equal(state.stepDrag, null); assert.equal(state.current.steps.length, 0); assert.equal(state.payload, '[]');
});

test('drop detection uses the captured editor root even when Alpine binds $el to the handle', () => {
    const { state, event, target } = reorderFixture();
    const handle = { contains: () => false, querySelector: () => null };
    state.$el = handle;
    state.startStepDrag(event(190), state.current.steps[2].key);
    target(0); state.moveStepDrag(event(110));
    assert.equal(state.stepDrag.target, state.current.steps[0].key);
    state.endStepDrag(event(110));
    assert.deepEqual(state.current.steps.map(step => step.id), [30, 10, 20]);
});

for (const surface of ['title', 'preview', 'background']) {
    test(`dragging the card ${surface} reorders without opening an editor`, () => {
        const { state, event, target } = reorderFixture();
        state.startStepDrag(event(190, 'mouse', { target: { closest: () => null } }), state.current.steps[2].key);
        target(0); state.moveStepDrag(event(110)); state.endStepDrag(event(110));
        assert.deepEqual(state.current.steps.map(step => step.id), [30, 10, 20]); assert.equal(state.draft, null);
    });
}
test('buttons, menus and editing fields cannot start a card drag', () => {
    for (const tag of ['button', 'input', 'textarea', 'select', 'a', 'summary', 'contenteditable', 'menu']) {
        const { state, event } = reorderFixture();
        state.startStepDrag(event(100, 'touch', { target: { closest: () => ({ tag }) } }), state.current.steps[0].key);
        assert.equal(state.stepDrag, null);
    }
});
test('reordering an expanded card header preserves the open editor and its index', () => {
    const { state, event, target } = reorderFixture(); state.editStep(2); state.draft.short_description = 'Unsaved instructions';
    state.startStepDrag(event(190), state.current.steps[2].key); target(0); state.moveStepDrag(event(110)); state.endStepDrag(event(110));
    assert.equal(state.stepIndex, 0); assert.equal(state.draft.short_description, 'Unsaved instructions');
    state.applyStep(); assert.equal(JSON.parse(state.payload)[0].steps[0].short_description, 'Unsaved instructions');
});
test('Alt arrows reorder focused headers; field keypresses do not reorder; Escape cancels drag', () => {
    const { state, event } = reorderFixture(); const header = {};
    const keyboard = { target: header, currentTarget: header, altKey: true, key: 'ArrowUp', preventDefault() {} };
    state.cardKeydown(keyboard, 2); assert.deepEqual(state.current.steps.map(step => step.id), [10, 30, 20]);
    state.cardKeydown({ ...keyboard, target: {} }, 2); assert.deepEqual(state.current.steps.map(step => step.id), [10, 30, 20]);
    state.startStepDrag(event(100), state.current.steps[0].key); state.cardKeydown({ ...keyboard, key: 'Escape' }, 0); assert.equal(state.stepDrag, null);
});
test('menu opens, closes and Edit preserves advanced data on cancel', () => {
    const state = make(); const key = state.current.steps[0].key;
    state.toggleMenu(key); assert.equal(state.openMenuKey, key); state.closeMenu(); assert.equal(state.openMenuKey, null);
    state.editMenuStep(0); state.draft.title = 'Temporary'; state.close(); assert.equal(state.payload, '[]');
    assert.deepEqual(send(state).steps[0].blocks, scenario().steps[0].blocks);
});




test('main contract omits shared scope and explicit positions while list order changes', () => {
    const { state } = reorderFixture();
    state.moveStep(2, -1);
    const saved = JSON.parse(state.payload)[0];
    assert.deepEqual(saved.steps.map(step => step.id), [10, 30, 20]);
    assert.equal(Object.hasOwn(saved, 'everyone'), false);
    assert.ok(saved.steps.every(step => !Object.hasOwn(step, 'sort_order')));
});

test('main scenario creation remains available and coordinates with Requirements', () => {
    const state = make([], { deferred: true });
    state.startScenario(); state.scenarioDraft.application_type = 'new';
    state.scenarioDraft.applicant_type = 'adult'; state.addScenario();
    assert.equal(state.scenarios.length, 1);
    assert.equal(state.events.at(-1).name, 'draft-guide-scenario');
    state.editStep(); state.draft.title = 'First step'; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[0].title, 'First step');
});

test('draggable card view expands inline and keeps main scenario controls', () => {
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/application-guide-editor.blade.php', import.meta.url), 'utf8');
    assert.match(view, /startStepDrag/); assert.match(view, /cardKeydown/);
    assert.match(view, /startScenario/); assert.doesNotMatch(view, /<dialog|x-teleport|Everyone|Applicability/);
});

test('wizard Done stores simple link and note in existing blocks, preserving additional content and IDs', () => {
    const row = scenario();
    row.steps[0].blocks.push({ id: 210, type: 'official_link', content: { label: 'Another site', url: 'https://example.org', description: 'Keep me' } },
        { id: 211, type: 'reminder', content: { body: { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Plain note' }] }] } } },
        { id: 212, type: 'reminder', content: { body: document('Keep bold reminder') } });
    const state = make([row], { wizard: true });
    state.editStep(0);
    assert.equal(state.simpleOfficialUrl, 'https://example.gov.ph/book');
    assert.equal(state.simpleReminderText, 'Plain note');
    state.simpleOfficialUrl = 'https://passport.gov.ph';
    state.simpleReminderText = 'Bring receipt.\nArrive early.';
    assert.equal(state.current.dirty, false);
    state.applyStep();
    const saved = JSON.parse(state.payload)[0].steps[0];
    assert.equal(saved.id, 10);
    assert.deepEqual(saved.blocks[1], { ...row.steps[0].blocks[1], content: { ...row.steps[0].blocks[1].content, url: 'https://passport.gov.ph' } });
    assert.deepEqual(saved.blocks.find(block => block.id === 210), row.steps[0].blocks.find(block => block.id === 210));
    assert.equal(saved.blocks.find(block => block.id === 211).content.body.content[1].content[0].text, 'Arrive early.');
    assert.deepEqual(saved.blocks.find(block => block.id === 212), row.steps[0].blocks.find(block => block.id === 212));
    assert.deepEqual(saved.blocks[0], row.steps[0].blocks[0]);
});

test('wizard keeps formatted reminders advanced and unchanged and handles optional fields without duplicates', () => {
    const row = scenario(); row.steps[0].blocks.push({ id: 220, type: 'reminder', content: { body: document('Rich note') } });
    const state = make([row], { wizard: true }); state.editStep(0);
    assert.equal(state.simpleReminderSupported, false);
    state.simpleReminderText = 'Must not flatten'; state.applyStep();
    assert.equal(state.payload, '[]');
    state.editStep(); state.draft.title = 'Simple';
    state.simpleOfficialUrl = 'https://passport.gov.ph'; state.simpleOfficialUrl = 'https://passport.gov.ph/book';
    state.simpleReminderText = 'Bring receipt'; state.simpleReminderText = 'Bring signed receipt';
    assert.equal(state.draft.blocks.length, 2);
    state.advancedOpen = true; state.advancedOpen = false;
    assert.equal(state.simpleReminderText, 'Bring signed receipt');
    state.simpleOfficialUrl = ''; state.simpleReminderText = ''; assert.equal(state.draft.blocks.length, 0);
    state.simpleOfficialUrl = 'javascript:alert(1)'; assert.equal(state.applyStep(), false);
    state.simpleOfficialUrl = 'https://passport.gov.ph'; assert.equal(state.applyStep(), true);
    assert.equal(JSON.parse(state.payload)[0].steps[1].blocks[0].type, 'official_link');
});

test('wizard Continue blocks even a valid open step without applying it', () => {
    const state = make([scenario()], { wizard: true }); state.editStep(); state.draft.title = 'Not done';
    let prevented = false;
    state.listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.error, 'Finish or cancel the open application step before continuing.');
    assert.equal(state.current.steps.length, 1); assert.ok(state.draft); assert.equal(state.savePromise, null);
});

test('checklist item additions keep existing items and use valid rich-text documents', () => {
    const state = make([scenario()], { wizard: true }); state.editStep(0);
    const checklist = state.draft.blocks.find(block => block.type === 'checklist');
    const original = clone(checklist.content.items);
    state.addItem(checklist);
    assert.deepEqual(checklist.content.items.slice(0, 2), original);
    assert.deepEqual(checklist.content.items[2].body, { type: 'doc', content: [{ type: 'paragraph' }] });
});

test('wizard Continue awaits persistence, blocks duplicate requests and only navigates after success', async () => {
    const originalFetch = globalThis.fetch, originalFormData = globalThis.FormData;
    try {
        const state = make([scenario()], { wizard: true, nextUrl: 'http://localhost/creation/offices' });
        state.form.action = 'http://localhost/creation/guide';
        window.location = { href: state.form.action, assign(url) { this.destination = url; } };
        let resolveSave, calls = 0;
        globalThis.FormData = class {};
        globalThis.fetch = async () => { calls++; return new Promise(resolve => { resolveSave = resolve; }); };
        state.editStep(0); state.draft.title = 'Changed'; state.applyStep();
        state.listeners.submit({ preventDefault() {} });
        assert.equal(state.saving, true); assert.equal(window.location.destination, undefined);
        state.listeners.submit({ preventDefault() {} }); assert.equal(calls, 1);
        assert.equal(JSON.parse(state.$refs.guidePayload.value)[0].steps[0].title, 'Changed');
        resolveSave({ ok: true, redirected: true, url: 'http://localhost/creation/offices' });
        await state.savePromise;
        assert.equal(window.location.destination, 'http://localhost/creation/offices');
        assert.equal(state.submitting, true); assert.equal(state.saving, false);
    } finally { globalThis.fetch = originalFetch; globalThis.FormData = originalFormData; }
});

test('wizard save errors retain the full draft for validation, server and network failures', async () => {
    const originalFetch = globalThis.fetch, originalFormData = globalThis.FormData;
    try {
        globalThis.FormData = class {};
        for (const failure of ['validation', 'server', 'network', 'session']) {
            const state = make([scenario()], { wizard: true, nextUrl: 'http://localhost/creation/offices' });
            state.form.action = 'http://localhost/creation/guide';
            window.location = { href: state.form.action, assign() { assert.fail('Must not navigate after failure'); } };
            state.editStep(0); state.draft.title = 'Keep my work'; state.applyStep();
            const payload = state.payload;
            globalThis.fetch = async () => {
                if (failure === 'network') throw new Error('Network unavailable');
                if (failure === 'session') return { ok: true, redirected: true, url: 'http://localhost/login' };
                return { ok: false, json: async () => failure === 'validation' ? { errors: { 'application_guide.0.steps.0.title': ['Title is invalid'] } } : { message: 'Server failed' } };
            };
            state.listeners.submit({ preventDefault() {} }); await state.savePromise;
            assert.ok(state.error); assert.equal(state.saving, false); assert.equal(state.submitting, false);
            assert.equal(state.payload, payload); assert.equal(state.current.dirty, true);
        }
    } finally { globalThis.fetch = originalFetch; globalThis.FormData = originalFormData; }
});

test('preview uses the current draft without applying, dirtying, or changing serialized guide data', () => {
    const state = make([scenario()], { wizard: true });
    const original = clone(state.current);
    assert.equal(state.payload, '[]');
    assert.equal(state.previewSteps[0], state.current.steps[0]);
    state.editStep(0); state.draft.title = 'Live title'; state.draft.short_description = 'Live instructions';
    state.simpleOfficialUrl = 'https://passport.gov.ph';
    state.simpleReminderText = 'Live reminder';
    assert.equal(state.previewSteps[0], state.draft);
    assert.equal(state.previewSteps[0].title, 'Live title');
    const reminder = state.previewSteps[0].blocks.find(block => block.type === 'reminder');
    assert.equal(state.previewContent(reminder.content.body), 'Live reminder');
    assert.equal(state.officialLinkHost(state.previewSteps[0]), 'passport.gov.ph');
    state.previewSteps.forEach(step => step.blocks.forEach(block => state.previewContent(block.content.body)));
    assert.equal(state.payload, '[]'); assert.deepEqual(state.current, original);
    state.close(); assert.deepEqual(state.previewSteps, original.steps);
});

test('preview is empty without steps and follows new steps Done reordering and local deletion', () => {
    const row = scenario(); row.steps = [];
    const state = make([row], { wizard: true });
    assert.deepEqual(state.previewSteps, []);
    state.editStep(); state.draft.title = 'Book appointment';
    assert.equal(state.previewSteps.length, 1); assert.equal(state.current.steps.length, 0);
    state.applyStep();
    state.editStep(); state.draft.title = 'Prepare'; state.applyStep();
    state.moveStep(1, -1);
    assert.deepEqual(state.previewSteps.map(step => step.title), ['Prepare', 'Book appointment']);
    const payload = state.payload;
    state.previewSteps; assert.equal(state.payload, payload);
    state.removeStep(0);
    assert.deepEqual(state.previewSteps.map(step => step.title), ['Book appointment']);
});

test('preview leaves saved rich blocks and identifiers untouched and uses safe compact reference text', () => {
    const state = make([scenario()], { wizard: true,
        previewReferences: { requirements: 5, fees: 2, offices: 3, processingTime: '10–15 working days' },
        stepTypes: { office_visit: 'Office Visit' },
    });
    const payload = send(state);
    assert.equal(state.previewStepType(state.previewSteps[0]), 'Office Visit');
    assert.equal(state.previewReference('requirements'), '5 requirements to prepare');
    assert.equal(state.previewReference('fees'), 'Processing fees available');
    assert.equal(state.previewReference('offices'), '3 offices available');
    assert.equal(state.previewReference('processing_time'), '10–15 working days');
    assert.equal(state.previewUrl('javascript:alert(1)'), null);
    assert.equal(state.previewUrl('https://passport.gov.ph'), 'https://passport.gov.ph/');
    assert.equal(state.previewContent(document('<script>Text only</script>')), '<script>Text only</script>');
    assert.ok(state.previewContent('x'.repeat(1000)).length <= 601);
    assert.deepEqual(JSON.parse(state.payload)[0], payload);
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/wizard/app-preview.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(view, /x-html|x-model|name=|type="submit"/);
});

test('wizard optional link and note disclosures start collapsed and preserve saved values across toggles', () => {
    const row = scenario();
    row.steps[0].blocks.push({ id: 230, type: 'reminder', content: { body: { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Bring receipt' }] }] } } });
    const state = make([row], { wizard: true }); state.editStep(0);
    assert.equal(state.advancedOpen, false); assert.equal(state.optionalLinkOpen, false); assert.equal(state.reminderOpen, false);
    assert.equal(state.simpleOfficialLabel, 'Book online'); assert.equal(state.simpleOfficialUrl, 'https://example.gov.ph/book');
    assert.equal(state.simpleReminderText, 'Bring receipt');
    const original = JSON.stringify(state.draft);
    state.advancedOpen = true; state.optionalLinkOpen = true; state.reminderOpen = true;
    state.optionalLinkOpen = false; state.reminderOpen = false; state.advancedOpen = false;
    assert.equal(JSON.stringify(state.draft), original); assert.equal(state.payload, '[]');
    state.optionalLinkOpen = true; state.simpleOfficialLabel = 'DFA Passport Appointment System';
    state.simpleOfficialUrl = 'https://passport.gov.ph'; state.simpleReminderText = 'Bring appointment receipt';
    const edited = JSON.stringify(state.draft);
    state.optionalLinkOpen = false; state.reminderOpen = false; state.advancedOpen = false;
    assert.equal(JSON.stringify(state.draft), edited);
    state.applyStep();
    const saved = JSON.parse(state.payload)[0].steps[0];
    assert.equal(saved.blocks.find(block => block.id === 102).content.label, 'DFA Passport Appointment System');
    assert.equal(saved.blocks.find(block => block.id === 102).content.description, 'Choose a date.');
    assert.equal(saved.blocks.find(block => block.type === 'reminder').id, 230);
    state.editStep(0);
    assert.equal(state.optionalLinkOpen, false); assert.equal(state.reminderOpen, false);
    assert.equal(state.simpleOfficialLabel, 'DFA Passport Appointment System');
});

test('wizard link label before URL uses one existing link structure and requirements use reference-only content', () => {
    const state = make([scenario()], { wizard: true }); state.editStep(); state.draft.title = 'Prepare Your Requirements';
    state.simpleOfficialLabel = 'DFA Passport Appointment System'; state.simpleOfficialUrl = 'https://passport.gov.ph';
    state.addBlock('requirements'); state.applyStep();
    const blocks = JSON.parse(state.payload)[0].steps[1].blocks;
    assert.equal(blocks.filter(block => block.type === 'official_link').length, 1);
    assert.deepEqual(blocks.find(block => block.type === 'official_link').content, { label: 'DFA Passport Appointment System', url: 'https://passport.gov.ph', description: '' });
    assert.deepEqual(blocks.find(block => block.type === 'requirements').content, { intro: { type: 'doc', content: [{ type: 'paragraph' }] } });
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/application-guide-step-editor.blade.php', import.meta.url), 'utf8');
    assert.ok(view.indexOf('More Content &amp; Settings <span') < view.indexOf('>Optional Link</summary>'));
    assert.match(view, /:open="optionalLinkOpen"/); assert.match(view, /:open="reminderOpen"/);
    assert.match(view, /Use existing ID information/);
    assert.match(view, /These blocks use the information already saved for this Government ID/);
    const preview = readFileSync(new URL('../../resources/views/admin/government_ids/wizard/app-preview.blade.php', import.meta.url), 'utf8');
    assert.match(preview, /requirements: 'Requirements'/); assert.match(preview, /View applicable fees/);
    assert.doesNotMatch(preview, /x-model|x-html/);
});

test('wizard references are unique per step at the action layer and can be re-added after removal', () => {
    const state = make([scenario()], { wizard: true }); state.editStep();
    for (const type of ['requirements', 'fees', 'offices', 'processing_time']) {
        state.addBlock(type); const original = clone(state.draft.blocks);
        state.addBlock(type); state.addBlock(type);
        assert.deepEqual(state.draft.blocks, original);
        assert.equal(state.hasReference(type), true);
        state.removeBlock(state.draft.blocks.findIndex(block => block.type === type));
        assert.equal(state.hasReference(type), false);
        state.addBlock(type);
        assert.equal(state.draft.blocks.filter(block => block.type === type).length, 1);
    }
    state.addBlock('custom'); state.addBlock('custom');
    assert.equal(state.draft.blocks.filter(block => block.type === 'custom').length, 2);
    const custom = state.draft.blocks.find(block => block.type === 'custom');
    const before = clone(custom); state.changeBlockType(custom, 'requirements');
    assert.deepEqual(custom, before);
    assert.deepEqual(Object.keys(state.contentBlockNames), ['instructions', 'checklist', 'reminder', 'official_link', 'custom']);
});

test('saved duplicate references are warned about and previewed once without silently editing data', () => {
    const row = scenario();
    const original = row.steps[0].blocks.find(block => block.type === 'requirements');
    row.steps[0].blocks.push({ ...clone(original), id: 500, section_title: 'Extra saved reference' });
    const state = make([row], { wizard: true });
    assert.equal(state.previewBlocks(state.current.steps[0]).filter(block => block.type === 'requirements').length, 1);
    assert.equal(state.payload, '[]');
    state.editStep(0);
    assert.deepEqual(state.duplicateReferenceLabels, ['Requirements']);
    assert.equal(state.referenceEntries.filter(entry => entry.block.type === 'requirements').length, 2);
    assert.equal(state.referenceEntries.find(entry => entry.block.id === 500).block.section_title, 'Extra saved reference');
    state.addBlock('requirements');
    assert.equal(state.draft.blocks.filter(block => block.type === 'requirements').length, 2);
    state.draft.title = 'Intentional title edit'; state.applyStep();
    assert.deepEqual(JSON.parse(state.payload)[0].steps[0].blocks, row.steps[0].blocks);
    state.editStep(0); state.removeBlock(state.draft.blocks.findIndex(block => block.id === 500));
    assert.deepEqual(state.duplicateReferenceLabels, []);
    state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[0].blocks.filter(block => block.type === 'requirements').length, 1);
});

test('normal edit keeps its existing generic references and reference duplication behavior', () => {
    const state = make([scenario()]); state.editStep();
    state.addBlock('requirements'); state.addBlock('requirements');
    assert.equal(state.draft.blocks.length, 2);
    assert.equal(state.contentBlockNames, state.availableBlockNames);
    assert.equal(state.blockNames.requirements, 'Existing requirements');
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/application-guide-step-editor.blade.php', import.meta.url), 'utf8');
    assert.match(view, /:aria-pressed="hasReference/);
    assert.match(view, /entry in referenceEntries/);
    assert.match(view, /x-if="!isLiveReference\(block.type\)"/);
    const preview = readFileSync(new URL('../../resources/views/admin/government_ids/wizard/app-preview.blade.php', import.meta.url), 'utf8');
    assert.match(preview, /previewBlocks\(previewStep\)/);
});

test('Edit guide preview follows selected existing checklist without copying references', () => {
    const first = scenario(1); const second = { ...scenario(2), application_type: 'renewal' };
    const state = make([first, second], { wizard: true, requirementsByScenario: { 1: 2, 2: 5 }, previewReferences: { fees: 2 } });
    assert.equal(state.previewReference('requirements'), '2 requirements to prepare');
    state.selected = state.scenarios[1].key;
    assert.equal(state.previewReference('requirements'), '5 requirements to prepare');
    state.editStep(0); state.draft.title = 'Updated renewal'; state.applyStep();
    const saved = JSON.parse(state.payload);
    assert.equal(saved.length, 1); assert.equal(saved[0].id, 2);
    assert.deepEqual(saved[0].steps[0].blocks, second.steps[0].blocks);
    assert.equal(state.scenarios[0].steps[0].title, first.steps[0].title);
});
