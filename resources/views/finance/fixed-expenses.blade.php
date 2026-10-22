<section class="panel fixed-expenses">
    <div class="section-heading">
        <div>
            <span class="eyebrow">COMPROMISSOS DESTE MÊS</span>
            <h2>Gastos fixos <span
                    class="count">{{ $entries->where('type', 'expense')->where('is_fixed', true)->count() }}</span></h2>
        </div>
        <strong>{{ $money($fixed) }}</strong>
    </div>
    <p class="muted">As alterações ficam neste mês. Os outros meses mantêm seus próprios registros.</p>
    <div class="fixed-expense-list">
        @forelse ($entries->where('type', 'expense')->where('is_fixed', true) as $expense)
            <div class="fixed-expense">
                <div><strong>{{ $expense->description }}</strong><small>Vencimento
                        {{ $expense->date->format('d/m') }}</small></div>
                <strong>{{ $money($expense->amount_cents) }}</strong>
                <span
                    class="tag {{ $expense->paid ? 'settled' : 'pending' }}">{{ $expense->paid ? 'Pago' : 'Pendente' }}</span>
                @can('finance.update', $workspace)
                    <a wire:navigate href="{{ route('entries.edit', [$workspace, $expense]) }}">Editar</a>
                @endcan
            </div>
        @empty
            <p class="muted">Nenhum gasto fixo neste mês. Marque “Compromisso fixo” ao registrar uma despesa.</p>
        @endforelse
    </div>
</section>
