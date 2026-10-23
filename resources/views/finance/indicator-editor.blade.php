<form wire:submit="saveIndicator" class="indicator-editor"
    wire:key="indicator-editor-{{ $editingIndicator }}-{{ $month }}">
    <label for="indicator-{{ $editingIndicator }}" class="sr-only">{{ $label }} (R$)</label>
    <div class="indicator-input">
        <span aria-hidden="true">R$</span>
        <x-money-input id="indicator-{{ $editingIndicator }}" model="indicatorValue" autofocus
            wire:keydown.escape="cancelIndicator" :required="$editingIndicator !== 'investment_balance'" />
    </div>
    @error('indicatorValue')
        <span class="field-error" role="alert">{{ $message }}</span>
    @enderror
    <div class="indicator-edit-actions">
        <button class="button" wire:loading.attr="disabled" wire:target="saveIndicator">Salvar</button>
        <button type="button" class="text-button" wire:click="cancelIndicator"
            wire:loading.attr="disabled">Cancelar</button>
    </div>
</form>
