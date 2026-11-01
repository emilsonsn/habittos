<div class="dialog-content form-stack">
    <div class="dialog-top">
        <div><span class="eyebrow">UM PASSO MAIS PERTO</span><h2 id="goal-dialog-title">{{ $editingId ? ($workspace->allows(auth()->user(), \App\Enums\Permission::GoalsManage) ? 'Editar meta' : 'Detalhes da meta') : 'Nova meta' }}</h2></div>
        <button type="button" class="icon-close" wire:click="closeForm" aria-label="Fechar">×</button>
    </div>
    <form wire:submit="saveGoal" class="form-stack">
        <fieldset class="goal-form-fields" @cannot('goals.manage', $workspace) disabled @endcannot>
            <label>Nome da meta<input wire:model="form.title" maxlength="200" required autofocus x-init="$el.focus()"></label>
            <div class="form-grid">
                <label>Status<select wire:model="form.status">
                    @foreach (\App\Models\Goal::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select></label>
                <label>Prioridade<select wire:model="form.priority">
                    @foreach (\App\Models\Goal::PRIORITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select></label>
                <label>Categoria<select wire:model="form.category"><option value="">Sem categoria</option>
                    @foreach ($categoryOptions as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
                </select></label>
            </div>
            <details class="entry-advanced" @invalid.capture="$el.open = true" @if ($errors->has('form.due_date') || $errors->has('form.progress_percent') || $errors->has('form.parent_id')) open @endif>
                <summary><span>Avançado</span><small>Prazo, progresso e vínculos</small><span class="advanced-chevron" aria-hidden="true">⌄</span></summary>
                <div class="entry-advanced-fields form-stack">
                    <div class="form-grid">
                        <label>Prazo<input type="date" wire:model="form.due_date"></label>
                        <label>Feito (%)<input type="number" wire:model="form.progress_percent" min="0" max="100" step="0.01" required><small>Este percentual é o progresso da meta.</small></label>
                    </div>
                    @if ($period !== 'annual')
                        <label>Meta maior <span class="muted">(opcional)</span><select wire:model="form.parent_id"><option value="">Sem vínculo</option>
                            @foreach ($parentOptions as $parent)<option value="{{ $parent->id }}">{{ $parent->title }} · {{ $parent->periodLabel() }}</option>@endforeach
                        </select></label>
                    @endif
                </div>
            </details>
        </fieldset>
        @foreach ($errors->getMessages() as $field => $messages)
            @if (str_starts_with($field, 'form.'))
                @foreach ($messages as $error)<p class="field-error" role="alert">{{ $error }}</p>@endforeach
            @endif
        @endforeach
        <div class="dialog-actions goal-dialog-actions">
            @can('goals.manage', $workspace)
                @if ($editingId)
                    <button type="button" class="text-button danger goal-delete-button" wire:click="deleteGoal({{ $editingId }})"
                        wire:confirm="Excluir esta meta? As metas vinculadas serão preservadas sem o vínculo." wire:loading.attr="disabled">Excluir meta</button>
                @endif
                <button type="button" class="button secondary" wire:click="closeForm">Cancelar</button>
                <button class="button" wire:loading.attr="disabled">{{ $editingId ? 'Salvar alterações' : 'Criar meta' }}</button>
            @else
                <button type="button" class="button secondary" wire:click="closeForm">Fechar</button>
            @endcan
        </div>
    </form>
</div>
