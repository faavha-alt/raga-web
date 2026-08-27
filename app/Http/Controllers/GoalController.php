<?php

namespace App\Http\Controllers;

use App\Models\TrainingGoal;
use App\Services\Training\TrainingGoalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(private TrainingGoalService $goals) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $goals = $user->trainingGoals()
            ->orderBy('is_active', 'desc')
            ->latest()
            ->get()
            ->map(fn (TrainingGoal $goal) => [
                'goal' => $goal,
                'progress' => $this->goals->progressFor($user, $goal),
            ]);

        return view('goals.index', [
            'goalRows' => $goals,
            'goalTypes' => $this->goals->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:weekly_distance,monthly_distance,weekly_frequency,race,custom'],
            'target_value' => ['nullable', 'numeric', 'min:0.01'],
            'custom_description' => ['nullable', 'string', 'max:255'],
            'target_date' => ['nullable', 'date'],
        ]);

        $request->user()->trainingGoals()->create([
            'type' => $data['type'],
            'target_value' => $data['target_value'] ?? null,
            'custom_description' => $data['custom_description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('goals.index')->with('status', 'Goal ditambahkan.');
    }

    public function destroy(Request $request, TrainingGoal $goal): RedirectResponse
    {
        abort_if($goal->user_id !== $request->user()->id, 403);

        $goal->delete();

        return redirect()->route('goals.index')->with('status', 'Goal dihapus.');
    }
}
