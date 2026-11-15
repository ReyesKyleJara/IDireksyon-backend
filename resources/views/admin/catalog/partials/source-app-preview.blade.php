{{--
    Live preview of one online source as a Public Reference Card in the resident app
    (Flutter: lib/features/ids/catalog_source_card.dart). Used inside the Online Sources
    x-for loop, so `source` is the row being edited. It only displays; nothing here is saved.
--}}
<div class="ds-preview" data-app-preview aria-label="Resident app preview">
    <p class="ds-preview-caption">Resident app preview <span>· updates as you type</span></p>
    <div class="ds-card">
        <div class="ds-card-head">
            <svg class="ds-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12 6.5C10.3 5.2 7.9 4.6 4 4.6v13.2c3.9 0 6.3.6 8 1.9m0-13.2c1.7-1.3 4.1-1.9 8-1.9v13.2c-3.9 0-6.3.6-8 1.9m0-13.2v13.2"/></svg>
            <div>
                <p class="ds-record" :class="!recordName && 'ds-empty'" x-text="recordName || 'Name of this record'"></p>
                <p class="ds-agency" x-show="agencyName" x-text="agencyName"></p>
            </div>
        </div>
        <hr class="ds-divider">
        <p class="ds-label">Public reference</p>
        <p class="ds-title" :class="!displayTitle(source.title) && 'ds-empty'" x-text="displayTitle(source.title) || 'Website or agency name'"></p>
        <p class="ds-section" x-show="showsSection(source.title, source.section)" x-text="(source.section || '').trim()"></p>
        <a class="ds-link" x-show="isSafe(source.url)" :href="isSafe(source.url) ? source.url.trim() : null" target="_blank" rel="noopener noreferrer">
            <span x-text="linkLabel(source.title)"></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
        </a>
        <p class="ds-scope" x-show="(source.scope || '').trim()" x-text="(source.scope || '').trim()"></p>
        <p class="ds-checked" x-text="checkedLabel(source.checked_on)"></p>
    </div>
    <p class="ds-preview-note" x-show="!isSafe(source.url)">No link button yet: residents only see it after you enter a full link starting with https://.</p>
    <p class="ds-preview-note" x-show="!source.checked_on">Add the date you checked the website, or residents see “Checked date not recorded”.</p>
</div>
