import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import editor from '../../resources/js/fee-editor.js';

test('simple fees remain simple and first option preserves amount without renaming parent', () => {
    const state = editor([{ id: 7, label: 'Processing', type: 'fixed', amount_min: '950.00' }]);
    const fee = state.fees[0];
    assert.equal(fee.type, 'fixed'); assert.deepEqual(fee.options, []);
    state.enableOptions(fee);
    assert.equal(fee.type, 'options'); assert.equal(fee.id, 7); assert.equal(fee.label, 'Processing');
    assert.equal(fee.options.length, 1); assert.equal(fee.options[0].amount_min, '950.00');
    state.addOption(fee); fee.options[0].label = 'Regular'; fee.options[1].label = 'Expedited';
    fee.options[1].amount_min = 1200;
    assert.equal(fee.options.length, 2); assert.equal(fee.options[0].amount_min, '950.00');
});

test('edit reload preserves IDs descriptions optionality and supports reorder and remove', () => {
    const input = [{ id: 5, label: 'Parent', type: 'options', is_optional: true, options: [
        { id: 10, label: 'A', type: 'free', description: 'First' },
        { id: 11, label: 'B', type: 'range', amount_min: 5, amount_max: 10, notes: 'Keep' },
    ] }];
    const state = editor(input); const fee = state.fees[0];
    fee.options[0].description = 'Edited'; state.moveOption(fee, 0, 1);
    assert.deepEqual(fee.options.map(o => o.id), [11, 10]);
    assert.equal(fee.options[1].description, 'Edited'); state.removeOption(fee, 0);
    assert.equal(fee.id, 5); assert.equal(fee.options[0].id, 10); assert.equal(fee.is_optional, true);
    assert.equal(input[0].options[0].description, 'First');
});

test('shared editor appends option forms inline and keeps nested form names', () => {
    const view = readFileSync(new URL('../../resources/views/admin/government_ids/partials/fee-items-field.blade.php', import.meta.url), 'utf8');
    assert.match(view, /@click="addOption\(fee\)">\+ Add another option/);
    assert.match(view, /x-for="\(option, optionIndex\) in fee.options"/);
    assert.match(view, /Choose one option/); assert.match(view, /\[options\]\[\$\{optionIndex\}\]\[description\]/);
    assert.doesNotMatch(view, /<dialog|<form/);
});
