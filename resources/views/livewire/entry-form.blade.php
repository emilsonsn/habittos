<div>
    <div class="page-heading">
        <div><span class="eyebrow">UM REGISTRO, MAIS CLAREZA</span>
            <h1>{{ $entryId ? 'Editar lançamento' : 'Novo lançamento' }}</h1>
            <p>Registre o que entrou, saiu ou foi investido.</p>
        </div><a wire:navigate
            href="{{ route('finance.index', [$workspace, 'month' => substr($form['date'], 0, 7)]) }}">← Voltar ao
            financeiro</a>
    </div>
    <section class="panel form-panel">
        <form wire:submit="save" class="form-stack">
            <div class="entry-types">
                @foreach (['expense' => 'Despesa', 'income' => 'Receita', 'investment' => 'Investimento'] as $value => $label)
                    <label><input type="radio" wire:model="form.type"
                            value="{{ $value }}"><span>{{ $label }}</span></label>
                @endforeach
            </div>
            <div class="form-grid">
                <label>Descrição<input wire:model="form.description" maxlength="180"
                        placeholder="Ex.: Mercado da semana" required autofocus>
                    @error('form.description')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
                <label>Valor (R$)<x-money-input model="form.amount" required />
                    @error('form.amount')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
                <label>Data de referência / vencimento<input type="date" wire:model="form.date" required>
                    @error('form.date')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
                <label>Categoria<select wire:model="form.category_id">
                        <option value="">Sem categoria</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('form.category_id')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>
            <div class="check-group"><label class="checkbox"><input type="checkbox" wire:model="form.paid">Já foi
                    recebido, pago ou investido</label><label class="checkbox"><input type="checkbox"
                        wire:model="form.is_fixed">Compromisso fixo</label><small>Este gasto pertence ao mês da data
                    informada. Ele será copiado como pendente ao abrir o mês seguinte.</small></div>
            <label>Observações <span class="muted">(opcional)</span>
                <textarea wire:model="form.notes" rows="3" maxlength="2000"></textarea>
                @error('form.notes')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </label>
            <div class="form-actions"><button class="button" wire:loading.attr="disabled"><span wire:loading.remove
                        wire:target="save">{{ $entryId ? 'Salvar alterações' : 'Registrar lançamento' }}</span><span
                        wire:loading wire:target="save">Salvando…</span></button><a wire:navigate
                    href="{{ route('finance.index', [$workspace, 'month' => substr($form['date'], 0, 7)]) }}">Cancelar</a>
            </div>
        </form>
    </section>
</div>
