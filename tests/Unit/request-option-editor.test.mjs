import test from 'node:test';
import assert from 'node:assert/strict';
import editor, { suggestOptionName, formSnapshot, catalogStepChanges } from '../../resources/js/request-option-editor.js';

test('suggests readable request option names for supported transactions and custom methods', () => {
    assert.equal(suggestOptionName('copy', 'outlet'), 'Certified Copy — In Person');
    assert.equal(suggestOptionName('new', 'outlet'), 'New Application — In Person');
    assert.equal(suggestOptionName('renewal', 'online'), 'Renewal — Online');
    assert.equal(suggestOptionName('custom', 'hybrid', '  Late filing  '), 'Late filing — Other / Hybrid');
    assert.equal(suggestOptionName('', 'online'), '');
});
test('updates suggested names while retaining an explicitly entered display name', () => {
    const state = editor({transaction: 'copy'});
    state.method = 'online'; state.changeMethod();
    assert.equal(state.name, 'Certified Copy — Online');
    state.name = 'Official portal service'; state.nameEdited = true;
    state.method = 'outlet'; state.changeMethod();
    assert.equal(state.name, 'Official portal service');
    const saved = editor({transaction:'renewal',channel:'online',name:'Saved custom name'});
    saved.suggest(); assert.equal(saved.name,'Saved custom name');
});
test('opening a service-partner or hybrid option preserves its existing channel code', () => {
    for (const channel of ['partner', 'hybrid']) {
        const state = editor({transaction:'copy',channel});
        assert.equal(state.method,'other');
        state.changeMethod(); assert.equal(state.channel,channel);
    }
});

// The editor compares successful form controls, matching browser FormData semantics.
function attach(state) {
    const previousFormData = globalThis.FormData;
    globalThis.FormData = class { constructor(form) { return form.entries[Symbol.iterator](); } };
    const listeners = new Map();
    globalThis.window = { addEventListener(name, fn) { listeners.set(name, fn); }, removeEventListener(name) { listeners.delete(name); }, confirm: () => false };
    state.$el = { entries:[['_token','token'],['_catalog_revision','r1'],['request_channel[label]','Saved name']], addEventListener(name,fn){listeners.set('form:'+name,fn);},removeEventListener(name){listeners.delete('form:'+name);} };
    state.$nextTick = fn => fn();
    state.init();
    return {listeners, cleanup(){state.destroy();globalThis.FormData=previousFormData;}};
}
test('warns only for unsaved edits, supports cancellation, and cleans up navigation listeners', () => {
    const state = editor({name:'Saved name'}); const {listeners,cleanup}=attach(state);
    assert.equal(state.dirty,false);
    state.$el.entries[1][1]='r2'; assert.equal(state.dirty,false,'revision refresh is not a content change');
    state.$el.entries[2][1]='Changed'; state.markChanged(); assert.equal(state.changed,true);
    let blocked=false;
    listeners.get('beforeunload')({preventDefault(){blocked=true;}}); assert.equal(blocked,true);
    blocked=false; state.cancel({preventDefault(){blocked=true;}}); assert.equal(blocked,true);
    state.$el.entries[2][1]='Saved name'; state.markChanged(); assert.equal(state.changed,false);
    assert.ok(formSnapshot(state.$el).includes('Saved name'));
    cleanup(); assert.equal(listeners.size,0);
});
test('fees and offices retain unsaved warnings when another editor prevents submission', async () => {
    const state = catalogStepChanges(); const {listeners,cleanup}=attach(state);
    state.$el.entries[2][1]='Changed';
    const event={defaultPrevented:true}; listeners.get('form:submit')(event);
    await Promise.resolve(); assert.equal(state.submitting,false);
    listeners.get('form:submit')({defaultPrevented:false}); await Promise.resolve();
    assert.equal(state.submitting,true);
    cleanup(); assert.equal(listeners.size,0);
});

test('Government ID acquisition suggests only the method while Documents retain transaction names', () => {
    const id = editor({implicitFirstAcquisition:true, transaction:'new'});
    id.method='outlet'; id.changeMethod(); assert.equal(id.name,'In Person');
    id.method='online'; id.changeMethod(); assert.equal(id.name,'Online');
    const document = editor({transaction:'copy'});
    document.method='online'; document.changeMethod(); assert.equal(document.name,'Certified Copy — Online');
    const legacy = editor({implicitFirstAcquisition:true, transaction:'renewal', name:'Saved renewal research'});
    legacy.suggest(); assert.equal(legacy.name,'Saved renewal research'); assert.equal(legacy.transaction,'renewal');
});
