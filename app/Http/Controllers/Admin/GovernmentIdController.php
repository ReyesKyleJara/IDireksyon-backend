<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GovernmentId;

class GovernmentIdController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $governmentIds = GovernmentId::all();
        
        return view('admin.government_ids.index', compact('governmentIds'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.government_ids.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
        'name' => 'required',
        'agency' => 'required',
        'purpose' => 'nullable',
        'validity' => 'nullable',
        'description' => 'nullable',
    ]);

    GovernmentId::create($validated);

    return redirect()->route('admin.government-ids.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
    $governmentId = GovernmentId::findOrFail($id);

    return view('admin.government_ids.edit', compact('governmentId'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
    $validated = $request->validate([
        'name' => 'required',
        'agency' => 'required',
        'purpose' => 'nullable',
        'validity' => 'nullable',
        'description' => 'nullable',
    ]);

    $governmentId = GovernmentId::findOrFail($id);

    $governmentId->update($validated);

    return redirect()->route('admin.government-ids.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
    $governmentId = GovernmentId::findOrFail($id);

    $governmentId->delete();

    return redirect()->route('admin.government-ids.index');
    }
}
