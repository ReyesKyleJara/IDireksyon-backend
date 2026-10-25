<aside class="min-w-0 2xl:sticky 2xl:top-6" aria-labelledby="guide-app-preview-heading">
    <div class="mb-3">
        <h2 id="guide-app-preview-heading" class="text-base font-semibold text-slate-900">App preview</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">Intended resident presentation. The mobile app does not read this guide yet.</p>
    </div>
    <div class="mx-auto max-w-sm overflow-hidden rounded-3xl border border-slate-200 bg-[#F7F6F2] shadow-sm">
        <header class="border-b border-slate-200 bg-white px-5 py-5">
            <p class="text-xs font-medium text-slate-500">{{ $governmentId->name }}</p>
            <h3 class="mt-1 text-lg font-semibold text-[#012877]">Application Guide</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">Follow the steps below to prepare your application.</p>
        </header>
        <div class="space-y-4 p-4" aria-label="Resident guide preview">
            <p x-show="!previewSteps.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm leading-6 text-slate-500">Your application steps will appear here as you add them.</p>
            <template x-for="(previewStep, previewIndex) in previewSteps" :key="previewStep.key">
                <article class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-[#012877]" x-text="'Step ' + (previewIndex + 1)"></p>
                        <span x-show="previewStepType(previewStep)" class="rounded-full bg-blue-50 px-2 py-1 text-[11px] text-blue-800" x-text="previewStepType(previewStep)"></span>
                    </div>
                    <h4 class="break-words text-sm font-semibold leading-6 text-slate-900" x-text="previewStep.title || 'Step title'"></h4>
                    <p x-show="previewStep.short_description" class="whitespace-pre-line break-words text-sm leading-6 text-slate-600" x-text="previewContent(previewStep.short_description)"></p>
                    <template x-for="previewBlock in previewBlocks(previewStep)" :key="previewBlock.key">
                        <div class="min-w-0 text-sm leading-6 text-slate-600">
                            <template x-if="previewBlock.type === 'official_link'">
                                <div>
                                    <a x-show="previewUrl(previewBlock.content.url)" :href="previewUrl(previewBlock.content.url)" target="_blank" rel="noopener noreferrer" class="break-words font-medium text-[#012877]" x-text="(previewBlock.content.label || 'Official website') + ' →'"></a>
                                    <p x-show="previewBlock.content.description" class="mt-1 break-words text-xs leading-5" x-text="previewContent(previewBlock.content.description, 200)"></p>
                                </div>
                            </template>
                            <template x-if="previewBlock.type === 'reminder'">
                                <div class="rounded-xl bg-amber-50 p-3 text-amber-900">
                                    <p class="font-semibold" x-text="previewBlock.section_title || 'Reminder'"></p>
                                    <p class="mt-1 whitespace-pre-line break-words" x-text="previewContent(previewBlock.content.body)"></p>
                                </div>
                            </template>
                            <template x-if="['instructions', 'checklist'].includes(previewBlock.type)">
                                <div>
                                    <p x-show="previewBlock.section_title || previewBlock.type === 'checklist'" class="mb-1 font-medium text-slate-800" x-text="previewBlock.section_title || 'Things to prepare'"></p>
                                    <ul class="list-disc space-y-1 pl-4">
                                        <template x-for="previewItem in previewBlock.content.items" :key="previewItem.key"><li class="break-words" x-text="previewContent(previewItem.body, 300)"></li></template>
                                    </ul>
                                </div>
                            </template>
                            <template x-if="['requirements', 'fees', 'offices', 'processing_time'].includes(previewBlock.type)">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="font-medium text-slate-800" x-text="previewBlock.section_title || ({ requirements: 'Requirements', fees: 'Fees', offices: 'Offices', processing_time: 'Processing Time' })[previewBlock.type]"></p>
                                    <p x-show="previewContent(previewBlock.content.intro)" class="mb-1 break-words" x-text="previewContent(previewBlock.content.intro, 180)"></p>
                                    <p x-text="previewReference(previewBlock.type)"></p>
                                    <span x-show="previewBlock.type !== 'processing_time'" class="mt-1 block font-medium text-[#012877]" x-text="previewBlock.type === 'requirements' ? 'View requirements →' : previewBlock.type === 'fees' ? 'View applicable fees →' : @js($guideConfig['previewReferences']['officeAction'])"></span>
                                </div>
                            </template>
                            <template x-if="previewBlock.type === 'custom'">
                                <div>
                                    <p x-show="previewBlock.section_title" class="font-medium text-slate-800" x-text="previewBlock.section_title"></p>
                                    <p class="whitespace-pre-line break-words" x-text="previewContent(previewBlock.content.body)"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </article>
            </template>
        </div>
    </div>
</aside>
