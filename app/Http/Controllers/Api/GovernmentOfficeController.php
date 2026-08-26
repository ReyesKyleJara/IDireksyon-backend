<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernmentOfficeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(GovernmentOffice $governmentOffice): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request, GovernmentOffice $governmentOffice): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(GovernmentOffice $governmentOffice): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
