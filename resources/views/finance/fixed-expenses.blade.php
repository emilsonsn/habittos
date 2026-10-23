<div class="fixed-cashflow-grid">
    @include('finance.fixed-entries', [
        'entryType' => 'income',
        'heading' => 'Receitas fixas',
        'total' => $fixedIncome,
    ])
    @include('finance.fixed-entries', [
        'entryType' => 'expense',
        'heading' => 'Gastos fixos',
        'total' => $fixed,
    ])
</div>
