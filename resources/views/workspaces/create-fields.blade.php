<label>Nome do ambiente<input wire:model="name" maxlength="100" placeholder="Ex.: Nossa casa"
        autocomplete="off" required autofocus></label>
@error('name')
    <p class="field-error" role="alert">{{ $message }}</p>
@enderror
<x-workspace-cover-upload id="create-workspace-cover" :upload="$cover" :ready="$coverReady" :upload-x="$positionX" :upload-y="$positionY" :version="$coverVersion" />
