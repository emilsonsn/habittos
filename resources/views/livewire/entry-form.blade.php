<div class="dialog-content form-stack">
    <div class="dialog-top">
        <div><span class="eyebrow">UM REGISTRO, MAIS CLAREZA</span>
            <h2 id="entry-dialog-title">{{ $entryId ? 'Editar lançamento' : 'Novo lançamento' }}</h2>
        </div>
        <button type="button" class="icon-close" wire:click="cancel" wire:loading.attr="disabled" aria-label="Fechar">×</button>
    </div>
    @if ($success)
        <div class="notice success" role="status" wire:key="entry-success-{{ $formVersion }}"
            x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 6000)" x-transition.opacity>
            {{ $success }}<button type="button" @click="visible = false" aria-label="Dispensar mensagem">×</button>
        </div>
    @endif
    <form wire:submit="save" class="form-stack" wire:key="entry-form-{{ $formVersion }}">
        <div class="entry-types">
            @foreach (\App\Enums\EntryType::options() as $value => $label)
                <label><input type="radio" wire:model.live="form.type"
                        value="{{ $value }}"><span>{{ $label }}</span></label>
            @endforeach
        </div>
        @error('form.type')
            <span class="field-error">{{ $message }}</span>
        @enderror
        <label>Descrição<input wire:model="form.description" maxlength="180" placeholder="Ex.: Mercado da semana"
                required autofocus x-init="$el.focus()">
            @error('form.description')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </label>
        <div class="form-grid">
            <label>Valor (R$)<x-money-input model="form.amount" required />
                <small>Para investimentos, valores positivos são aportes e negativos são retiradas.</small>
                @error('form.amount')
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
        @if (($form['type'] ?? null) === \App\Enums\EntryType::Expense->value)
            <div class="check-group">
                <label class="checkbox"><input type="checkbox" wire:model="form.counts_toward_weekly_spending">Contar no gasto semanal</label>
                <small>Desmarque para desconsiderar esta despesa no gasto da semana. Despesas fixas já ficam fora desse cálculo.</small>
                @error('form.counts_toward_weekly_spending')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
        @endif
        <details class="entry-advanced" @if ($errors->hasAny(['form.date', 'form.paid', 'form.is_fixed', 'form.is_installment', 'form.notes'])) open @endif>
            <summary><span>Avançado</span><small>Data, pagamento, recorrência e observações</small><span
                    class="advanced-chevron" aria-hidden="true">⌄</span></summary>
            <div class="entry-advanced-fields form-stack">
                <label>Data de referência / vencimento<input type="date" wire:model="form.date" required>
                    @error('form.date')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
                <div class="check-group">
                    <label class="checkbox"><input type="checkbox" wire:model="form.paid">Já foi recebido, pago ou
                        investido</label>
                    <label class="checkbox"><input type="checkbox" wire:model="form.is_fixed">Lançamento fixo
                        mensal</label>
                    @if (($form['type'] ?? null) === \App\Enums\EntryType::Expense->value)
                        <label class="checkbox"><input type="checkbox" wire:model="form.is_installment">Parcelado</label>
                    @endif
                    <small>Receitas e despesas fixas e despesas parceladas são copiadas como pendentes ao sincronizar o mês seguinte.</small>
                    @error('form.paid')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    @error('form.is_fixed')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    @error('form.is_installment')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
                <label>Observações <span class="muted">(opcional)</span>
                    <textarea wire:model="form.notes" rows="3" maxlength="2000"></textarea>
                    @error('form.notes')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </label>
            </div>
        </details>
        <div class="form-actions">
            <button class="button" wire:loading.attr="disabled"><span wire:loading.remove
                    wire:target="save">{{ $entryId ? 'Salvar alterações' : 'Salvar' }}</span><span
                    wire:loading wire:target="save">Salvando…</span></button>
            @if (!$entryId)
                <button type="button" class="button secondary" wire:click="saveAndCreateAnother"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveAndCreateAnother">Salvar e lançar outro</span>
                    <span wire:loading wire:target="saveAndCreateAnother">Salvando…</span>
                </button>
            @endif
            <button type="button" class="text-button" wire:click="cancel" wire:loading.attr="disabled">Cancelar</button>
        </div>
    </form>
</div>
