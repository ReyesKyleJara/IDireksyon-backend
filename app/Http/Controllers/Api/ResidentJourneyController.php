<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResidentJourney;
use App\Models\User;
use App\Services\RoadmapPlanner;
use App\Services\CatalogResidentReferences;
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

        $journey = CatalogResidentReferences::transaction(function () use ($user, $planner, $targets, $documents, $key) {
            // Serialize concurrent confirmations of the same account's target set.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = ResidentJourney::where('user_id', $user->id)->where('journey_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $plan = $planner->build($user, $targets, $documents);
            if (($plan['blocked'] ?? collect($plan['steps'])->contains('blocked', true))
                || collect($plan['steps'])->contains(fn ($step) => $step['type'] === 'unresolved_requirement' && ! ResidentJourney::manualPreparation($step))) {
                throw ValidationException::withMessages(['journey' => 'Review the unresolved requirements before confirming this journey.']);
            }

            if (collect($plan['steps'])->every(fn ($step) => $step['already_owned'] ?? false)) {
                throw ValidationException::withMessages(['journey' => 'You already have everything in this roadmap.']);
            }

            return ResidentJourney::create(['user_id' => $user->id, 'journey_key' => $key, 'plan' => $plan]);
        });

        return response()->json(['data' => $journey->present()], $journey->wasRecentlyCreated ? 201 : 200);
    }

    public function resolve(Request $request, int $journey, RoadmapPlanner $planner): JsonResponse
    {
        $user = $this->resident($request);
        $data = $request->validate([
            'resolution' => ['required', 'array:key,set_id,type,id,way_id,reset'],
            'resolution.key' => ['required', 'string', 'max:255'],
            'resolution.set_id' => ['sometimes', 'integer', 'min:1'],
            'resolution.type' => ['sometimes', 'in:government_id,document'],
            'resolution.id' => ['sometimes', 'integer', 'min:1'],
            'resolution.way_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'resolution.reset' => ['sometimes', 'accepted'],
            'user_id' => ['prohibited'], 'plan' => ['prohibited'],
            'completed_step_identities' => ['prohibited'],
        ]);

        return CatalogResidentReferences::transaction(function () use ($user, $journey, $planner, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $saved = ResidentJourney::where('user_id', $user->id)->lockForUpdate()->findOrFail($journey);
            $reset = ! empty($data['resolution']['reset']);
            $step = $reset ? null : collect($saved->roadmapSteps())->first(fn ($step) => $step['type'] === 'unresolved_requirement'
                && ($step['resolution_key'] ?? null) === $data['resolution']['key']);
            if (! $reset && ! $step) {
                throw ValidationException::withMessages(['resolution' => 'This choice is no longer in this journey. Reload your journey.']);
            }
            $resolution = ! $reset && ($step['resolution_ready'] ?? false) && count($data['resolution']) === 1
                ? null : $data['resolution'];
            $plan = $planner->build($user, $saved->plan['target_government_id_ids'] ?? [],
                $saved->plan['target_document_ids'] ?? [], $resolution);
            if ($plan['blocked'] && ! $reset) {
                throw ValidationException::withMessages(['resolution' => 'This option creates a blocked journey. Choose another accepted option.']);
            }
            $saved->update(['plan' => $plan]);
            // Completion rows are retained by identity. New acquisitions start incomplete.
            return response()->json(['data' => $saved->present()]);
        });
    }

    public function complete(Request $request, int $journey): JsonResponse
    {
        $user = $this->resident($request);
        $data = $request->validate([
            'step_identity' => ['required', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
            'acknowledge_order' => ['sometimes', 'boolean'],
            'user_id' => ['prohibited'],
        ]);

        return CatalogResidentReferences::transaction(function () use ($user, $journey, $data) {
            $saved = ResidentJourney::where('user_id', $user->id)->lockForUpdate()->findOrFail($journey);
            if (($data['completed'] ?? true) === false) {
                DB::table('resident_journey_completions')->where('resident_journey_id', $saved->id)
                    ->where('step_identity', $data['step_identity'])->delete();

                return response()->json(['data' => $saved->present()]);
            }
            $steps = $saved->roadmapSteps();
            $step = collect($steps)->first(fn ($step) => ResidentJourney::identity($step) === $data['step_identity']);
            if (! $step || ! ResidentJourney::actionable($step)) {
                throw ValidationException::withMessages(['step_identity' => 'This is not an actionable step in this journey.']);
            }
            $query = DB::table('resident_journey_completions')->where('resident_journey_id', $saved->id);
            $completed = (clone $query)->pluck('step_identity')->all();
            if (! in_array($data['step_identity'], $completed, true)) {
                $waiting = ResidentJourney::waitingOn($step, $steps, $completed);
                // The order is a suggestion: the resident may record what actually happened.
                if ($waiting && ! ($data['acknowledge_order'] ?? false)) {
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

    /** Applies current Inventory and catalog guidance. Completion history is retained. */
    public function refresh(Request $request, int $journey, RoadmapPlanner $planner): JsonResponse
    {
        $user = $this->resident($request);

        return CatalogResidentReferences::transaction(function () use ($user, $journey, $planner) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $saved = ResidentJourney::where('user_id', $user->id)->lockForUpdate()->findOrFail($journey);
            $plan = $planner->build($user, $saved->plan['target_government_id_ids'] ?? [], $saved->plan['target_document_ids'] ?? []);
            if ($plan['blocked']) {
                throw ValidationException::withMessages(['journey' => 'Part of this Roadmap is currently unavailable. Check with the issuing agency.']);
            }
            $saved->forceFill(['plan' => $plan, 'updated_at' => now()])->save();

            return response()->json(['data' => $saved->present()]);
        });
    }

    /** Removes this Roadmap, its progress and its choices. Inventory and Preparation stay. */
    public function destroy(Request $request, int $journey): JsonResponse
    {
        $user = $this->resident($request);

        return CatalogResidentReferences::transaction(function () use ($user, $journey) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $saved = ResidentJourney::where('user_id', $user->id)->lockForUpdate()->findOrFail($journey);
            DB::table('resident_journey_resolutions')->where('user_id', $user->id)->where('journey_key', $saved->journey_key)->delete();
            $saved->delete();

            return response()->json(['data' => ['removed' => true]]);
        });
    }
}
