<section class="panel category-breakdown">
    <div class="section-heading">
        <div><span class="eyebrow">PARA ONDE VAI</span>
            <h2>Despesas por categoria</h2>
        </div>
    </div>
    <x-category-pie :totals="$byCategory" />
    @can('budget.manage', $workspace)
        <details>
            <summary>+ Nova categoria</summary>
            <form wire:submit="saveCategory" class="form-stack">
                <label>Nome<input wire:model="categoryName" maxlength="80" required></label>
                @error('categoryName')
                    <span class="field-error">{{ $message }}</span>
                @enderror
                <button class="button secondary" wire:loading.attr="disabled">Adicionar categoria</button>
            </form>
        </details>
    @endcan
</section>
