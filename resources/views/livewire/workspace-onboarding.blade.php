<section class="panel onboarding-panel">
    <span class="eyebrow">SEU PRIMEIRO ESPAÇO</span>
    <h1>Vamos criar seu primeiro ambiente<span class="heading-dot">.</span></h1>
    <p class="muted">Um ambiente reúne suas finanças, metas e planos. Dê um nome a ele para começar e, se quiser, escolha uma capa.</p>
    <form wire:submit="createWorkspace" class="form-stack">
        @include('workspaces.create-fields')
        <p class="muted">As categorias iniciais já vêm prontas. Você poderá personalizar seu ambiente e compartilhar o acesso depois.</p>
        <button class="button" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="createWorkspace">Criar ambiente e começar ↗</span>
            <span wire:loading wire:target="createWorkspace">Criando ambiente…</span>
        </button>
    </form>
    @if ($positioning && $cover && !$errors->has('cover'))
        <x-workspace-cover-editor :image="$cover->temporaryUrl()" editor-id="onboarding-cover-editor" :version="$coverVersion" />
    @endif
</section>
