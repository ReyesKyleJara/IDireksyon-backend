export default function governmentIdOffices(options, initialLinks) {
    return {
        options, links: initialLinks, draft: [], selected: '', error: '', changed: false,
        opener: null, previousOverflow: null, creatingOffice: false, creationUrl: '', messageHandler: null,
        init() {
            this.messageHandler = event => {
                if (event.origin !== window.location.origin || !this.creatingOffice
                    || event.source !== this.$refs.createFrame?.contentWindow
                    || event.data?.type !== 'idireksyon:office-created') return;
                const office = event.data.office;
                if (!office || !/^\d+$/.test(String(office.id)) || typeof office.name !== 'string') return;
                if (!this.options.some(option => String(option.id) === String(office.id))) this.options.push(office);
                this.selected = String(office.id);
                this.addOffice();
                this.creatingOffice = false;
                this.creationUrl = '';
                this.$dispatch('notify', { type: 'success', message: 'Office created. Apply your office selection, then save the ID to keep the link.' });
            };
            window.addEventListener('message', this.messageHandler);
        },
        createOffice(url) {
            this.creationUrl = url;
            this.creatingOffice = true;
        },
        openNewOffice(event, url) {
            this.open(event);
            this.createOffice(url);
        },
        backToLinks() {
            if (!window.confirm('Close the office form? Unsaved office details will be lost.')) return;
            this.creatingOffice = false;
            this.creationUrl = '';
        },
        officeFor(id) { return this.options.find(office => String(office.id) === String(id)); },
        open(event) {
            this.draft = this.links.map(link => ({ ...link }));
            this.selected = '';
            this.error = '';
            this.opener = event.currentTarget;
            this.$refs.dialog.showModal();
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        },
        addOffice() {
            if (!this.selected || this.draft.length >= 100 || this.draft.some(link => link.office_id === this.selected)) return;
            this.draft.push({
                office_id: this.selected, new_application_status: 'unknown',
                renewal_status: 'unknown', replacement_status: 'unknown', service_notes: '',
            });
            this.selected = '';
        },
        services(link) {
            return [['new_application_status', 'New applications'], ['renewal_status', 'Renewals'], ['replacement_status', 'Replacements']]
                .filter(([field]) => ['available', 'unavailable'].includes(link[field]))
                .map(([field, label]) => label + ': ' + (link[field] === 'available' ? 'Available' : 'Not available')).join(' · ');
        },
        close() {
            this.$refs.dialog.close();
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
            this.previousOverflow = null;
            this.opener?.focus();
        },
        cancel() {
            if (this.creatingOffice && !window.confirm('Close the office form? Unsaved office details will be lost.')) return;
            this.creatingOffice = false;
            this.creationUrl = '';
            this.draft = []; this.close();
        },
        apply() {
            this.error = '';
            const ids = this.draft.map(link => link.office_id);
            if (new Set(ids).size !== ids.length || this.draft.some(link => !this.officeFor(link.office_id))) {
                this.error = 'Choose existing offices without adding the same branch twice.';
            } else if (this.draft.some(link => ['new_application_status', 'renewal_status', 'replacement_status']
                .some(field => !['unknown', 'available', 'unavailable'].includes(link[field])))) {
                this.error = 'Select a service status for each branch.';
            }
            if (this.error) { this.$nextTick(() => this.$refs.error.focus()); return; }
            this.links = this.draft.map(link => ({ ...link }));
            this.changed = true;
            this.close();
            this.$dispatch('notify', { type: 'info', message: 'Office selection updated. Save the ID to keep it.' });
        },
        destroy() {
            window.removeEventListener('message', this.messageHandler);
            if (this.previousOverflow !== null) document.body.style.overflow = this.previousOverflow;
        },
    };
}
