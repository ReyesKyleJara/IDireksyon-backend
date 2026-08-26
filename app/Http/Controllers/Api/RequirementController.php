<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Requirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(Requirement $requirement): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request, Requirement $requirement): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(Requirement $requirement): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
