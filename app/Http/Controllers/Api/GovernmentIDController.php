<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentID;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernmentIDController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(GovernmentID $governmentID): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request, GovernmentID $governmentID): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(GovernmentID $governmentID): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
