<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Requirement;
use Illuminate\Http\Request;

class RequirementController extends Controller
{
    public function index()
    {
        $requirements = Requirement::all();

        return view('admin.requirements.index', compact('requirements'));
    }

    public function create()
    {
        return view('admin.requirements.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
        ]);

        Requirement::create($validated);

        return redirect()->route('admin.requirements.index');
    }

    public function show(string $id)
    {
        $requirement = Requirement::findOrFail($id);

        return view('admin.requirements.show', compact('requirement'));
    }

    public function edit(string $id)
    {
        $requirement = Requirement::findOrFail($id);

        return view('admin.requirements.edit', compact('requirement'));
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
        ]);

        $requirement = Requirement::findOrFail($id);

        $requirement->update($validated);

        return redirect()->route(
            'admin.requirements.show',
            $requirement->id
        );
    }

    public function destroy(string $id)
    {
        $requirement = Requirement::findOrFail($id);

        $requirement->delete();

        return redirect()->route('admin.requirements.index');
    }
}