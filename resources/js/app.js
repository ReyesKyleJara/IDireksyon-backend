import './bootstrap';

import Alpine from 'alpinejs';
import governmentIdChecklists from './government-id-checklists';

import governmentIdFeeModal from './government-id-fees';
import governmentIdOffices from './government-id-offices';

import governmentIdGuide from './government-id-guide';
import guideRichText from './guide-rich-text';

window.Alpine = Alpine;
Alpine.data('governmentIdGuide', governmentIdGuide);
Alpine.data('guideRichText', guideRichText);

Alpine.data('governmentIdChecklists', governmentIdChecklists);

Alpine.data('governmentIdFeeModal', governmentIdFeeModal);
Alpine.data('governmentIdOffices', governmentIdOffices);

// One notification surface for redirects and in-page CMS actions.
Alpine.data('adminNotifications', (initial = []) => {
    const timers = new Map();
    let sequence = 0;
    return {
        messages: [],
        init() { initial.forEach(message => this.add(message)); },
        add(detail = {}) {
            if (!detail || typeof detail.message !== 'string' || !detail.message.trim()) return;
            const type = ['success', 'error', 'info', 'warning'].includes(detail.type) ? detail.type : 'info';
            const message = detail.message.trim();
            const existing = this.messages.find(item => item.message === message && item.type === type);
            if (existing) { this.resume(existing.id); return; }
            const item = { id: ++sequence, message, type, hovered: false, focused: false };
            this.messages.push(item);
            this.resume(item.id);
            this.$nextTick(() => {
                const stack = this.$refs.stack;
                if (this.messages.length && stack?.showPopover && !stack.matches(':popover-open')) stack.showPopover();
            });
        },
        pause(id) { clearTimeout(timers.get(id)); timers.delete(id); },
        resume(id) {
            this.pause(id);
            const item = this.messages.find(message => message.id === id);
            if (!item || item.hovered || item.focused || ['error', 'warning'].includes(item.type)) return;
            timers.set(id, setTimeout(() => this.dismiss(id), 5500));
        },
        dismiss(id) {
            this.pause(id);
            this.messages = this.messages.filter(item => item.id !== id);
            if (!this.messages.length && this.$refs.stack?.hidePopover && this.$refs.stack.matches(':popover-open')) this.$refs.stack.hidePopover();
        },
        destroy() { timers.forEach(timer => clearTimeout(timer)); timers.clear(); },
    };
});

Alpine.start();
