<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GovernmentIdSequenceRequest;
use App\Models\GovernmentId;
use App\Services\PrerequisitePlanner;
use App\Support\ResidentPrerequisitePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class GovernmentIdSequenceController extends Controller
{
    public function __invoke(GovernmentIdSequenceRequest $request, GovernmentId $governmentId, PrerequisitePlanner $planner): JsonResponse
    {
        $context = $request->applicantContext();
        $plan = $planner->plan($governmentId, $context, $request->validated('selected_profile_id'), $request->profileSelections());
        if ($plan->status === 'configuration_error') {
            // Diagnostics stay server-side; do not log submitted facts, ownership, or maintainer notes.
            Log::warning('Government ID prerequisite configuration error.', [
                'government_id_id' => $governmentId->id,
                'errors' => $plan->errors,
            ]);
        }

        return response()->json(['data' => ResidentPrerequisitePlan::forPlan($governmentId, $context, $plan)]);
    }
}
