export default function catalogPrerequisites(initial = []) {
    let sequence = 0;
    return {
        rows: initial.map(row => ({ ...row, key: ++sequence })),
        add() {
            this.rows.push({ key: ++sequence, id: null, type: 'custom', government_id_id: null, document_id: null, custom_name: '', condition: '' });
        },
        changeType(row) {
            row.government_id_id = null;
            row.document_id = null;
            row.custom_name = '';
        },
        remove(index) { this.rows.splice(index, 1); },
        move(index, direction) {
            const next = index + direction;
            if (next < 0 || next >= this.rows.length) return;
            [this.rows[index], this.rows[next]] = [this.rows[next], this.rows[index]];
        },
    };
}
