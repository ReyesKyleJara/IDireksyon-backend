<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResidentRoadmapGoalRequest;
use App\Models\GovernmentId;
use App\Models\ResidentRoadmapGoal;
use App\Services\ResidentRoadmapGoals;
use Illuminate\Http\Request;

class ResidentRoadmapGoalController extends Controller
{
    public function __construct(private readonly ResidentRoadmapGoals $goals) {}

    private function resident(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->role === 'resident', 403);

        return $user;
    }

    private function ownedIds($user): array
    {
        return $user->ownedGovernmentIds()->pluck('government_ids.id')->map(fn ($id) => (int) $id)->all();
    }

    public function index(Request $request)
    {
        $user = $this->resident($request);
        $owned = $this->ownedIds($user);
        $rows = ResidentRoadmapGoal::with('governmentId')->where('user_id', $user->id)->orderByDesc('updated_at')->orderBy('id')->get();

        return response()->json(['data' => $rows->map(fn ($goal) => $this->goals->present($goal, $owned))]);
    }

    public function show(Request $request, GovernmentId $governmentId)
    {
        $user = $this->resident($request);
        $goal = ResidentRoadmapGoal::with('governmentId')->where('user_id', $user->id)->where('government_id_id', $governmentId->id)->firstOrFail();

        return response()->json(['data' => $this->goals->present($goal, $this->ownedIds($user))]);
    }

    public function save(ResidentRoadmapGoalRequest $request, GovernmentId $governmentId)
    {
        $user = $this->resident($request);
        $supplied = $request->profileSelections();
        $valid = $this->goals->currentSelections($supplied->all());
        $goal = ResidentRoadmapGoal::updateOrCreate(
            ['user_id' => $user->id, 'government_id_id' => $governmentId->id],
            ['application_type' => $request->validated('application_type'), 'profile_selections' => $valid->all()],
        );

        return response()->json(['data' => $this->goals->present($goal, $this->ownedIds($user), $supplied->all() !== $valid->all())]);
    }

    public function destroy(Request $request, GovernmentId $governmentId)
    {
        $user = $this->resident($request);
        $goal = ResidentRoadmapGoal::where('user_id', $user->id)->where('government_id_id', $governmentId->id)->firstOrFail();
        $goal->delete();

        return response()->noContent();
    }
}
