@props(['saveAction' => 'save', 'anotherAction' => null, 'editing' => false, 'saveLabel' => 'Salvar', 'anotherLabel' => 'Salvar e cadastrar novo', 'cancelAction' => null, 'showSave' => true, 'compact' => false])
<div {{ $attributes->class(['create-form-actions', 'is-compact' => $compact]) }}>
    @if ($showSave)
        <button type="submit" class="button" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="{{ $saveAction }}">{{ $editing ? 'Salvar alterações' : $saveLabel }}</span>
            <span wire:loading wire:target="{{ $saveAction }}">Salvando…</span>
        </button>
    @endif
    @if (!$editing && $anotherAction)
        <button type="button" class="button secondary" wire:click="{{ $anotherAction }}" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="{{ $anotherAction }}">{{ $anotherLabel }}</span>
            <span wire:loading wire:target="{{ $anotherAction }}">Salvando…</span>
        </button>
    @endif
    @if ($cancelAction)
        <button type="button" class="text-button" wire:click="{{ $cancelAction }}" wire:loading.attr="disabled">Cancelar</button>
    @endif
</div>
