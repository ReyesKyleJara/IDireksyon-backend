import test from 'node:test';
import assert from 'node:assert/strict';
import editor from '../../resources/js/catalog-prerequisites.js';

test('loads independent drafts with stable identities and preserves links conditions and order', () => {
    const input = [{id: 1, type: 'document', document_id: 7, condition: 'Under 18'}, {id: 2, type: 'custom', custom_name: 'Training'}];
    const state = editor(input);
    state.move(0, 1);
    assert.deepEqual(state.rows.map(row => row.id), [2, 1]);
    assert.equal(state.rows[1].document_id, 7);
    assert.equal(state.rows[1].condition, 'Under 18');
    state.rows[0].custom_name = 'Changed';
    assert.equal(input[1].custom_name, 'Training');
    state.move(0, -1); state.move(1, 1);
    assert.deepEqual(state.rows.map(row => row.id), [2, 1]);
    state.remove(0);
    assert.equal(state.rows[0].id, 1);
});

test('type changes clear conflicting sources and new rows never invent catalog links', () => {
    const state = editor();
    state.add();
    const firstKey = state.rows[0].key;
    assert.equal(state.rows[0].type, 'custom');
    state.rows[0].custom_name = 'Training';
    state.rows[0].type = 'document';
    state.changeType(state.rows[0]);
    assert.equal(state.rows[0].custom_name, '');
    assert.equal(state.rows[0].document_id, null);
    assert.equal(state.rows[0].government_id_id, null);
    state.remove(0); state.add();
    assert.notEqual(state.rows[0].key, firstKey);
});
