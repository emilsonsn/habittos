<?php

namespace App\Http\Controllers;

use App\Enums\FinanceSection;
use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreateWorkspace;
use App\Services\InvestmentBalance;
use App\Services\ManageMembers;
use App\Services\OpenFinancialMonth;
use App\Services\SelectWorkspace;
use App\Services\WorkspaceCover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function select(Request $request, OpenFinancialMonth $opener)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'module' => ['required', Rule::in(['home', 'finance', 'goals', 'members', 'settings', ...array_map(fn ($section) => $section->routeName(), FinanceSection::cases())])],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $url = app(SelectWorkspace::class)->handle(
            $request->user(), (int) $data['workspace_id'], $data['module'], $data['month'] ?? null,
        );

        return redirect($url);
    }

    public function index(Request $request)
    {
        $activeWorkspace = app(SelectWorkspace::class)->current($request->user());
        $canViewFinance = $activeWorkspace?->allows($request->user(), Permission::FinanceView) ?? false;
        $investmentBalance = $canViewFinance
            ? app(InvestmentBalance::class)->forWorkspace($activeWorkspace)
            : null;
        $hour = now(config('app.timezone'))->hour;
        $greeting = match (true) {
            $hour >= 5 && $hour < 12 => 'Bom dia',
            $hour >= 12 && $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };

        return view('workspaces.index', compact('activeWorkspace', 'canViewFinance', 'investmentBalance', 'greeting'));
    }

    public function store(Request $request, CreateWorkspace $creator)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'cover' => WorkspaceCover::rules(),
            'positionX' => ['sometimes', 'integer', 'between:0,100'], 'positionY' => ['sometimes', 'integer', 'between:0,100'],
        ]);
        $creator->handle($request->user(), $data['name'], $request->file('cover'), (int) ($data['positionX'] ?? 50), (int) ($data['positionY'] ?? 50));

        return back()->with('success', 'Ambiente criado.');
    }

    public function cover(Workspace $workspace)
    {
        abort_unless($workspace->cover_path && Storage::disk('local')->exists($workspace->cover_path), 404);

        return Storage::disk('local')->response($workspace->cover_path, null, ['Cache-Control' => 'private, max-age=3600']);
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
