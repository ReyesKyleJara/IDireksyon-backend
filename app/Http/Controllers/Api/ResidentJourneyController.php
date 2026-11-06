<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\RoadmapPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidentJourneyController extends Controller
{
    private function resident(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->role === 'resident', 403);

        return $user;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resident($request);

        return response()->json(['data' => ResidentJourney::where('user_id', $user->id)
            ->orderBy('id')->get()->map(fn ($journey) => $journey->present())]);
    }

    public function store(Request $request, RoadmapPlanner $planner): JsonResponse
    {
        $user = $this->resident($request);
        $data = $request->validate([
            'target_government_id_ids' => ['required_without:target_document_ids', 'array', 'list', 'max:100'],
            'target_government_id_ids.*' => ['required', 'integer', 'distinct', 'exists:government_ids,id'],
            'target_document_ids' => ['required_without:target_government_id_ids', 'array', 'list', 'max:100'],
            'target_document_ids.*' => ['required', 'integer', 'distinct', 'exists:documents,id'],
            'user_id' => ['prohibited'],
            'completed_step_identities' => ['prohibited'],
            'plan' => ['prohibited'],
        ]);
        $targets = $data['target_government_id_ids'] ?? [];
        $documents = $data['target_document_ids'] ?? [];
        if ($targets === [] && $documents === []) {
            throw ValidationException::withMessages(['targets' => 'Choose at least one target.']);
        }
        $keys = [...array_map(fn ($id) => 'government_id:'.$id, $targets), ...array_map(fn ($id) => 'document:'.$id, $documents)];
        sort($keys);
        $key = hash('sha256', json_encode($keys));

        $journey = DB::transaction(function () use ($user, $planner, $targets, $documents, $key) {
            // Serialize concurrent confirmations of the same account's target set.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = ResidentJourney::where('user_id', $user->id)->where('journey_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $plan = $planner->build($user, $targets, $documents);
            if ($plan['blocked'] ?? collect($plan['steps'])->contains('blocked', true)) {
                throw ValidationException::withMessages(['journey' => 'Review the unresolved requirements before confirming this journey.']);
            }

            if (collect($plan['steps'])->every(fn ($step) => $step['already_owned'] ?? false)) {
                throw ValidationException::withMessages(['journey' => 'You already have everything in this roadmap.']);
            }

            return ResidentJourney::create(['user_id' => $user->id, 'journey_key' => $key, 'plan' => $plan]);
        });

        return response()->json(['data' => $journey->present()], $journey->wasRecentlyCreated ? 201 : 200);
    }

    public function complete(Request $request, int $journey): JsonResponse
    {
        $user = $this->resident($request);
        $data = $request->validate([
            'step_identity' => ['required', 'string', 'max:255'],
            'user_id' => ['prohibited'],
        ]);

        return DB::transaction(function () use ($user, $journey, $data) {
            $saved = ResidentJourney::where('user_id', $user->id)->lockForUpdate()->findOrFail($journey);
            $step = collect($saved->plan['steps'])->first(fn ($step) => ResidentJourney::identity($step) === $data['step_identity']);
            if (! $step || ! ResidentJourney::actionable($step)) {
                throw ValidationException::withMessages(['step_identity' => 'This is not an actionable step in this journey.']);
            }
            $query = DB::table('resident_journey_completions')->where('resident_journey_id', $saved->id);
            $completed = (clone $query)->pluck('step_identity')->all();
            if (! in_array($data['step_identity'], $completed, true)) {
                $waiting = ResidentJourney::waitingOn($step, $saved->plan['steps'], $completed);
                if ($waiting) {
                    throw ValidationException::withMessages(['step_identity' => 'Complete '.implode(', ', array_column($waiting, 'name')).' first.']);
                }
                DB::table('resident_journey_completions')->insert([
                    'resident_journey_id' => $saved->id,
                    'step_identity' => $data['step_identity'],
                    'completed_at' => now(),
                ]);
            }

            return response()->json(['data' => $saved->present()]);
        });
    }
}
