import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import editor from '../../resources/js/government-id-checklists.js';

const clone = value => structuredClone(value);
const item = (id, extra = {}) => ({ id, type: 'custom', government_id_id: null, document_id: null,
    custom_name: 'Photo ' + id, submission_format: 'custom', submission_format_custom: 'Signed print',
    copies: 3, quantity: 2, instructions: '<script>alert(1)</script>', ...extra });
const legacy = rule => ({ id: 20, title: null, rule, condition_type: 'custom', condition_custom: 'Only when requested', items: [item(30), item(31)] });
const advanced = () => ({ id: 21, title: 'Proof', rule: 'all', condition_type: 'representative', condition_custom: null,
    ways: [{ id: 40, required_count: 2, qualification_type: 'custom', qualification_scope: 'at_least_one',
        qualification_custom: 'Show address', items: [item(32), item(33), item(34)] },
    { id: 41, required_count: 1, qualification_type: 'unexpired', qualification_scope: 'every', qualification_custom: null,
        items: [item(35, { type: 'document', document_id: 50, custom_name: null, quantity: null })] }] });
const scenario = groups => ({ id: 10, application_type: 'new', application_type_custom: null, applicant_type: 'custom',
    applicant_type_custom: 'Children', min_age: 0, max_age: 17, groups });
function make(groups = [], options = {}) {
    const state = editor({ editable: true, options: [], countableFormats: ['custom'], formats: {}, conditions: {},
        applications: { new: 'First-Time Application' }, applicants: { custom: 'Custom' }, qualifications: {}, ...options });
    state.$nextTick = fn => fn(); state.$refs = {}; state.$dispatch = () => {};
    state.$el = { querySelector: () => null, closest: () => null };
    state.draft = { ...scenario(groups), groups: groups.map(group => state.decorate(group)) };
    state.baseline = JSON.stringify(state.payload());
    return state;
}
globalThis.window = { confirm: () => true, addEventListener() {}, removeEventListener() {} };
globalThis.document = { activeElement: null };

for (const rule of ['all', 'choose_one']) {
    test(`legacy ${rule} round-trips without ways or lost identities`, () => {
        const original = scenario([legacy(rule)]); const state = make(original.groups);
        const expected = clone(original); delete expected.id;
        assert.deepEqual(state.payload(), expected);
        state.editRequirement(0); assert.equal(state.finishRequirement(), true);
        assert.deepEqual(state.payload(), expected);
        assert.equal(state.dirty(), false);
        assert.equal(state.payload().groups[0].ways, undefined);
    });
}
test('advanced OR ways and hidden details round-trip unchanged', () => {
    const original = scenario([advanced()]); const state = make(original.groups);
    const expected = clone(original); delete expected.id;
    state.editRequirement(0);
    state.editing.ways[0].qualificationOpen = true;
    state.editing.ways[0].items[0].details = true;
    state.editing.ways[0].items[0].details = false;
    assert.equal(state.finishRequirement(), true);
    assert.deepEqual(state.payload(), expected);
    assert.equal(state.payload().groups[0].ways[1].items[0].document_id, 50);
});
test('cancel card and whole checklist do not mutate loaded records', () => {
    const original = scenario([advanced()]); const untouched = clone(original);
    const state = make(original.groups); state.checklists = [original];
    state.editRequirement(0); state.editing.ways[0].items[0].instructions = 'Changed';
    assert.equal(state.previewGroups[0].ways[0].items[0].instructions, 'Changed');
    state.cancelRequirement(); assert.deepEqual(state.payload().groups, untouched.groups);
    state.removeRequirement(0); state.close(); assert.deepEqual(original, untouched);
});
test('legacy conversion requires explicit consent and cancel preserves storage', () => {
    const state = make([legacy('all')]); state.editRequirement(0);
    window.confirm = () => false; state.addWay();
    assert.equal(state.editing.legacy, true); assert.equal(state.editing.ways.length, 1);
    state.selection(state.editing.ways[0], 'accepted'); state.setCount(state.editing.ways[0], 2);
    assert.equal(state.editing.ways[0].required_count, 1);
    window.confirm = () => true; state.addWay(); assert.equal(state.editing.legacy, false);
    assert.equal(state.editing.ways[0].items[0].id, 30);
});
test('all-listed counts follow item addition/removal and preserve legacy rule', () => {
    const state = make([legacy('all')]); state.editRequirement(0); const way = state.editing.ways[0];
    state.addItem(way); assert.equal(way.required_count, 3);
    state.removeItem(way, 2); assert.equal(way.required_count, 2);
    state.selection(way, 'accepted'); assert.equal(way.required_count, 1);
    assert.equal(state.editing.rule, 'choose_one');
    state.finishRequirement(); assert.equal(state.payload().groups[0].rule, 'choose_one');
    assert.equal(state.payload().groups[0].ways, undefined);
});
test('create payload restores legacy groups and preserves client keys and zero ages', () => {
    const raw = { ...scenario([legacy('all'), advanced()]), client_key: 'draft-1' }; delete raw.id;
    const state = make([], { deferred: true, checklists: [raw] });
    const sequence = state.sequence;
    const payload = JSON.parse(state.creationPayload);
    assert.equal(state.sequence, sequence, 'serialization must not mutate reactive state');
    assert.deepEqual(payload, [raw]);
    const restored = make([], { deferred: true, oldPayload: JSON.stringify(payload) }); restored.init();
    assert.deepEqual(JSON.parse(restored.creationPayload), [raw]);
    restored.open(restored.checklists[0]); restored.applyDraft();
    assert.deepEqual(JSON.parse(restored.creationPayload), [raw]);
});
test('preview retains condition and OR scopes without mutating submitted data', () => {
    const state = make([advanced()]); state.editRequirement(0);
    assert.equal(state.previewGroups[0].condition_type, 'representative');
    state.editing.conditional = false;
    assert.equal(state.previewGroups[0].condition_type, 'always');
    assert.equal(state.payload().groups[0].condition_type, 'representative');
    assert.match(state.qualification(state.editing.ways[0]), /^At least one/);
});
test('failed network saves retain draft and nested data', async () => {
    const state = make([advanced()]); const before = state.payload();
    state.request = async () => { throw new Error('offline'); };
    await state.save(); assert.deepEqual(state.payload(), before);
    assert.equal(state.saving, false); assert.equal(state.errors.length, 1);
});
test('preview uses text bindings, and inline editor has no nested form or dialog', () => {
    const preview = readFileSync(new URL('../../resources/views/admin/government_ids/partials/requirements-preview.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(preview, /x-html|\{!!/);
    assert.match(preview, /x-text="item.instructions"/);
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/checklists.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(view, /<form|<dialog|x-teleport/);
});

test('new cards use ways, cancel is local, and completed additions retain order', () => {
    const state = make([legacy('all')]);
    state.addRequirement(); state.selection(state.editing.ways[0], 'all');
    state.addItem(state.editing.ways[0]); state.editing.ways[0].items[0].custom_name = 'New requirement';
    state.finishRequirement();
    assert.equal(state.payload().groups[0].ways, undefined);
    assert.equal(state.payload().groups[1].ways[0].items[0].custom_name, 'New requirement');
    state.addRequirement(); state.cancelRequirement(); assert.equal(state.draft.groups.length, 2);
});
test('scenario switching can be cancelled and a failed validation keeps advanced drafts', async () => {
    const state = make([advanced()]); state.checklists = [scenario([legacy('all')])];
    const before = state.payload();
    window.confirm = () => false; state.selectChecklist('0'); assert.deepEqual(state.payload(), before);
    window.confirm = () => true;
    state.request = async () => { throw { messages: ['Invalid scenario'] }; };
    await state.save(); assert.deepEqual(state.errors, ['Invalid scenario']); assert.deepEqual(state.payload(), before);
});

test('scenario selector is hidden when empty or defining a new scenario', () => {
    const state = make(); state.close(true);
    assert.equal(state.showScenarioSelector, false);
    state.checklists = [scenario([advanced()])];
    assert.equal(state.showScenarioSelector, true);
    state.open(null);
    assert.equal(state.showScenarioSelector, false);
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/checklists.blade.php', import.meta.url), 'utf8');
    assert.match(view, /x-cloak x-show="showScenarioSelector"/);
    assert.match(view, /<details :open="selected === ''"/);
});

test('selecting each saved scenario populates its types and exact nested requirements', () => {
    const state = make();
    state.checklists = [scenario([advanced()]), { ...scenario([legacy('choose_one')]), id: 11, application_type: 'renewal', applicant_type: 'adult' }];
    const original = clone(state.checklists);
    for (const index of [0, 1]) {
        state.selectChecklist(String(index));
        assert.equal(state.selected, String(index));
        assert.equal(state.showScenarioSelector, true);
        assert.equal(state.draft.id, original[index].id);
        assert.equal(state.draft.applicant_type, original[index].applicant_type);
        assert.equal(state.draft.application_type, original[index].application_type);
        const expected = clone(original[index]); delete expected.id;
        assert.deepEqual(state.payload(), expected);
    }
    const before = state.payload(); state.selectChecklist('99');
    assert.deepEqual(state.payload(), before);
    assert.deepEqual(state.checklists, original);
});

test('preview labels use clear placeholders for missing selections and custom names', () => {
    const state = make();
    for (const row of [undefined, null, {}, { applicant_type: null, application_type: null },
        { applicant_type: 'missing', application_type: 'missing' }]) {
        assert.equal(state.checklistLabel(row), 'Select applicant • Select application');
    }
    assert.equal(state.checklistLabel({ applicant_type: 'custom', min_age: 0, max_age: 17, application_type: 'custom', application_type_custom: ' ' }), 'Ages 0–17 • Select application');
    assert.equal(state.checklistLabel(scenario([])), 'Children (Ages 0–17) • First-Time Application');
});

test('new immediate scenarios cannot duplicate saved scenarios or send a create request', async () => {
    const state = make([advanced()]); state.checklists = [scenario([legacy('all')])];
    state.draft.id = null;
    const before = clone(state.checklists);
    state.request = async () => { assert.fail('duplicate must not send POST'); };
    await state.save();
    assert.match(state.errors[0], /already has a checklist/);
    assert.deepEqual(state.checklists, before);
    assert.equal(state.saving, false);
});

test('new deferred scenarios cannot duplicate applied requirements', () => {
    const state = make([advanced()], { deferred: true });
    const existing = { ...scenario([legacy('all')]), client_key: 'existing' }; delete existing.id;
    state.checklists = [existing]; state.draft.id = null;
    const before = state.creationPayload;
    state.applyDraft();
    assert.match(state.errors[0], /already has a checklist/);
    assert.equal(state.creationPayload, before);
});
