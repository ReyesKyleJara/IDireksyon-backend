import test from 'node:test';
import assert from 'node:assert/strict';
import requirements from '../../resources/js/government-id-checklists.js';
import guide from '../../resources/js/government-id-guide.js';

globalThis.window = { addEventListener() {}, removeEventListener() {} };
globalThis.document = { activeElement: null };
function attach(state, dispatch = () => {}) {
    state.$el = { closest: () => ({ addEventListener() {}, removeEventListener() {} }) };
    state.$refs = { pendingPayload: {}, guidePayload: {} };
    state.$nextTick = fn => fn(); state.$dispatch = dispatch;
    state.init(); return state;
}
test('embedded checklist serializes its active draft without a separate checklist save', () => {
    const events = [];
    const state = attach(requirements({ embedded: true, deferred: true, checklists: [{client_key:'special-case',application_type:'new',applicant_type:'all',groups:[]}], options: [], applications: {new:'New'}, applicants: {all:'All'}, formats:{},conditions:{},qualifications:{} }), event => events.push(event));
    assert.deepEqual(events, [], 'assistance must not create an extra normal guide scenario');
    state.draft = {application_type:'new',applicant_type:'all',groups:[]};
    let blocked = false;
    state.submitHandler({preventDefault(){blocked=true;},defaultPrevented:false});
    assert.equal(blocked,false);
    assert.equal(JSON.parse(state.$refs.pendingPayload.value)[0].client_key,'special-case');
    state.editing = {};
    state.submitHandler({preventDefault(){blocked=true;},defaultPrevented:true});
    assert.equal(blocked,true);
});
test('case guide serializes unchanged steps so editing a description cannot erase them', () => {
    const state = attach(guide({serializeAll:true,scenarios:[{application_type:'new',applicant_type:'all',steps:[{title:'Saved fixture step',type:'general',blocks:[]}]}]}));
    assert.equal(JSON.parse(state.payload)[0].steps[0].title,'Saved fixture step');
});
test('validation recovery replaces the single standalone draft instead of appending another scenario', () => {
    const previous = [{application_type:'new',applicant_type:'all',steps:[{title:'Retained draft after validation',type:'general',blocks:[]}]}];
    const state = attach(guide({singleScenario:true,serializeAll:true,scenarios:[{application_type:'new',applicant_type:'all',steps:[]}],oldPayload:JSON.stringify(previous)}));
    assert.equal(state.scenarios.length,1);
    assert.equal(state.current.steps[0].title,'Retained draft after validation');
});
