<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\ManageMembers;
use App\Services\OpenFinancialMonth;
use App\Services\SelectWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function select(Request $request, OpenFinancialMonth $opener)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'module' => ['required', Rule::in(['finance', 'goals', 'members', 'settings'])],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $url = app(SelectWorkspace::class)->handle(
            $request->user(), (int) $data['workspace_id'], $data['module'], $data['month'] ?? null,
        );

        return redirect($url);
    }

    public function index(Request $request)
    {
        return view('workspaces.index');
    }

    public function store(Request $request, CreateWorkspace $creator)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $creator->handle($request->user(), $data['name']);

        return back()->with('success', 'Ambiente criado.');
    }

    public function members(Workspace $workspace)
    {
        return view('workspaces.members', ['workspace' => $workspace, 'members' => $workspace->members()->get(), 'permissions' => Permission::cases()]);
    }

    public function saveMember(Request $request, Workspace $workspace)
    {
        app(ManageMembers::class)->save($workspace, $request->all());

        return back()->with('success', 'Acesso e permissões atualizados.');
    }

    public function removeMember(Request $request, Workspace $workspace, User $user)
    {
        app(ManageMembers::class)->remove($workspace, $user->id);

        return back()->with('success', 'Acesso removido.');
    }
}
