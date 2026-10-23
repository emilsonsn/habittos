<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntryRequest;
use App\Models\Workspace;
use App\Services\Money;
use App\Services\OpenFinancialMonth;
use App\Services\SaveEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $request->input('month') ?? now()->format('Y-m');

        return view('finance.index', compact('workspace', 'month'));
    }

    public function openMonth(Request $request, Workspace $workspace, OpenFinancialMonth $opener)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $copied = $opener->handle($workspace, $data['month']);
        $redirect = redirect()->route('finance.index', [$workspace, 'month' => $data['month']]);

        return $copied > 0
            ? $redirect->with('success', $copied.' gasto(s) fixo(s) copiado(s) do mês anterior. Os pagamentos começam pendentes.')
            : $redirect;
    }

    public function create(Request $request, Workspace $workspace)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return view('finance.form', ['workspace' => $workspace, 'entry' => null]);
    }

    public function store(EntryRequest $request, Workspace $workspace)
    {
        app(SaveEntry::class)->handle($workspace, $request->entryData());

        return redirect()->route('finance.index', [$workspace, 'month' => substr($request->validated('date'), 0, 7)])->with('success', 'Lançamento registrado.');
    }

    public function edit(Workspace $workspace, int $entry)
    {
        return view('finance.form', ['workspace' => $workspace, 'entry' => $workspace->entries()->findOrFail($entry), 'categories' => $workspace->categories()->orderBy('name')->get()]);
    }

    public function update(EntryRequest $request, Workspace $workspace, int $entry)
    {
        app(SaveEntry::class)->handle($workspace, $request->entryData(), $entry);

        return redirect()->route('finance.index', [$workspace, 'month' => substr($request->validated('date'), 0, 7)])->with('success', 'Lançamento atualizado.');
    }

    public function destroy(Workspace $workspace, int $entry)
    {
        $workspace->entries()->findOrFail($entry)->delete();

        return back()->with('success', 'Lançamento excluído.');
    }

    public function budget(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'weekly_limit' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
            'reserve' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ]);
        $workspace->budgets()->updateOrCreate(['month' => $data['month']], ['weekly_limit_cents' => Money::cents($data['weekly_limit']), 'reserve_cents' => Money::cents($data['reserve'])]);

        return back()->with('success', 'Planejamento salvo.');
    }

    public function category(Request $request, Workspace $workspace)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories')->where('workspace_id', $workspace->id)]]);
        $workspace->categories()->create($data);

        return back()->with('success', 'Categoria criada.');
    }
}
