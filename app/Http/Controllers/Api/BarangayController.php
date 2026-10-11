<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use Illuminate\Http\JsonResponse;

class BarangayController extends Controller
{
    public function index(): JsonResponse
    {
        $barangays = Barangay::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return response()->json([
            'data' => $barangays,
        ]);
    }
}