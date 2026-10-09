<style>
    .id-editor { width: 100%; min-width: 0; }
    .id-editor .editor-heading { margin-bottom: 24px; }
    .id-editor .editor-heading h1 { margin-top: 16px; font-size: 26px; font-weight: 650; line-height: 1.3; letter-spacing: -.025em; overflow-wrap: anywhere; }
    .id-editor .editor-form { padding: 32px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
    .id-editor .editor-form > .admin-panel { margin: 0; padding: 28px 0; border: 0; border-bottom: 1px solid #e2e8f0; border-radius: 0; }
    .id-editor .editor-form > .admin-panel:first-of-type { padding-top: 0; }
    .id-editor .editor-form > .admin-panel > div:first-child { margin-bottom: 20px; }
    .id-editor .editor-form > section > div > h2,
    .id-editor .editor-form > section > div > div > h2,
    .id-editor .editor-form > section > fieldset > legend,
    .id-editor .editor-timing h2 { font-size: 16px; font-weight: 600; line-height: 1.5; }
    .id-editor .editor-timing { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px; padding: 28px 0; border-bottom: 1px solid #e2e8f0; }
    .id-editor .editor-timing > section { min-width: 0; padding: 0; border: 0; border-radius: 0; }
    .id-editor .editor-timing > section + section { padding-left: 32px; border-left: 1px solid #e2e8f0; }
    .id-editor .editor-actions { position: sticky; bottom: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 20px; padding: 16px 0; background: #fff; border-top: 1px solid #e2e8f0; }
    .id-editor .editor-actions p { margin: 0; max-width: 440px; font-size: 12px; line-height: 1.5; color: #64748b; }
    .id-editor .editor-buttons { display: flex; gap: 12px; margin-left: auto; }
    @media (min-width: 900px) {
        .id-editor .editor-description, .id-editor .editor-purpose { grid-column: span 1 / span 1; }
    }
    @media (max-width: 1023px) {
        .id-editor .editor-timing { grid-template-columns: minmax(0, 1fr); gap: 24px; }
        .id-editor .editor-timing > section + section { padding-left: 0; border-left: 0; padding-top: 24px; border-top: 1px solid #e2e8f0; }
    }
    @media (max-width: 640px) {
        .id-editor .editor-form { padding: 20px; }
        .id-editor .editor-heading h1 { font-size: 23px; }
    }
</style>
