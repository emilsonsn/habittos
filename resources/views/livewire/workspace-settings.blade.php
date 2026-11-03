<div class="settings-page">
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    <div class="page-heading">
        <div><span class="eyebrow">DO SEU JEITO</span>
            <h1>Configurações<span class="heading-dot">.</span></h1>
            <p>Cuide dos detalhes do seu ambiente.</p>
        </div>
    </div>
    <section class="settings-section">
        <div class="settings-description"><span class="settings-number">01</span>
            <h2>Sobre o ambiente</h2>
            <p>Um nome que faça sentido para os seus planos.</p>
        </div>
        <div class="panel">
            @if ($workspace->owner_id === auth()->id())
                <form wire:submit="save" class="form-stack"><label>Nome do ambiente<input wire:model="name"
                            maxlength="100" required></label>
                    @error('name')
                        <p class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                    <div>
                        <button class="button" wire:loading.attr="disabled" wire:target="save">Salvar
                            alterações</button>
                    </div>
                </form>
            @else
                <h2>{{ $workspace->name }}</h2>
                <p class="muted">Somente o proprietário pode alterar os dados e excluir este ambiente.</p>
            @endif
        </div>
    </section>
    <section class="settings-section">
        <div class="settings-description"><span class="settings-number">02</span>
            <h2>Capa do ambiente</h2>
            <p>Uma imagem para deixar a Home com a sua cara.</p>
        </div>
        <div class="panel">
            @if ($workspace->owner_id === auth()->id())
                <form wire:submit="saveCover" class="form-stack">
                    <x-workspace-cover-upload id="settings-workspace-cover" :upload="$cover" :current-url="$workspace->coverUrl()"
                        :workspace-id="$workspace->id" :position-x="$workspace->cover_position_x" :position-y="$workspace->cover_position_y" :ready="$coverReady" :upload-x="$positionX" :upload-y="$positionY" :version="$coverVersion" />
                    <div class="actions">
                        <button class="button" wire:loading.attr="disabled" wire:target="cover,saveCover,removeCover">
                            <span wire:loading.remove wire:target="saveCover">Salvar capa</span>
                            <span wire:loading wire:target="saveCover">Salvando…</span>
                        </button>
                        @if ($workspace->cover_path)
                            <button type="button" class="text-button danger" wire:click="removeCover" data-confirm="A imagem de capa será removida deste ambiente. Os demais dados serão preservados." data-confirm-title="Remover capa?" data-confirm-label="Remover capa" data-confirm-subject="{{ $workspace->name }}"
                                wire:loading.attr="disabled" wire:target="cover,saveCover,removeCover">Remover capa</button>
                        @endif
                    </div>
                </form>
            @else
                @if ($workspace->cover_path)
                    <x-workspace-cover-image class="workspace-cover-preview" :src="$workspace->coverUrl()" :alt="'Capa do ambiente '.$workspace->name"
                        :workspace-id="$workspace->id" :position-x="$workspace->cover_position_x" :position-y="$workspace->cover_position_y" />
                @endif
                <p class="muted">Somente o proprietário pode alterar a capa.</p>
            @endif
        </div>
    </section>
    @can('members.manage', $workspace)
        <section class="settings-section">
            <div class="settings-description"><span class="settings-number">03</span>
                <h2>Pessoas e acessos</h2>
                <p>Escolha quem participa e o que cada pessoa pode fazer.</p>
            </div>
            <div class="panel settings-link">
                <div>
                    <h2>Um espaço compartilhado</h2>
                    <p class="muted">Gerencie os membros e suas permissões.</p>
                </div><a wire:navigate class="button secondary" href="{{ route('members.index', $workspace) }}">Gerenciar
                    acessos ↗</a>
            </div>
        </section>
    @endcan
    @if ($workspace->owner_id === auth()->id())
        <section class="settings-section" x-data>
            <div class="settings-description"><span class="settings-number">04</span>
                <h2>Excluir ambiente</h2>
                <p>Esta ação é definitiva. Faça isso somente se não precisar mais dos dados.</p>
            </div>
            <div class="panel danger-panel">
                <h2>Encerrar este espaço</h2>
                <p>Todos os lançamentos, metas, notas, atividades da rotina, categorias, orçamentos e acessos deste ambiente serão excluídos. As
                    contas dos participantes e os outros ambientes serão preservados.</p><button type="button"
                    class="button danger-button" @click="$refs.deleteDialog.showModal()">Excluir ambiente</button>
            </div>
            <x-confirmation-dialog id="delete-space" x-ref="deleteDialog" wire:ignore.self
                :title="'Excluir '.$workspace->name.'?'"
                description="Todos os lançamentos, metas, notas, pastas, atividades da rotina, categorias, orçamentos e acessos deste ambiente serão apagados. As contas dos participantes e os outros ambientes serão preservados. Esta ação não pode ser desfeita."
                close-action="$refs.deleteDialog.close()" @click="if ($event.target === $el) $el.close()">
                <form wire:submit="deleteWorkspace" class="form-stack">
                    <label>Digite “{{ $workspace->name }}” para confirmar<input wire:model="confirmation" autocomplete="off" required></label>
                    @error('confirmation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <div class="dialog-actions confirmation-actions">
                        <button type="button" class="button secondary" @click="$refs.deleteDialog.close()">Cancelar</button>
                        <button class="button confirmation-accept" wire:loading.attr="disabled" wire:target="deleteWorkspace">Excluir definitivamente</button>
                    </div>
                </form>
            </x-confirmation-dialog>
        </section>
    @endif
    @if ($positioning && $cover && !$errors->has('cover'))
        <x-workspace-cover-editor :image="$cover->temporaryUrl()" :editor-id="'settings-cover-editor-'.$workspaceId" :version="$coverVersion" />
    @endif
</div>
