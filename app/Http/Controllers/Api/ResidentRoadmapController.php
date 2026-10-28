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
            'target_government_id_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'target_government_id_ids.*' => ['required', 'integer', 'distinct', 'exists:government_ids,id'],
        ]);
        return response()->json(['data' => $planner->build($resident, $data['target_government_id_ids'])]);
    }
}
