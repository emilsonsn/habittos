@php($kindLabel = match($formKind) { 'roadmap' => 'roadmap', 'step' => 'etapa', 'topic' => 'tópico', 'session' => 'sessão de estudo', 'material' => 'material' })
<dialog class="app-dialog study-dialog" wire:ignore.self wire:key="study-dialog-{{ $formVersion }}" aria-labelledby="study-dialog-title"
    x-data x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeForm()">
    <div class="dialog-content form-stack">
        <header class="dialog-top"><div><span class="eyebrow">CADA PASSO É APRENDIZADO</span><h2 id="study-dialog-title">{{ $editingId ? 'Editar' : 'Cadastrar' }} {{ $kindLabel }}</h2></div>
            <button type="button" class="icon-close" wire:click="closeForm" aria-label="Fechar" wire:loading.attr="disabled">×</button></header>
        @if ($formSuccess)<div class="notice success" role="status">{{ $formSuccess }}</div>@endif
        <form wire:submit="save" class="form-stack">
            @if ($formKind !== 'session')
                <label>{{ $formKind === 'roadmap' ? 'O que você quer aprender?' : 'Nome do '.$kindLabel }}<input type="text" wire:model="form.title" maxlength="200" required autofocus x-init="$el.focus()"></label>
            @endif
            @if ($formKind === 'roadmap')
                <div class="form-grid"><label>Status<select wire:model="form.status">@foreach (\App\Enums\StudyStatus::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Cor da trilha<input type="color" wire:model="form.color"></label></div>
                <details class="entry-advanced" @if ($errors->has('form.description') || $errors->has('form.due_date')) open @endif><summary><span>Avançado</span><small>Objetivo e prazo</small><span class="advanced-chevron">⌄</span></summary>
                    <div class="entry-advanced-fields form-stack"><x-rich-text-editor model="form.description" label="Objetivo e descrição" editor-id="study-description" :value="$form['description'] ?? ''" :optional="true" :max="100000" />
                        <label>Prazo <small>(opcional)</small><input type="date" wire:model="form.due_date"></label></div></details>
            @elseif ($formKind === 'topic')
                <label>Status<select wire:model="form.status">@foreach (\App\Enums\StudyStatus::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label>De onde continuar <small>(opcional)</small><textarea wire:model="form.checkpoint" rows="2" maxlength="2000" placeholder="Ex.: continuar no exercício 4"></textarea></label>
                <details class="entry-advanced" @if ($errors->has('form.description')) open @endif><summary><span>Avançado</span><small>Anotações e detalhes</small><span class="advanced-chevron">⌄</span></summary>
                    <div class="entry-advanced-fields form-stack"><x-rich-text-editor model="form.description" label="Anotações e detalhes" editor-id="study-topic-description" :value="$form['description'] ?? ''" :optional="true" :max="100000" /></div></details>
            @elseif ($formKind === 'session')
                <label>O que você estudou?<select wire:model.live="form.study_topic_id" required autofocus x-init="$el.focus()"><option value="">Escolha um tópico</option>
                    @foreach ($topicOptions as $option)<option value="{{ $option->id }}">{{ $option->roadmap->title }} · {{ $option->title }}</option>@endforeach</select></label>
                <div class="form-grid"><label>Data<input type="date" wire:model="form.studied_on" max="{{ now(config('app.timezone'))->toDateString() }}" required></label>
                    <label>Duração em minutos <small>(opcional)</small><input type="number" wire:model="form.minutes" min="1" max="1440" placeholder="Ex.: 25"></label></div>
                <label>De onde continuar <small>(opcional)</small><textarea wire:model="form.checkpoint" rows="2" maxlength="2000"></textarea></label>
                <label>O que aprendeu? <small>(opcional)</small><textarea wire:model="form.notes" rows="3" maxlength="10000"></textarea></label>
                <small>A sessão registra seu estudo. Marque o tópico como concluído quando terminar o assunto.</small>
            @elseif ($formKind === 'material')
                <label>Status<select wire:model="form.status">@foreach (\App\Enums\StudyMaterialStatus::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label>Roadmap <small>(opcional)</small><select wire:model.live="form.study_roadmap_id"><option value="">Biblioteca do ambiente</option>@foreach ($roadmapOptions as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach</select></label>
                <label>Tópico <small>(opcional)</small><select wire:model="form.study_topic_id"><option value="">Sem tópico específico</option>@foreach ($formTopicOptions as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach</select></label>
                <label>Link<input type="url" wire:model="form.url" maxlength="4000" placeholder="https://…"></label>
                <label>Ou anexe um arquivo<input type="file" wire:model="upload" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"></label>
                <small>Até 10 MB. PDFs, imagens, documentos, planilhas, apresentações e ZIP. Os arquivos ficam privados no ambiente.</small>
                @if ($editingId)<small>Sem um novo arquivo ou link, o arquivo atual é mantido.</small>@endif
                <span wire:loading wire:target="upload" role="status">Enviando arquivo…</span>
                @error('upload')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @endif
            @foreach ($errors->getMessages() as $field => $messages)
                @if (str_starts_with($field, 'form.'))@foreach ($messages as $text)<p class="field-error" role="alert">{{ $text }}</p>@endforeach@endif
            @endforeach
            <div class="dialog-actions study-form-footer">
                @if ($formKind === 'roadmap' && $editingId)<button type="button" class="text-button danger" wire:click="deleteRecord('roadmap', {{ $editingId }})" data-confirm="O roadmap, suas etapas, tópicos e sessões serão excluídos. Os materiais serão preservados na biblioteca." data-confirm-title="Excluir roadmap?" data-confirm-subject="{{ $form['title'] }}" data-confirm-label="Excluir roadmap" wire:loading.attr="disabled">Excluir roadmap</button>@endif
                <x-create-form-actions save-action="save" another-action="saveAndCreateAnother" :editing="$editingId !== null" cancel-action="closeForm" />
            </div>
        </form>
    </div>
</dialog>
