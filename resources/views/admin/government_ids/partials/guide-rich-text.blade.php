<div class="guide-richtext overflow-hidden rounded-lg border border-slate-300 bg-white" x-data="guideRichText({{ $value }})" @guide-richtext.stop="{{ $value }} = $event.detail.value">
    <div class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 p-2" role="toolbar" aria-label="Text formatting">
        @foreach(['toggleBold' => ['Bold', 'bold'], 'toggleItalic' => ['Italic', 'italic'], 'toggleUnderline' => ['Underline', 'underline'], 'toggleBulletList' => ['Bullets', 'bulletList'], 'toggleOrderedList' => ['Numbering', 'orderedList']] as $command => [$label, $mark])
            <button type="button" class="guide-tool" :aria-pressed="active('{{ $mark }}')" @click="format('{{ $command }}')">{{ $label }}</button>
        @endforeach
        <button type="button" class="guide-tool" :aria-pressed="active('heading', { level: 2 })" @click="format('toggleHeading', { level: 2 })">Heading</button>
        <button type="button" class="guide-tool" :aria-pressed="active('heading', { level: 3 })" @click="format('toggleHeading', { level: 3 })">Subheading</button>
        <button type="button" class="guide-tool" :aria-pressed="active('link')" @click="openLink()">Link</button>
    </div>
    <div x-show="linkOpen" x-cloak class="space-y-2 border-b border-slate-200 p-3">
        <label class="block text-sm">Link address <input type="url" class="admin-input mt-1" x-model="linkUrl" placeholder="https://..." @keydown.enter.prevent="applyLink()"></label>
        <p x-show="linkError" x-text="linkError" class="text-sm text-red-600" role="alert"></p>
        <div class="flex gap-2"><button type="button" class="guide-tool" @click="applyLink()">Apply link</button><button type="button" class="guide-tool" @click="removeLink()">Remove link</button><button type="button" class="guide-tool" @click="linkOpen = false">Cancel</button></div>
    </div>
    <div x-ref="editor"></div>
</div>
