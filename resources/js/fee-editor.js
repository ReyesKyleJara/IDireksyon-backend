let sequence = 0;
const normalize = (fee = {}) => ({
    key: ++sequence, id: fee.id ?? null, label: fee.label ?? '', type: fee.type ?? 'fixed',
    amount_min: fee.amount_min ?? '', amount_max: fee.amount_max ?? '', currency: fee.currency ?? 'PHP',
    description: fee.description ?? '', notes: fee.notes ?? '', is_optional: fee.is_optional === true || fee.is_optional === '1' || fee.is_optional === 1,
    options: (fee.options || []).map(option => normalize(option)),
});
export default function feeEditor(initialFees = []) {
    return {
        fees: initialFees.map(normalize),
        addFee() { this.fees.push(normalize()); },
        removeFee(index) { this.fees.splice(index, 1); },
        enableOptions(fee) {
            if (fee.type === 'options') return;
            fee.options = [normalize({ type: fee.type, amount_min: fee.amount_min, amount_max: fee.amount_max, currency: fee.currency })];
            fee.type = 'options';
        },
        addOption(fee) { if (fee.options.length < 50) fee.options.push(normalize()); },
        removeOption(fee, index) { fee.options.splice(index, 1); },
        moveOption(fee, index, offset) {
            const target = index + offset;
            if (target < 0 || target >= fee.options.length) return;
            fee.options.splice(target, 0, fee.options.splice(index, 1)[0]);
        },
    };
}
