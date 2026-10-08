export default function governmentIdFeeModal(initialFees) {
    const scalar = value => ['string', 'number'].includes(typeof value) ? value : '';
    let sequence = 0;
    const normalize = (row = {}) => ({
        key: ++sequence,
        label: scalar(row?.label),
        type: scalar(row?.type) || 'fixed',
        amount_min: scalar(row?.amount_min),
        amount_max: scalar(row?.amount_max),
        is_optional: [true, 1, '1'].includes(row?.is_optional),
        notes: scalar(row?.notes),
    });
    return {
        fees: Array.isArray(initialFees) ? initialFees.map(normalize) : [],
        draft: [], error: '', changed: false, opener: null, previousOverflow: null,
        normalize,
        amount(fee) {
            if (fee.type === 'free') return 'Free';
            if (fee.type === 'varies') return 'Varies';
            const money = value => value === '' || !Number.isFinite(Number(value)) ? '—'
                : new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value));
            return fee.type === 'range' ? money(fee.amount_min) + ' – ' + money(fee.amount_max) : money(fee.amount_min);
        },
        open(event) {
            this.draft = this.fees.map(fee => ({ ...fee }));
            this.error = '';
            this.opener = event.currentTarget;
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.$refs.dialog.showModal();
        },
        close() {
            this.$refs.dialog.close();
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
            this.previousOverflow = null;
            this.opener?.focus();
        },
        cancel() {
            this.draft = [];
            this.close();
        },
        apply() {
            this.error = '';
            for (const fee of this.draft) {
                if (!String(fee.label).trim()) { this.error = 'Enter a name for each fee.'; break; }
                if (!['fixed', 'range', 'free', 'varies'].includes(fee.type)) { this.error = 'Select a fee type.'; break; }
                const fields = fee.type === 'range' ? ['amount_min', 'amount_max'] : fee.type === 'fixed' ? ['amount_min'] : [];
                if (fields.some(field => fee[field] === '' || !Number.isFinite(Number(fee[field])) || Number(fee[field]) < 0)) {
                    this.error = 'Enter a valid, non-negative amount for each charge.'; break;
                }
                if (fee.type === 'range' && Number(fee.amount_max) < Number(fee.amount_min)) {
                    this.error = 'The maximum amount must be at least the minimum amount.'; break;
                }
            }
            if (this.error) { this.$nextTick(() => this.$refs.error.focus()); return; }
            this.fees = this.draft.map(fee => ({
                ...fee,
                amount_min: ['free', 'varies'].includes(fee.type) ? '' : fee.amount_min,
                amount_max: fee.type === 'range' ? fee.amount_max : '',
            }));
            this.changed = true;
            this.close();
            this.$dispatch('notify', { type: 'info', message: 'Fees updated in this form. Save the ID to keep them.' });
        },
        destroy() {
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
        },
    };
}
