@props(['image', 'editorId', 'version'])
<dialog class="app-dialog cover-position-dialog" wire:ignore.self wire:key="{{ $editorId }}-{{ $version }}" aria-labelledby="cover-position-title-{{ $editorId }}"
    x-data="coverPositionEditor($wire)" x-init="$nextTick(() => { $el.showModal(); resize(); })"
    @resize.window.debounce.100ms="resize()" @cancel.prevent="$wire.cancelCoverPosition()">
    <form @submit.prevent="save()" class="dialog-content form-stack">
        <div class="dialog-top">
            <h2 id="cover-position-title-{{ $editorId }}">Ajustar capa</h2>
            <button type="button" class="icon-close" wire:click="cancelCoverPosition" wire:loading.attr="disabled" aria-label="Fechar">×</button>
        </div>
        <p id="cover-position-help-{{ $editorId }}">Arraste a imagem para escolher o que aparece na capa. Você também pode usar as setas do teclado.</p>
        <div class="cover-position-reference" x-ref="reference" aria-hidden="true"></div>
        <div class="cover-position-viewport" x-ref="viewport" :style="'aspect-ratio: ' + ratio"
            tabindex="0" role="group" aria-label="Enquadramento da capa" aria-describedby="cover-position-help-{{ $editorId }}"
            :class="{ 'is-dragging': dragging }"
            @pointerdown.prevent="start($event)" @pointermove="move($event)"
            @pointerup="end($event)" @pointercancel="end($event)" @lostpointercapture="dragging = false"
            @keydown.arrow-left.prevent="nudge(-1, 0)" @keydown.arrow-right.prevent="nudge(1, 0)"
            @keydown.arrow-up.prevent="nudge(0, -1)" @keydown.arrow-down.prevent="nudge(0, 1)">
            <img src="{{ $image }}" alt="Prévia do enquadramento da capa" draggable="false"
                x-ref="image" @load="resize()" :style="'object-position: ' + x + '% ' + y + '%'">
        </div>
        @error('positionX')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        @error('positionY')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <div class="dialog-actions">
            <button type="button" class="text-button" @click="x = 50; y = 50">Centralizar</button>
            <button type="button" class="button secondary" wire:click="cancelCoverPosition" wire:loading.attr="disabled">Cancelar</button>
            <button type="submit" class="button" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="confirmCoverPosition">Usar enquadramento</span>
                <span wire:loading wire:target="confirmCoverPosition">Confirmando…</span>
            </button>
        </div>
    </form>
</dialog>
