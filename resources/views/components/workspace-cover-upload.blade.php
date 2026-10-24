@props(['id', 'upload' => null, 'currentUrl' => null, 'workspaceId' => null, 'positionX' => 50, 'positionY' => 50, 'ready' => false, 'uploadX' => 50, 'uploadY' => 50, 'version' => 0])
<div class="form-stack">
    @if (($upload && $ready && !$errors->has('cover')) || $currentUrl)
        <div class="cover-preview-frame" wire:key="{{ $id }}-preview-{{ $version }}-{{ $ready ? 'draft' : 'saved' }}"
            x-data="coverPositionPreview" x-init="$nextTick(() => resize())" @resize.window.debounce.100ms="resize()"
            :style="'aspect-ratio: ' + ratio">
            <div class="cover-position-reference" x-ref="reference" aria-hidden="true"></div>
            @if ($upload && $ready && !$errors->has('cover'))
                <img class="workspace-cover-preview" src="{{ $upload->temporaryUrl() }}" alt="Prévia da nova capa do ambiente"
                    style="object-position: {{ $uploadX }}% {{ $uploadY }}%">
            @else
                <x-workspace-cover-image class="workspace-cover-preview" :src="$currentUrl" alt="Capa atual do ambiente"
                    :workspace-id="$workspaceId" :position-x="$positionX" :position-y="$positionY" />
            @endif
        </div>
    @endif
    <label for="{{ $id }}">Capa do ambiente <span class="muted">(opcional)</span>
        <input type="file" id="{{ $id }}" wire:model="cover" wire:key="{{ $id }}-input-{{ $version }}" accept="image/jpeg,image/png,image/webp">
        <small>JPG, PNG ou WebP, até 5 MB. Imagens horizontais ficam melhores.</small>
    </label>
    <span class="muted" wire:loading wire:target="cover" role="status">Enviando imagem…</span>
    @error('cover')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
</div>
