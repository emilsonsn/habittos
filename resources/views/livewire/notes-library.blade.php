<div class="notes-library">
    @php($isDashboard = $section === 'dashboard')
    @if ($message)<div class="notice success" role="status">{{ $message }}</div>@endif
    <div class="page-heading">
        <div><span class="eyebrow">UM LUGAR PARA SUAS IDEIAS</span>
            <h1>{{ $isDashboard ? 'Suas notas' : ($section === 'favorites' ? 'Favoritos' : 'Notas') }}<span class="heading-dot">.</span></h1>
            <p>{{ $section === 'favorites' ? 'As notas favoritas deste ambiente, sempre por perto.' : 'Guarde ideias, organize por pastas e encontre quando precisar.' }}</p>
        </div>
        @can('notes.manage', $workspace)
            <div class="actions">
                @if ($section !== 'favorites')<button type="button" class="button secondary" wire:click="openCategory">+ Nova pasta</button>@endif
                <button type="button" class="button" wire:click="openCreateNote">+ Nova nota</button>
            </div>
        @endcan
    </div>

    @if ($isDashboard)
        <div class="metrics notes-metrics">
            <article class="metric"><h2 class="metric-label">Notas no ambiente</h2><strong>{{ $stats['notes'] }}</strong><small>Ideias e registros guardados</small></article>
            <article class="metric"><h2 class="metric-label">Pastas</h2><strong>{{ $stats['folders'] }}</strong><small>Seu espaço organizado</small></article>
            <article class="metric"><h2 class="metric-label">Favoritos do ambiente</h2><strong>{{ $stats['favorites'] }}</strong><small>Um atalho para o essencial</small></article>
            <article class="metric"><h2 class="metric-label">Sem pasta</h2><strong>{{ $stats['uncategorized'] }}</strong><small>Notas na página inicial</small></article>
        </div>
    @endif

    <section class="panel notes-workspace" aria-label="{{ $isDashboard ? 'Visão geral das notas' : 'Biblioteca de notas' }}">
        @if ($isDashboard)
            <div class="section-heading"><div><span class="eyebrow">CADA IDEIA NO SEU LUGAR</span><h2>Suas pastas</h2></div>
                <a wire:navigate class="text-button" href="{{ route('notes.browse', $workspace) }}">Explorar notas →</a>
            </div>
        @elseif ($section === 'browse')
            <div class="notes-folder-header">
                <nav class="notes-breadcrumbs" aria-label="Caminho da pasta">
                    <a wire:navigate href="{{ route('notes.browse', $workspace) }}" @if (!$category) aria-current="page" @endif>Notas</a>
                    @foreach ($breadcrumbs as $crumb)
                        <span aria-hidden="true">/</span>
                        <a wire:navigate href="{{ route('notes.browse', [$workspace, 'category' => $crumb->id]) }}"
                            @if ($crumb->id === $category?->id) aria-current="page" @endif>{{ $crumb->name }}</a>
                    @endforeach
                </nav>
                @if ($category)
                    @can('notes.manage', $workspace)<button type="button" class="text-button" wire:click="openCategory({{ $category->id }})">Editar esta pasta</button>@endcan
                @endif
            </div>
        @endif

        @if (!$isDashboard)
            <label class="notes-search">{{ $section === 'favorites' ? 'Buscar nos favoritos' : 'Buscar notas em todo o ambiente' }}
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Título ou texto da nota" maxlength="200">
            </label>
            @error('search')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        @endif

        @if ($section !== 'favorites' && trim($search) === '')
            @if ($folders->isNotEmpty())
                <div class="notes-folder-grid" aria-label="Pastas">
                    @foreach (($isDashboard ? $folders->take(6) : $folders) as $folder)
                        <article class="notes-folder" wire:key="notes-folder-{{ $folder->id }}">
                            <a wire:navigate class="notes-folder-link" href="{{ route('notes.browse', [$workspace, 'category' => $folder->id]) }}">
                                <span class="notes-folder-icon"><x-icon name="folder" /></span>
                                <span><strong>{{ $folder->name }}</strong><small>{{ $folder->children_count }} {{ $folder->children_count === 1 ? 'subpasta' : 'subpastas' }} · {{ $folder->notes_count }} {{ $folder->notes_count === 1 ? 'nota' : 'notas' }}</small></span>
                            </a>
                            @can('notes.manage', $workspace)
                                <button type="button" class="notes-folder-edit text-button" wire:click="openCategory({{ $folder->id }})" aria-label="Editar pasta {{ $folder->name }}"><x-icon name="pencil" /></button>
                            @endcan
                        </article>
                    @endforeach
                </div>
            @elseif ($isDashboard)
                <p class="muted notes-folder-hint">Crie pastas para organizar suas notas. Você também pode salvar sem pasta.</p>
            @endif
        @endif

        <div class="section-heading notes-list-heading">
            <div><span class="eyebrow">{{ $isDashboard ? 'RETOME DE ONDE PAROU' : ($section === 'favorites' ? 'DESTAQUES DO AMBIENTE' : 'SUAS ANOTAÇÕES') }}</span>
                <h2>{{ $isDashboard ? 'Atualizadas recentemente' : (trim($search) !== '' ? 'Resultados da busca' : ($section === 'favorites' ? 'Notas favoritas' : ($category?->name ?? 'Notas sem pasta'))) }}</h2>
            </div>
            @if (!$isDashboard)<span class="muted notes-total" role="status">{{ $notes->total() }} {{ $notes->total() === 1 ? 'nota' : 'notas' }}</span>@endif
        </div>
        @if ($notes->isNotEmpty())
            <div class="notes-grid">
                @foreach ($notes as $note)
                    <article class="note-card" wire:key="note-{{ $note->id }}">
                        <div class="note-card-top"><span class="note-card-category"><x-icon name="notes" />{{ $note->category?->name ?? 'Sem pasta' }}</span>
                            @can('notes.manage', $workspace)
                            <button type="button" wire:click="toggleFavorite({{ $note->id }})" wire:loading.attr="disabled" wire:target="toggleFavorite({{ $note->id }})"
                                @class(['note-favorite', 'is-favorite' => $note->is_favorite]) aria-pressed="{{ $note->is_favorite ? 'true' : 'false' }}"
                                aria-label="{{ $note->is_favorite ? 'Remover dos favoritos: ' : 'Favoritar: ' }}{{ $note->title }}">{{ $note->is_favorite ? '★' : '☆' }}</button>
                            @else
                                @if ($note->is_favorite)<span class="note-favorite is-favorite" aria-label="Favorita do ambiente">★</span>@endif
                            @endcan
                        </div>
                        <button type="button" class="note-card-open" wire:click="openNote({{ $note->id }})" aria-label="Abrir nota: {{ $note->title }}">
                            <h3>{{ $note->title }}</h3>
                            <p>{{ $note->excerpt() ?: 'Sem texto.' }}</p>
                        </button>
                        <time datetime="{{ $note->updated_at->toIso8601String() }}">Atualizada em {{ $note->updated_at->format('d/m/Y \à\s H:i') }}</time>
                    </article>
                @endforeach
            </div>
            @if (!$isDashboard && $notes->hasPages())
                <nav class="goals-pagination" aria-label="Paginação das notas">
                    <button type="button" class="button secondary" wire:click="previousPage" @disabled($notes->onFirstPage())>Anterior</button>
                    <span>Página {{ $notes->currentPage() }} de {{ $notes->lastPage() }}</span>
                    <button type="button" class="button secondary" wire:click="nextPage" @disabled(!$notes->hasMorePages())>Próxima</button>
                </nav>
            @endif
        @else
            <div class="empty-state notes-empty"><span>{{ $section === 'favorites' ? '☆' : '✎' }}</span>
                <h2>{{ $section === 'favorites' ? 'Nenhuma nota favorita neste ambiente.' : (trim($search) !== '' ? 'Nenhuma nota encontrada.' : 'Uma ideia pode começar aqui.') }}</h2>
                <p>{{ $section === 'favorites' ? 'As notas marcadas com estrela neste ambiente aparecem aqui.' : (trim($search) !== '' ? 'Experimente buscar por outro título ou trecho do texto.' : 'Crie uma nota para guardar o que importa, com ou sem pasta.') }}</p>
            </div>
        @endif
    </section>

    @if ($noteOpen)
        <dialog class="app-dialog notes-dialog" wire:ignore.self wire:key="note-dialog-{{ $formVersion }}" aria-labelledby="note-dialog-title"
            x-data x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeForms()">
            <div class="dialog-content form-stack">
                <div class="dialog-top"><div><span class="eyebrow">ESPAÇO PARA PENSAR</span><h2 id="note-dialog-title">{{ $editingNoteId ? ($workspace->allows(auth()->user(), \App\Enums\Permission::NotesManage) ? 'Editar nota' : 'Ler nota') : 'Nova nota' }}</h2></div>
                    <button type="button" class="icon-close" wire:click="closeForms" aria-label="Fechar">×</button></div>
                <form wire:submit="saveNote" class="form-stack">
                    <fieldset class="notes-form-fields" @cannot('notes.manage', $workspace) disabled @endcannot>
                        <label>Título<input wire:model="noteForm.title" maxlength="200" required autofocus x-init="$el.focus()"></label>
                        <x-rich-text-editor model="noteForm.text" label="Texto" editor-id="note-text" :max="100000"
                            :value="$noteForm['text'] ?? ''" :readonly="!$workspace->allows(auth()->user(), \App\Enums\Permission::NotesManage)" />
                        <label>Pasta <span class="muted">(opcional)</span><select wire:model="noteForm.category_id"><option value="">Sem pasta</option>
                            @foreach ($categoryOptions as $option)<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endforeach
                        </select></label>
                    </fieldset>
                    @foreach ($errors->getMessages() as $field => $messages)
                        @if (str_starts_with($field, 'noteForm.'))
                            @foreach ($messages as $error)<p class="field-error" role="alert">{{ $error }}</p>@endforeach
                        @endif
                    @endforeach
                    <div class="dialog-actions notes-dialog-actions">
                        @can('notes.manage', $workspace)
                            @if ($editingNoteId)<button type="button" class="text-button danger notes-delete" wire:click="deleteNote({{ $editingNoteId }})" wire:confirm="Excluir esta nota?" wire:loading.attr="disabled">Excluir nota</button>@endif
                            <button type="button" class="button secondary" wire:click="closeForms">Cancelar</button>
                            <button class="button" wire:loading.attr="disabled"><span wire:loading.remove wire:target="saveNote">{{ $editingNoteId ? 'Salvar alterações' : 'Criar nota' }}</span><span wire:loading wire:target="saveNote">Salvando…</span></button>
                        @else
                            <button type="button" class="button secondary" wire:click="closeForms">Fechar</button>
                        @endcan
                    </div>
                </form>
            </div>
        </dialog>
    @endif
    @if ($categoryOpen)
        <dialog class="app-dialog notes-category-dialog" wire:ignore.self wire:key="note-category-dialog-{{ $formVersion }}" aria-labelledby="note-category-title"
            x-data x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeForms()">
            <div class="dialog-content form-stack">
                <div class="dialog-top"><div><span class="eyebrow">CADA COISA NO SEU LUGAR</span><h2 id="note-category-title">{{ $editingCategoryId ? 'Editar pasta' : 'Nova pasta' }}</h2></div>
                    <button type="button" class="icon-close" wire:click="closeForms" aria-label="Fechar">×</button></div>
                <form wire:submit="saveCategory" class="form-stack">
                    <label>Nome da pasta<input wire:model="categoryForm.name" maxlength="100" required autofocus x-init="$el.focus()"></label>
                    <label>Pasta superior<select wire:model="categoryForm.parent_id"><option value="">Página inicial de notas</option>
                        @foreach ($categoryOptions as $option)
                            @if ($option['available_parent'])<option value="{{ $option['id'] }}">{{ $option['label'] }}</option>@endif
                        @endforeach
                    </select></label>
                    @foreach ($errors->getMessages() as $field => $messages)
                        @if (str_starts_with($field, 'categoryForm.'))
                            @foreach ($messages as $error)<p class="field-error" role="alert">{{ $error }}</p>@endforeach
                        @endif
                    @endforeach
                    <div class="dialog-actions notes-dialog-actions">
                        @if ($editingCategoryId)<button type="button" class="text-button danger notes-delete" wire:click="deleteCategory({{ $editingCategoryId }})"
                            wire:confirm="Excluir esta pasta? As notas e subpastas serão preservadas na pasta superior." wire:loading.attr="disabled">Excluir pasta</button>@endif
                        <button type="button" class="button secondary" wire:click="closeForms">Cancelar</button>
                        <button class="button" wire:loading.attr="disabled">{{ $editingCategoryId ? 'Salvar alterações' : 'Criar pasta' }}</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif
</div>
