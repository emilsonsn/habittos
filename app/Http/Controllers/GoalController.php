<?php

namespace App\Http\Controllers;

use App\Enums\GoalSection;
use App\Models\Workspace;
use App\Services\ManageGoals;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Workspace $workspace, string $section = 'dashboard')
    {
        $goalSection = GoalSection::from($section);

        return view('goals.index', compact('workspace', 'goalSection'));
    }

    public function store(Request $request, Workspace $workspace, ManageGoals $goals)
    {
        $goals->save($workspace, $request->all());

        return back()->with('success', 'Meta criada.');
    }

    public function update(Request $request, Workspace $workspace, int $goal, ManageGoals $goals)
    {
        $goals->save($workspace, $request->all(), $goal);

        return back()->with('success', 'Meta atualizada.');
    }

    public function destroy(Workspace $workspace, int $goal, ManageGoals $goals)
    {
        $goals->delete($workspace, $goal);

        return back()->with('success', 'Meta excluída.');
    }
}
