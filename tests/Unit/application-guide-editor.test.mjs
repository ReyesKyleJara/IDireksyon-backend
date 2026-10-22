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
    ...['text', 'heading', 'note', 'warning'].map((type, i) => ({ id: 109 + i, type, content: { body: '<script>literal text</script>' } })),
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

test('Everyone restores as one explicit scope and never creates a Requirements profile', () => {
    const shared = { everyone: true, label: 'Everyone', steps: [] };
    const state = make([shared], { deferred: true, oldPayload: JSON.stringify([{ everyone: true, steps: [] }]) });
    assert.equal(state.scenarios.length, 1);
    assert.equal(state.current.key, 'everyone');
    assert.equal(state.current.client_key, undefined);
    assert.equal(state.events.length, 0);
    state.editStep(); state.draft.title = 'Book appointment'; state.applyStep();
    const payload = JSON.parse(state.payload)[0];
    assert.equal(payload.everyone, true);
    assert.equal(payload.id, undefined);
    assert.equal(payload.steps[0].title, 'Book appointment');
});

test('changing step applicability preserves IDs and blocks and submits both scopes', () => {
    const original = scenario();
    const state = make([original, { everyone: true, steps: [] }]);
    state.editStep(0); state.draftAppliesTo = 'everyone';
    assert.equal(state.applyStep(), true);
    const payload = JSON.parse(state.payload);
    assert.deepEqual(payload[0].steps, []);
    assert.equal(payload[1].everyone, true);
    assert.deepEqual(payload[1].steps[0], original.steps[0]);
});

test('backend summaries mark manual profiles without interpreting their names', () => {
    const input = { ...scenario(), applicability: { summary: 'Students · New Application · Age 18+', needs_rule_setup: true } };
    const state = make([input]);
    assert.match(state.label(state.current), /Needs rule setup/);
    state.editStep(0); assert.equal(state.draftAppliesTo, state.current.key);
    state.draft.title = 'Manual instructions'; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].id, input.id);
});

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
    assert.deepEqual(JSON.parse(state.payload)[0].steps[1], { title: 'Submit', short_description: 'Hand the form to the officer.', type: 'general', sort_order: 1, blocks: [] });
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
test('template has inline cards, applicability and position controls, collapsed settings and safe summaries', () => {
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/application-guide-editor.blade.php', import.meta.url), 'utf8');
    const fields = readFileSync(new URL('../../resources/views/admin/government_ids/partials/application-guide-step-editor.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(view, /x-teleport|aria-modal|<form|x-html/); assert.match(view, /:key="step.key"/);
    const main = fields.split('<details')[0]; assert.equal((main.match(/<(input|textarea) /g) || []).length, 3);
    assert.match(fields, /More Content &amp; Settings/); assert.match(fields, /Save step/);
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
    assert.equal(state.startScenario, undefined);
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
        assert.deepEqual(saved.steps[2], { ...row.steps[0], sort_order: 2 }); assert.equal(saved.id, row.id);
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
    assert.deepEqual(payload[0].steps[2], { ...row.steps[0], sort_order: 2 });
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


test('new steps append after explicit positions and blank position uses list fallback', () => {
    const row = scenario(); row.steps[0].sort_order = 10;
    const state = make([row]); state.editStep();
    assert.equal(state.draft.sort_order, 11);
    state.draft.title = 'Next step'; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[1].sort_order, 11);
    state.editStep(1); state.draft.sort_order = ''; state.applyStep();
    assert.equal(JSON.parse(state.payload)[0].steps[1].sort_order, undefined);
});
