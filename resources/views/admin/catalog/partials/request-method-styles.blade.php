@once
<style>
    .request-methods { padding: 22px; border: 1px solid #e2e5e9; border-radius: 18px; background: #fff; }
    .request-methods-title { margin: 0 0 22px; font-size: 20px; font-weight: 600; letter-spacing: -.02em; color: #28313e; }
    .request-method-category + .request-method-category { margin-top: 22px; }
    .request-method-category-heading { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; font-size: 15px; font-weight: 600; color: #374151; }
    .request-method-icon { width: 18px; height: 18px; flex-shrink: 0; color: #858b93; }
    .request-method-count { margin-left: auto; font-size: 12px; font-weight: 400; color: #8a919b; }
    .request-method-card { overflow: hidden; border: 1px solid #e2e5e9; border-radius: 14px; background: #fff; }
    .request-method-card + .request-method-card { margin-top: 10px; }
    .request-method-toggle { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; padding: 17px 18px; text-align: left; color: #303844; font-size: 15px; font-weight: 600; }
    .request-method-toggle:hover { background: #fafbfc; }
    .request-method-toggle:focus-visible { outline: 2px solid #012877; outline-offset: -3px; border-radius: 14px; }
    .request-method-chevron { width: 17px; height: 17px; color: #8a919b; flex-shrink: 0; transition: transform .15s; }
    .request-method-toggle[aria-expanded="true"] .request-method-chevron { transform: rotate(180deg); }
    .request-method-body { margin: 0 18px; padding: 18px 0; border-top: 1px solid #eceef1; }
    .request-method-legacy { margin-top: 20px; border-top: 1px solid #eceef1; padding-top: 18px; }
    .request-method-legacy > p { margin-bottom: 10px; color: #737d8a; font-size: 13px; line-height: 1.6; }
    .request-method-legacy .request-method-toggle { font-size: 14px; font-weight: 500; }
    .request-method-add { display: inline-flex; align-items: center; gap: 6px; margin-top: 18px; padding: 6px 0; font-size: 14px; font-weight: 500; color: #012877; border-radius: 4px; }
    .request-method-add:hover { text-decoration: underline; }
    .request-method-new { margin-top: 18px; padding: 18px; border: 1px solid #e2e5e9; border-radius: 14px; }
    .request-method-form { font-size: 14px; }
    .request-method-form > fieldset { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
    .request-method-form h4, .request-method-form legend { font-size: 14px; font-weight: 500; color: #697381; }
    .request-method-form label { color: #687281; font-size: 13px; font-weight: 400; }
    .request-method-form .admin-input { border: 1px solid #d8dde3; border-radius: 12px; background: #fff; padding: 10px 12px; font-size: 14px; line-height: 1.5; box-shadow: none; }
    .request-method-form .admin-input:focus { border-color: #687fa9; outline: none; box-shadow: 0 0 0 2px rgb(1 40 119 / 8%); }
    .request-method-form textarea.admin-input { resize: vertical; min-height: 92px; }
    .request-method-form .request-method-processing textarea.admin-input { min-height: 43px; }
    .request-method-form .admin-input::placeholder { color: #9aa2ac; }
    .request-method-form .request-method-entry { padding: 0; }
    .request-method-entry + .request-method-entry { padding-top: 16px; border-top: 1px solid #eceef1; }
    .request-method-columns { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
    .request-method-columns > section { min-width: 0; }
    .request-method-fee-summary { width: 100%; text-align: left; cursor: pointer; padding: 10px 12px; border: 1px solid #d8dde3; border-radius: 12px; min-height: 43px; font-size: 14px; line-height: 1.5; color: #475569; }
    .request-method-fee-summary:hover { border-color: #a8b8d1; background: #fafbfc; }
    .request-method-fee-summary:focus-visible { outline: 2px solid #012877; outline-offset: 2px; }
    .request-method-form .request-method-link { color: #315582; font-size: 12px; font-weight: 500; }
    .request-method-choices { display: flex; flex-wrap: wrap; gap: 8px; }
    .request-method-choices label { display: inline-flex; align-items: center; gap: 7px; padding: 7px 11px; border: 1px solid #e2e5e9; border-radius: 10px; cursor: pointer; }
    .request-method-choices label:has(input:checked) { border-color: #a8b8d1; background: #f5f7fb; color: #012877; }
    .request-method-choices input { width: 14px; height: 14px; border-color: #bac3cf; color: #012877; }
    .request-method-delete { grid-column: 3; grid-row: 1; justify-self: end; color: #9c5555; font-size: 12px; }
    .request-method-delete:hover { text-decoration: underline; }
    .request-method-fulfillment { display: flex; flex-wrap: wrap; gap: 8px; }
    .request-method-fulfillment label { display: inline-flex; align-items: center; gap: 7px; padding: 8px 11px; border: 1px solid #e2e5e9; border-radius: 10px; color: #5d6673; cursor: pointer; }
    .request-method-fulfillment label:has(input:checked) { border-color: #a8b8d1; background: #f5f7fb; color: #012877; }
    .request-method-fulfillment input { width: 14px; height: 14px; border-radius: 4px; border-color: #bac3cf; color: #012877; }
    .request-method-actions { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 12px; padding-top: 2px; }
    .request-method-actions > .admin-primary { grid-column: 1 / -1; grid-row: 2; width: 100%; min-height: 42px; border-radius: 12px; }
    .request-method-actions > .admin-secondary { padding: 4px 0; border: 0; box-shadow: none; font-size: 13px; background: transparent; color: #64748b; }
    .request-method-form [role="alert"] { margin-bottom: 16px; }
    @media (max-width: 640px) {
        .request-methods { padding: 16px; border-radius: 14px; }
        .request-method-toggle { padding: 15px; }
        .request-method-body { margin: 0 15px; padding: 16px 0; }
        .request-method-columns { grid-template-columns: minmax(0, 1fr); gap: 20px; }
        .request-method-count { display: none; }
    }
    @media (prefers-reduced-motion: reduce) { .request-method-chevron { transition: none; } }
</style>
@endonce
