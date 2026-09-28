<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:agencies,name',
            ],

            'acronym' => [
                'nullable',
                'string',
                'max:50',
            ],

            'official_website' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ]);

        $agency = Agency::create($validated);

        return response()->json([
            'message' => 'Agency added successfully.',
            'agency' => [
                'id' => $agency->id,
                'name' => $agency->name,
                'acronym' => $agency->acronym,
            ],
        ], 201);
    }
}