<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\ManageGoals;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Workspace $workspace)
    {
        return view('goals.index', compact('workspace'));
    }

    public function store(Request $request, Workspace $workspace, ManageGoals $goals)
    {
        $goals->create($workspace, $request->all());

        return back()->with('success', 'Meta criada.');
    }

    public function update(Request $request, Workspace $workspace, int $goal, ManageGoals $goals)
    {
        $data = $request->validate(['current' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99']]);
        $goals->progress($workspace, $goal, (string) $data['current']);

        return back()->with('success', 'Progresso atualizado.');
    }

    public function destroy(Workspace $workspace, int $goal, ManageGoals $goals)
    {
        $goals->delete($workspace, $goal);

        return back()->with('success', 'Meta excluída.');
    }
}
