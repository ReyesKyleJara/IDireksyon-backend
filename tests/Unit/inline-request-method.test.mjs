import test from 'node:test';
import assert from 'node:assert/strict';
import { inlineRequestMethods, providerCategory } from '../../resources/js/request-option-editor.js';

const provider = (id, name, channel = 'online') => ({id, channel: {channel, provider: name, fee_ids: [], fulfillment: []}, groups: [], steps: [], fees: []});
function fixture() {
    const methods = [provider(1, 'Counter', 'outlet'), provider(2, 'PSAHelpline.ph'), provider(3, 'PSA Serbilis')];
    const state = inlineRequestMethods({methods, blank: provider(null, '', ''), saveUrl: '/save', returnUrl: '/channels'});
    globalThis.window = {confirm: () => false};
    return state;
}

test('groups providers independently and keeps a single editor open', () => {
    const state = fixture();
    assert.equal(state.providers('online').length, 2);
    state.open(state.methods[1]); assert.equal(state.active, 2);
    state.open(state.methods[2]); assert.equal(state.active, 3);
    assert.equal(state.draft.channel.provider, 'PSA Serbilis');
    state.open(state.methods[2]); assert.equal(state.active, null);
    assert.equal(providerCategory('partner'), 'hybrid');
    assert.equal(providerCategory('in_person'), 'outlet');
});

test('edits clones, protects dirty drafts when switching or collapsing, and confirms cancel', () => {
    const state = fixture(); state.open(state.methods[1]);
    state.draft.channel.provider = 'Changed';
    assert.equal(state.dirty, true);
    assert.equal(state.methods[1].channel.provider, 'PSAHelpline.ph');
    state.open(state.methods[2]); assert.equal(state.active, 2);
    state.open(state.methods[1]); assert.equal(state.active, 2);
    state.cancel(); assert.equal(state.active, 2);
    window.confirm = () => true;
    state.cancel(); assert.equal(state.draft, null);
    state.open(state.methods[1]); assert.equal(state.draft.channel.provider, 'PSAHelpline.ph');
});

test('blank providers stay local until save and do not infer fulfillment', () => {
    const state = fixture(); state.open();
    state.draft.channel.channel = 'online';
    assert.deepEqual(state.draft.channel.fulfillment, []);
    state.draft.channel.preparation = 'Bring the accepted documents.'; state.addFee();
    assert.equal(state.methods.length, 3);
    assert.equal(state.blank.groups.length, 0);
    assert.deepEqual(state.draft.steps, []);
    window.confirm = () => true; state.cancel(); state.open();
    assert.equal(state.draft.groups.length, 0);
});

test('shared all/selected/none fee scopes remain distinct', () => {
    const state = fixture(); state.open(state.methods[0]);
    state.draft.fees = [{id: 4}, {id: 5}]; state.draft.channel.fee_ids = null;
    assert.equal(state.selectedFee({id: 4}), true);
    state.toggleFee({id: 4}, false); assert.deepEqual(state.draft.channel.fee_ids, [5]);
    state.toggleFee({id: 5}, false); assert.deepEqual(state.draft.channel.fee_ids, []);
    state.toggleFee({id: 4}, true); assert.deepEqual(state.draft.channel.fee_ids, [4]);
});

test('failed validation and network errors retain the unsaved draft', async () => {
    const state = fixture(); state.open(state.methods[0]); state.draft.channel.provider = 'Unsaved';
    const OriginalFormData = globalThis.FormData;
    globalThis.FormData = class {set() {}};
    globalThis.fetch = async () => ({ok: false, json: async () => ({errors: {provider: ['Invalid provider']}})});
    try {
        await state.save({reportValidity: () => true});
        assert.deepEqual(state.errors, ['Invalid provider']); assert.equal(state.draft.channel.provider, 'Unsaved'); assert.equal(state.saving, false);
        globalThis.fetch = async () => {throw new Error('offline');};
        await state.save({reportValidity: () => true});
        assert.equal(state.dirty, true); assert.match(state.errors[0], /still here/);
    } finally {globalThis.FormData = OriginalFormData;}
});

test('navigation protection attaches once and is cleaned up', () => {
    const state = fixture(), listeners = new Map();
    globalThis.window = {confirm: () => false, addEventListener: (name, handler) => listeners.set(name, handler), removeEventListener: name => listeners.delete(name)};
    globalThis.document = {addEventListener: (name, handler) => listeners.set(name, handler), removeEventListener: name => listeners.delete(name)};
    state.init(); state.open(state.methods[0]); state.draft.channel.processing = 'Changed';
    let prevented = false;
    listeners.get('beforeunload')({preventDefault() {prevented = true;}}); assert.equal(prevented, true);
    prevented = false;
    listeners.get('click')({target: {closest: () => ({})}, preventDefault() {prevented = true;}}); assert.equal(prevented, true);
    state.destroy(); assert.equal(listeners.size, 0);
});

test('a successful save navigates back to collapsed server-rendered cards and clears the dirty warning', async () => {
    const state = fixture(); state.open(state.methods[0]); state.draft.channel.processing = 'Saved value';
    const OriginalFormData = globalThis.FormData;
    globalThis.FormData = class {set() {}};
    let returnedTo = null;
    window.location = {assign(url) {returnedTo = url;}};
    globalThis.fetch = async () => ({ok: true, json: async () => ({method: state.draft, revision: 'new'})});
    try {
        await state.save({reportValidity: () => true});
        assert.equal(returnedTo, '/channels'); assert.equal(state.dirty, false);
    } finally {globalThis.FormData = OriginalFormData;}
});

test('unconfigured legacy options are separate and opening one preserves its identity and saved data', () => {
    const state = fixture();
    const legacy = provider(9, '', ''); legacy.groups = [{id: 41, title: 'Saved requirements'}]; legacy.steps = [{id: 55, title: 'Saved guide'}];
    state.methods.push(legacy);
    assert.equal(state.providers('unconfigured').length, 1);
    assert.equal(state.providers('online').length, 2);
    state.open(legacy);
    assert.equal(state.draft.id, 9);
    assert.deepEqual(state.draft.groups, legacy.groups); assert.deepEqual(state.draft.steps, legacy.steps);
    state.draft.channel.provider = 'Configured provider'; state.draft.channel.channel = 'online';
    assert.equal(state.methods.length, 4); assert.equal(legacy.channel.provider, '');
});

test('fee summary respects provider scope and keeps free separate from unknown amounts', () => {
    const state = fixture(); state.open(state.methods[0]);
    assert.equal(state.feeSummary(), 'No fee details added');
    state.draft.fees = [{id: 1, label: 'Counter charge', type: 'fixed', amount_min: 155}, {id: 2, label: 'Other provider', type: 'fixed', amount_min: 999}];
    state.draft.channel.fee_ids = [1];
    assert.match(state.feeSummary(), /155/); assert.doesNotMatch(state.feeSummary(), /999/);
    state.draft.fees[0].type = 'free'; assert.match(state.feeSummary(), /Free/);
    state.addFee(); assert.match(state.feeSummary(), /Varies/);
});


test('preparation text stays in the provider draft and cancel never changes structured requirements', () => {
    const state = fixture();
    state.methods[0].groups = [{id: 21, title: 'Existing checklist', items: [{id: 33, type: 'government_id', government_id_id: 9}]}];
    state.methods[0].channel.preparation = 'Saved preparation';
    state.open(state.methods[0]);
    const before = JSON.stringify(state.methods[0].groups);
    state.draft.channel.preparation = 'Updated preparation';
    assert.equal(state.dirty, true);
    assert.equal(JSON.stringify(state.draft.groups), before);
    assert.equal(state.methods[0].channel.preparation, 'Saved preparation');
    state.cancel(); assert.equal(state.active, 1, 'declining discard retains the draft');
    window.confirm = () => true; state.cancel(); state.open(state.methods[0]);
    assert.equal(state.draft.channel.preparation, 'Saved preparation');
    assert.equal(JSON.stringify(state.draft.groups), before);
});

test('an empty Fees summary never creates a fee automatically', () => {
    const state = fixture(); state.open(state.methods[0]); state.$nextTick = fn => fn();
    let focused = false;
    state.editFees({querySelectorAll: () => [], querySelector: () => ({focus() {focused = true;}})});
    assert.equal(state.draft.fees.length, 0); assert.equal(focused, true);
    assert.equal(state.dirty, false);
});

test('clicking Fees focuses the existing editable amount without duplicating a fee', () => {
    const state = fixture(); state.open(state.methods[0]); state.$nextTick = fn => fn();
    state.draft.fees = [{id: 4, label: 'Processing', shared: false}]; state.draft.channel.fee_ids = [4];
    let focused = false;
    const amount = {type: 'number', disabled: false, focus() {focused = true;}};
    state.editFees({querySelectorAll: () => [{type:'number', disabled:false, matches: () => true, focus() {throw new Error('Must skip fields disabled by a shared-fee fieldset');}}, {type:'text', value:'Processing', disabled:false}, amount]});
    assert.equal(focused, true); assert.equal(state.draft.fees.length, 1);
});

test('clicking Fees keeps shared records intact and leaves existing guide steps untouched', () => {
    const state = fixture(); state.open(state.methods[0]); state.$nextTick = fn => fn();
    state.draft.fees = [{id: 4, label: 'Shared processing', shared: true}]; state.draft.channel.fee_ids = [4];
    state.draft.steps = [{id: 18, title: 'Keep saved guide', blocks: [{id: 22, content: {intro: 'Keep content'}}]}];
    const steps = JSON.stringify(state.draft.steps);
    state.editFees({querySelectorAll: () => [], querySelector: () => null});
    assert.equal(state.draft.fees.length, 1); assert.equal(state.draft.fees[0].id, 4); assert.equal(state.draft.fees[0].shared, true);
    assert.equal(JSON.stringify(state.draft.steps), steps);
});

test('the paragraph Application Guide uses draft warnings and cancellation without changing structured steps', () => {
    const state = fixture();
    state.methods[0].channel.application_guide_text = 'Saved paragraph';
    state.methods[0].steps = [{id: 19, title: 'Existing structured step', blocks: [{id: 20}]}];
    state.open(state.methods[0]);
    const steps = JSON.stringify(state.draft.steps);
    state.draft.channel.application_guide_text = 'Updated paragraph';
    assert.equal(state.dirty, true);
    state.cancel(); assert.equal(state.active, 1);
    assert.equal(state.methods[0].channel.application_guide_text, 'Saved paragraph');
    assert.equal(JSON.stringify(state.draft.steps), steps);
    window.confirm = () => true; state.cancel(); state.open(state.methods[0]);
    assert.equal(state.draft.channel.application_guide_text, 'Saved paragraph');
    assert.equal(JSON.stringify(state.draft.steps), steps);
});

test('Remove Fee removes saved and unsaved entries locally and Cancel restores the original links', () => {
    const state = fixture();
    state.methods[0].fees = [{id: 4, label: 'Shared charge', shared: true}]; state.methods[0].channel.fee_ids = [4];
    state.open(state.methods[0]); state.removeFee(0);
    assert.deepEqual(state.draft.fees, []); assert.deepEqual(state.draft.channel.fee_ids, []); assert.equal(state.dirty, true);
    state.$nextTick = fn => fn(); state.editFees({querySelectorAll: () => [], querySelector: () => null});
    assert.equal(state.draft.fees.length, 0);
    window.confirm = () => true; state.cancel(); state.open(state.methods[0]);
    assert.equal(state.draft.fees[0].id, 4);
    state.addFee(); state.removeFee(1); assert.equal(state.draft.fees.length, 1);
    assert.equal(state.methods[0].fees.length, 1);
});
