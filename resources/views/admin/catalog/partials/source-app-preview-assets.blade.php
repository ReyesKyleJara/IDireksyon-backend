{{--
    Styles and display rules for the resident app preview (source-app-preview.blade.php).
    The rules copy CatalogSourceCard in the Flutter app so the preview matches what residents see.
--}}
@once
<style>
    .ds-source-row { display: grid; gap: 1rem; }
    @media (min-width: 1100px) { .ds-source-row { grid-template-columns: minmax(0, 1fr) 340px; align-items: start; } }
    .ds-preview { background: #F3F8FF; border-radius: 12px; padding: 12px; }
    .ds-preview-caption { font-size: 12px; font-weight: 700; color: #103765; margin-bottom: 8px; }
    .ds-preview-caption span { font-weight: 400; color: #64748b; }
    .ds-preview-note { font-size: 12px; color: #92400e; margin-top: 8px; }
    .ds-card { font-family: 'Google Sans', 'Product Sans', Roboto, system-ui, sans-serif; background: #fff;
        border: 1px solid rgba(194, 199, 207, .4); border-radius: 14px; padding: 16px; color: #191C20; max-width: 360px; }
    .ds-card-head { display: flex; gap: 10px; align-items: flex-start; }
    .ds-icon { width: 20px; height: 20px; margin-top: 1px; color: #174B85; flex: none; }
    .ds-record { font-size: 15px; line-height: 1.35; font-weight: 700; }
    .ds-agency { font-size: 13px; color: #42474E; margin-top: 2px; }
    .ds-divider { border: 0; border-top: 1px solid rgba(194, 199, 207, .5); margin: 10px 0; }
    .ds-label { font-size: 12px; letter-spacing: .2px; font-weight: 700; color: #174B85; }
    .ds-title { font-size: 14px; line-height: 1.35; font-weight: 600; margin-top: 4px; overflow-wrap: anywhere; }
    .ds-section { font-size: 12px; color: #42474E; margin-top: 2px; }
    .ds-link { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 8px 4px;
        font-size: 14px; font-weight: 600; color: #174B85; text-decoration: none; }
    .ds-link:hover { text-decoration: underline; }
    .ds-link svg { width: 16px; height: 16px; }
    .ds-scope { font-size: 13px; line-height: 1.45; color: #42474E; overflow-wrap: anywhere; }
    .ds-checked { font-size: 12px; color: #42474E; margin-top: 6px; }
    .ds-empty { color: #94a3b8; font-style: italic; }
</style>
<script>
    // Same rules as CatalogSourceCard (Flutter). Display only: the saved values are never changed.
    function sourcePreview(sources, blank, record) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const text = (value) => (value ?? '').toString();
        return {
            sources, blank,
            recordName: text(record.name).trim(),
            agencyName: '',
            init() { this.syncRecord(); },
            // The card header uses the record name and issuing agency typed above, saved or not.
            syncRecord() {
                const name = document.getElementById('name');
                if (name) this.recordName = name.value.trim();
                const select = document.getElementById('agency_id');
                let agency = '';
                if (select && select.value) {
                    agency = record.agencies[select.value]
                        ?? text(select.options[select.selectedIndex]?.text).replace(/\s+\([^()]*\)$/, '');
                }
                const issuedBy = document.getElementById('issued_by');
                this.agencyName = (agency || (issuedBy ? issuedBy.value : record.issuedBy) || '').trim();
            },
            displayTitle(title) {
                const shown = text(title).replace(/^\s*public\s+reference\s*[:\-–—]\s*/i, '').trim();
                return shown || text(title).trim();
            },
            linkLabel(title) {
                const parts = this.displayTitle(title).split(/\s+[–—-]\s+/);
                const agency = parts.length > 1 ? parts[0].trim() : '';
                if (!agency || agency.length > 24 || agency.split(/\s+/).length > 3) return 'View source webpage';
                return `View ${agency} webpage`;
            },
            checkedLabel(value) {
                const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text(value).trim());
                const month = match ? Number(match[2]) : 0;
                if (!match || month < 1 || month > 12) return 'Checked date not recorded';
                return `Checked on ${Number(match[3])} ${months[month - 1]} ${match[1]}`;
            },
            showsSection(title, section) {
                const plain = (value) => text(value).toLowerCase().replace(/[^a-z0-9]/g, '');
                const wanted = plain(section);
                return wanted !== '' && !plain(this.displayTitle(title)).includes(wanted);
            },
            isSafe(url) {
                try {
                    const uri = new URL(text(url).trim());
                    return ['https:', 'http:'].includes(uri.protocol) && uri.hostname !== '' && !uri.username && !uri.password;
                } catch { return false; }
            },
        };
    }
</script>
@endonce
