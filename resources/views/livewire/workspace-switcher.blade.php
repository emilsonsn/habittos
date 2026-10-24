<div class="space-control" x-data="{
    open: false,
    search: '',
    moveFocus(direction) {
        const options = [...$refs.options.querySelectorAll('[role=option]')].filter(el => el.offsetParent !== null);
        const index = options.indexOf(document.activeElement);
        options[(index + direction + options.length) % options.length]?.focus();
    }
}" @keydown.escape.window="open = false">
    <div class="space-control-row">
        <button type="button" class="space-trigger" id="workspace-select" aria-label="Ambiente ativo"
            aria-haspopup="listbox" :aria-expanded="open"
            @click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
            @keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.search.focus())">
            <span class="space-monogram">{{ mb_substr($selected?->name ?? 'h', 0, 1) }}</span>
            <span class="space-trigger-label"><small>AMBIENTE
                    ATUAL</small><strong>{{ $selected?->name ?? 'Escolher ambiente' }}</strong></span>
            <svg class="chevron" :class="{ 'rotated': open }" width="16" height="16" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="m7 10 5 5 5-5" />
            </svg>
        </button>
        <button type="button" class="create-space" aria-label="Criar ambiente" title="Criar ambiente"
            @click="$refs.createDialog.showModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8">
                <path d="M12 5v14M5 12h14" />
            </svg>
        </button>
    </div>
    <div class="space-popover" x-show="open" x-cloak x-transition.opacity @click.outside="open = false">
        <div class="space-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.6">
                <circle cx="10" cy="10" r="6" />
                <path d="m15 15 5 5" />
            </svg><input x-ref="search" x-model="search" placeholder="Buscar ambiente…" aria-label="Buscar ambiente"
                @keydown.arrow-down.prevent="moveFocus(1)"></div>
        <div class="space-options" role="listbox" aria-label="Seus ambientes" x-ref="options"
            @keydown.arrow-down.prevent="moveFocus(1)" @keydown.arrow-up.prevent="moveFocus(-1)">
            @foreach ($workspaces as $space)
                <button type="button" role="option"
                    aria-selected="{{ $selected?->id === $space->id ? 'true' : 'false' }}"
                    wire:key="space-{{ $space->id }}" wire:click="selectWorkspace({{ $space->id }})"
                    wire:loading.attr="disabled"
                    x-show="{{ \Illuminate\Support\Js::from(mb_strtolower($space->name)) }}.includes(search.toLocaleLowerCase())">
                    <span class="space-monogram">{{ mb_substr($space->name, 0, 1) }}</span>
                    <span><strong>{{ $space->name }}</strong><small>{{ $space->owner_id === auth()->id() ? 'Seu ambiente' : 'Compartilhado com você' }}</small></span>
                    @if ($selected?->id === $space->id)
                        <span class="selection-check" aria-hidden="true">✓</span>
                    @endif
                </button>
            @endforeach
            <p class="space-search-empty"
                x-show="!{{ \Illuminate\Support\Js::from($workspaces->pluck('name')->map(fn($name) => mb_strtolower($name))->values()) }}.some(name => name.includes(search.toLocaleLowerCase()))">
                Nenhum ambiente encontrado.</p>
        </div>
        <div class="space-popover-footer"><span class="tiny-dot"></span> Seus dados ficam separados em cada ambiente.
        </div>
    </div>
    <dialog class="app-dialog" x-ref="createDialog" wire:ignore.self aria-labelledby="create-space-title"
        @click="if ($event.target === $el) $el.close()">
        <form wire:submit="createWorkspace" class="dialog-content form-stack">
            <div class="dialog-top"><span class="eyebrow">UM NOVO ESPAÇO</span><button type="button" class="icon-close"
                    aria-label="Fechar" @click="$refs.createDialog.close()">×</button></div>
            <div>
                <h2 id="create-space-title">O que vamos organizar?</h2>
                <p class="muted">Um espaço para você ou para compartilhar seus planos.</p>
            </div>
            <label>Nome do ambiente<input wire:model="name" maxlength="100" placeholder="Ex.: Nossa casa"
                    autocomplete="off" required autofocus></label>
            @error('name')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
            <x-workspace-cover-upload id="create-workspace-cover" :upload="$cover" :ready="$coverReady" :upload-x="$positionX" :upload-y="$positionY" :version="$coverVersion" />
            <div class="dialog-actions"><button type="button" class="button secondary"
                    @click="$refs.createDialog.close()">Cancelar</button><button class="button"
                    wire:loading.attr="disabled"><span wire:loading.remove wire:target="createWorkspace">Criar
                        ambiente</span><span wire:loading wire:target="createWorkspace">Criando…</span></button></div>
        </form>
    </dialog>
    @if ($positioning && $cover && !$errors->has('cover'))
        <x-workspace-cover-editor :image="$cover->temporaryUrl()" editor-id="create-cover-editor" :version="$coverVersion" />
    @endif
    <span class="space-loading" wire:loading wire:target="selectWorkspace" role="status">Abrindo ambiente…</span>
</div>
