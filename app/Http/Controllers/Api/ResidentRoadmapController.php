<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RoadmapPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentRoadmapController extends Controller
{
    public function plan(Request $request, RoadmapPlanner $planner): JsonResponse
    {
        $resident = $request->user();
        abort_unless($resident->is_active && $resident->role === 'resident', 403);
        $data = $request->validate([
            'target_government_id_ids' => ['required_without:target_document_ids', 'array', 'list', 'max:100'],
            'target_government_id_ids.*' => ['required', 'integer', 'distinct', 'exists:government_ids,id'],
            'target_document_ids' => ['required_without:target_government_id_ids', 'array', 'list', 'max:100'],
            'target_document_ids.*' => ['required', 'integer', 'distinct', 'exists:documents,id'],
            'resolution' => ['sometimes', 'required', 'array:key,set_id,type,id,way_id'],
            'resolution.key' => ['required_with:resolution', 'string', 'max:255'],
            'resolution.set_id' => ['sometimes', 'integer', 'min:1'],
            'resolution.type' => ['sometimes', 'in:government_id,document'],
            'resolution.id' => ['sometimes', 'integer', 'min:1'],
            'resolution.way_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $planner->build($resident, $data['target_government_id_ids'] ?? [], $data['target_document_ids'] ?? [], $data['resolution'] ?? null)]);
    }
}
