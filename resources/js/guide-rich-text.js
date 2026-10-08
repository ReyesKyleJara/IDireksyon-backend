import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

export const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
export const textDocument = text => ({ type: 'doc', content: [{ type: 'paragraph', ...(text ? { content: [{ type: 'text', text }] } : {}) }] });

export default function guideRichText(initial) {
    // Keep the Editor outside Alpine's reactive proxy (ProseMirror owns its state).
    let editor;
    return {
        revision: 0, linkOpen: false, linkUrl: '', linkError: '',
        init() {
            this.$nextTick(() => {
                editor = new Editor({
                    element: this.$refs.editor,
                    extensions: [StarterKit.configure({
                        heading: { levels: [2, 3] },
                        strike: false, code: false, codeBlock: false, blockquote: false, horizontalRule: false,
                        link: { openOnClick: false, autolink: false, protocols: ['http', 'https'], defaultProtocol: 'https' },
                    })],
                    content: typeof initial === 'string' ? textDocument(initial) : (initial || emptyDocument()),
                    editorProps: { attributes: { class: 'guide-content min-h-24 p-3 outline-none', role: 'textbox', 'aria-label': 'Formatted content', 'aria-multiline': 'true' } },
                    onUpdate: ({ editor: current }) => { this.$dispatch('guide-richtext', { value: current.getJSON() }); this.revision++; },
                    onSelectionUpdate: () => this.revision++,
                });
            });
        },
        active(name, attributes = {}) { void this.revision; return editor?.isActive(name, attributes) || false; },
        format(command, attributes) { editor?.chain().focus()[command](attributes).run(); },
        openLink() { this.linkUrl = editor?.getAttributes('link').href || ''; this.linkError = ''; this.linkOpen = true; },
        applyLink() {
            let url;
            try { url = new URL(this.linkUrl.trim()); } catch { this.linkError = 'Enter a complete https:// or http:// address.'; return; }
            if (!['http:', 'https:'].includes(url.protocol)) { this.linkError = 'Use an https:// or http:// link.'; return; }
            editor?.chain().focus().extendMarkRange('link').setLink({ href: url.href }).run();
            this.linkOpen = false;
        },
        removeLink() { editor?.chain().focus().extendMarkRange('link').unsetLink().run(); this.linkOpen = false; },
        destroy() { editor?.destroy(); },
    };
}
