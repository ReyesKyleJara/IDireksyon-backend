<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
{
    $sort = $request->input('sort', 'name_asc');

    $documents = Document::query()
        ->when($request->filled('q'), function ($query) use ($request) {
            $search = $request->string('q')->trim();

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('issued_by', 'like', "%{$search}%");
            });
        })
        ->when($request->filled('level'), function ($query) use ($request) {
            $query->where('level', $request->level);
        })
        ->when($request->filled('category'), function ($query) use ($request) {
            $query->where('category', $request->category);
        })
        ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
        ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
        ->when($sort === 'level', fn ($query) => $query->orderBy('level')->orderBy('name'))
        ->when($sort === 'category', fn ($query) => $query->orderBy('category')->orderBy('name'))
        ->when($sort === 'recent', fn ($query) => $query->latest())
        ->get();

    return view('admin.documents.index', compact('documents', 'sort'));
}

    public function create()
    {
        return view('admin.documents.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateDocument($request);

        Document::create($validated);

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document added successfully.');
    }

    public function edit(Document $document)
    {
        return view('admin.documents.edit', compact('document'));
    }

    public function update(Request $request, Document $document)
    {
        $validated = $this->validateDocument($request);

        $document->update($validated);

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(Document $document)
    {
        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'level' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],

            'issued_by' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'string'],
            'eligibility' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],

            'fee' => ['nullable', 'string', 'max:255'],
            'processing_time' => ['nullable', 'string', 'max:255'],
            'validity' => ['nullable', 'string', 'max:255'],
        ]);
    }
}