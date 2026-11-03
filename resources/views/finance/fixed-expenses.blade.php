<div class="fixed-cashflow-grid">
    @include('finance.fixed-entries', [
        'entryType' => \App\Enums\EntryType::Income->value,
        'heading' => 'Receitas fixas',
        'total' => $fixedIncome,
    ])
    @include('finance.fixed-entries', [
        'entryType' => \App\Enums\EntryType::Expense->value,
        'heading' => 'Gastos fixos',
        'total' => $fixed,
    ])
</div>
