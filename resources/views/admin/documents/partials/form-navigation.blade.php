<nav aria-label="Document form sections" class="mb-6 overflow-x-auto rounded-xl border border-slate-200 bg-white p-4">
    <p class="mb-3 text-xs font-medium text-slate-500">Jump to a section</p>
    <ol class="flex min-w-max items-center gap-2">
        @foreach([
            'document-basic' => 'Basic Information',
            'document-eligibility' => 'Eligibility',
            'checklist-requirements' => 'Requirements',
            'document-fees' => 'Fees',
            'document-validity' => 'Validity',
            'document-processing' => 'Processing Time',
            'document-guide' => 'Application Guide',
            'document-offices' => 'Offices',
        ] as $anchor => $label)
            <li>
                <a href="#{{ $anchor }}" class="inline-flex rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-[#012877] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#012877]">{{ $label }}</a>
            </li>
        @endforeach
    </ol>
    <p class="mt-3 text-xs leading-5 text-slate-500">{{ $governmentId ? 'Save Changes saves the main form. Requirements use their existing Save checklist action.' : 'Create Document saves all sections together. Save before leaving this page.' }}</p>
</nav>
