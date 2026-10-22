<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GovernmentIdApplicabilityRequest;
use App\Models\GovernmentId;
use App\Services\GovernmentIdApplicability;
use Illuminate\Http\JsonResponse;

class GovernmentIdApplicabilityController extends Controller
{
    public function __invoke(GovernmentIdApplicabilityRequest $request, GovernmentId $governmentId, GovernmentIdApplicability $applicability): JsonResponse
    {
        return response()->json(['data' => $applicability->evaluate(
            $governmentId,
            $request->applicantContext(),
            $request->validated('selected_profile_id'),
        )]);
    }
}
