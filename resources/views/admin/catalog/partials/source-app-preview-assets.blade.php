@once
<style>
    .ds-layout { display: grid; gap: 1.5rem; align-items: start; }
    @media (min-width: 1100px) { .ds-layout { grid-template-columns: minmax(0, 1fr) minmax(290px, 36%); } }
    .ds-preview { background: #f5f7fa; border-radius: 12px; padding: 12px; }
    .ds-card { display: grid; gap: 10px; background: white; border: 1px solid #e0e4e9; border-radius: 12px;
        padding: 14px; color: #191c20; font: 14px/1.45 system-ui, sans-serif; overflow-wrap: anywhere; }
    .ds-heading { display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .ds-verified { color: #18704a; }
    .ds-divider { border: 0; border-top: 1px solid #e0e4e9; margin: 0; }
    .ds-label { font-weight: 600; }
    .ds-entry, .ds-link { margin-left: 16px; }
    .ds-link { display: inline-block; color: #174b85; text-decoration: underline; padding-block: 3px; }
    .ds-secondary { color: #565c64; font-size: 12px; }
</style>
<script>
    function sourcePreview(sources, blank, approvedVerification, methods) {
        return {
            sources, blank, draftVerification: null,
            get visibleSources() {
                return this.sources.map((source, index) => ({
                    index, title: (source.title ?? '').trim(), url: (source.url ?? '').trim(),
                })).filter(source => source.title && this.isSafe(source.url));
            },
            get shownVerification() {
                const draft = this.draftVerification;
                const office = draft?.office?.trim();
                const method = methods[draft?.method];
                return office && method && this.formatDate(draft.date)
                    ? { office, method, date: draft.date } : approvedVerification;
            },
            formatDate(value) {
                if (!/^\d{4}-\d{2}-\d{2}$/.test(value ?? '')) return '';
                const date = new Date(value + 'T00:00:00Z');
                if (Number.isNaN(date.valueOf())) return '';
                return new Intl.DateTimeFormat('en-US', { dateStyle: 'long', timeZone: 'UTC' }).format(date);
            },
            isSafe(url) {
                try {
                    const uri = new URL(url);
                    return ['https:', 'http:'].includes(uri.protocol) && !!uri.hostname && !uri.username && !uri.password;
                } catch { return false; }
            },
        };
    }
</script>
@endonce
