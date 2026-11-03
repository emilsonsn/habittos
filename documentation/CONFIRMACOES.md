# Confirmações de ações críticas

O componente Blade `x-confirmation-dialog` concentra a aparência, o título, a descrição, a identificação acessível e os tons definidos em `ConfirmationTone`. É responsivo, respeita redução de movimento e usa `<dialog>` nativo para foco, teclado e sobreposição a outros modais.

## Ações simples

`x-action-confirmation` é montado uma única vez no layout. `public/confirmation.js` intercepta botões e formulários com `data-confirm` antes dos handlers Livewire e da submissão HTTP. A ação original só é retomada depois da confirmação. Não há `window.confirm`, avaliação de expressões JavaScript ou inserção de HTML vindo dos nomes dos registros.

```blade
<button type="button" wire:click="deleteNote({{ $note->id }})"
    data-confirm="O conteúdo desta nota será excluído definitivamente."
    data-confirm-title="Excluir nota?"
    data-confirm-label="Excluir nota"
    data-confirm-subject="{{ $note->title }}">
    Excluir nota
</button>
```

- `data-confirm`: consequência da ação, obrigatória.
- `data-confirm-title`: título do diálogo.
- `data-confirm-label`: texto do botão que executa a ação.
- `data-confirm-subject`: nome do registro afetado, opcional.
- `data-confirm-tone`: valor de `ConfirmationTone`, opcional; padrão `Danger`. Use `Warning` para alterações de acesso e ações não destrutivas.

Não combine `data-confirm` com `wire:confirm` no mesmo elemento. Nos formulários, coloque os atributos no `<form>`; o controlador preserva o botão de submissão e a validação nativa. Não os repita no botão de envio.

Cancelar, fechar, pressionar Escape, clicar no backdrop ou navegar não executa a ação. O foco inicial fica em Cancelar; ao fechar, retorna ao elemento que abriu a confirmação. Duplo clique em confirmar não duplica a ação. Elementos removidos da tela antes da confirmação não disparam ações. A delegação permanece funcionando após navegações Livewire.

## Confirmações com campos

A exclusão de ambiente e a sincronização mensal usam o mesmo `x-confirmation-dialog`, com formulário e campos próprios no slot. `close-action` define o fechamento no contexto Alpine do módulo. Os eventos já usados pela sincronização continuam fechando o diálogo.

A exclusão de ambiente mantém a exigência de digitar seu nome e a validação no servidor. A sincronização mantém as datas, a validação de mês vazio e os estados de carregamento. As autorizações dos serviços e componentes continuam sendo verificadas no momento da execução.

## Cobertura

- Financeiro: exclusão de lançamento e sincronização mensal.
- Metas: exclusão, com informação sobre a preservação de metas vinculadas.
- Notas: exclusão de nota e pasta, explicando que notas e subpastas são preservadas.
- Rotina diária: remoção da recorrência, com preservação das semanas anteriores.
- Pessoas e acessos: remoção de acesso, inclusão de membro e alteração de permissões.
- Ambiente: remoção de capa e exclusão definitiva do ambiente.

## Validação

`tests/Frontend/confirmation.test.js` verifica bloqueio anterior à confirmação, retomada única, cancelamentos, navegação, elementos removidos, preservação do botão de envio e atualização dos detalhes. `tests/Feature/ConfirmationDialogTest.php` verifica montagem única no layout, contexto dos registros e preservação da confirmação digitada no servidor. As suítes dos módulos mantêm a cobertura das permissões e das operações em si.
