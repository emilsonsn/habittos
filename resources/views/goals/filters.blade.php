<div class="goal-filters">
    <label class="goal-search">Buscar meta<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nome da meta"></label>
    <label>Status<select wire:model.live="statusFilter"><option value="">Todos</option>
        @foreach (\App\Models\Goal::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
    </select></label>
    <label>Prioridade<select wire:model.live="priorityFilter"><option value="">Todas</option>
        @foreach (\App\Models\Goal::PRIORITIES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
    </select></label>
    <label>Categoria<select wire:model.live="categoryFilter"><option value="">Todas</option><option value="__empty__">Sem categoria</option>
        @foreach ($categoryOptions as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
    </select></label>
    <label>Prazo a partir de<input type="date" wire:model.live="dateFrom"></label>
    <label>Prazo até<input type="date" wire:model.live="dateTo"></label>
</div>
<div class="goal-filter-footer">
    <button type="button" class="text-button" wire:click="clearFilters">Limpar filtros</button>
    @error('dateFrom')<span class="field-error" role="alert">{{ $message }}</span>@enderror
    @error('dateTo')<span class="field-error" role="alert">{{ $message }}</span>@enderror
</div>
