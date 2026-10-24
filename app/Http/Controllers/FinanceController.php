<?php

namespace App\Http\Controllers;

use App\Enums\FinanceSection;
use App\Http\Requests\EntryRequest;
use App\Models\Workspace;
use App\Services\FinancialCalendar;
use App\Services\Money;
use App\Services\OpenFinancialMonth;
use App\Services\SaveEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    public function index(Request $request, Workspace $workspace, string $section = 'dashboard')
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $request->input('month') ?? app(FinancialCalendar::class)->monthForDate($workspace, now()->toDateString());

        $section = FinanceSection::from($section);

        return view('finance.index', compact('workspace', 'month', 'section'));
    }

    public function openMonth(Request $request, Workspace $workspace, OpenFinancialMonth $opener)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'cycleStartInput' => ['required', 'date_format:Y-m-d'], 'cycleEndInput' => ['required', 'date_format:Y-m-d']]);
        $copied = $opener->handle($workspace, $data['month'], $data['cycleStartInput'], $data['cycleEndInput']);
        $redirect = redirect()->route('finance.index', [$workspace, 'month' => $data['month']]);

        return $copied > 0
            ? $redirect->with('success', $copied.' lançamento(s) fixo(s) copiado(s) do mês anterior. Receitas e despesas já estão efetivadas.')
            : $redirect;
    }

    public function create(Request $request, Workspace $workspace)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return redirect()->route('finance.entries', [$workspace,
            'month' => $request->input('month') ?? app(FinancialCalendar::class)->monthForDate($workspace, now()->toDateString()),
            'new-entry' => 1,
        ]);
    }

    public function store(EntryRequest $request, Workspace $workspace)
    {
        app(SaveEntry::class)->handle($workspace, $request->entryData());

        return redirect()->route('finance.index', [$workspace, 'month' => app(FinancialCalendar::class)->monthForDate($workspace, $request->validated('date'))])->with('success', 'Lançamento registrado.');
    }

    public function edit(Workspace $workspace, int $entry)
    {
        $entry = $workspace->entries()->findOrFail($entry);

        return redirect()->route('finance.entries', [$workspace,
            'month' => app(FinancialCalendar::class)->monthForDate($workspace, $entry->date->toDateString()),
            'edit-entry' => $entry->id,
        ]);
    }

    public function update(EntryRequest $request, Workspace $workspace, int $entry)
    {
        app(SaveEntry::class)->handle($workspace, $request->entryData(), $entry);

        return redirect()->route('finance.index', [$workspace, 'month' => app(FinancialCalendar::class)->monthForDate($workspace, $request->validated('date'))])->with('success', 'Lançamento atualizado.');
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
            'reserve' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999.99'],
        ]);
        $workspace->budgets()->updateOrCreate(['month' => $data['month']], ['reserve_cents' => Money::cents($data['reserve'])]);

        return back()->with('success', 'Planejamento salvo.');
    }

    public function category(Request $request, Workspace $workspace)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories')->where('workspace_id', $workspace->id)]]);
        $workspace->categories()->create($data);

        return back()->with('success', 'Categoria criada.');
    }
}
