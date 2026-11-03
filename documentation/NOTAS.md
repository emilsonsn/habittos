# Notas por ambiente

O módulo organiza notas em pastas (categorias) e subpastas. Notas, pastas e favoritos pertencem ao ambiente. Nenhum desses registros é particular de um usuário: quem acessa o mesmo ambiente, com as permissões necessárias, vê os mesmos dados. Trocar de ambiente troca o conjunto de notas, pastas, favoritos e indicadores. As pastas de notas são independentes das categorias financeiras e das metas.

## Páginas

O menu **Notas** possui os submenus:

- **Dashboard**: `/ambientes/{workspace}/notas`. Mostra os totais de notas, pastas, favoritos do ambiente e notas sem pasta, as pastas iniciais e as seis notas atualizadas mais recentemente.
- **Favoritos**: `/ambientes/{workspace}/notas/favoritos`. Lista as notas marcadas com estrela naquele ambiente.
- **Notas**: `/ambientes/{workspace}/notas/lista`. Mostra as pastas e notas da página inicial. `?category={id}` abre uma pasta e mostra suas subpastas e notas diretas.

A navegação por pastas mostra o caminho completo, com links para os níveis anteriores. O estado da pasta e da busca fica na URL. A busca encontra título ou texto das notas em todo o ambiente, mesmo quando iniciada dentro de uma pasta; na página de favoritos, pesquisa apenas os favoritos. As listas têm paginação de 12 notas. A ordenação é pela atualização mais recente, com desempate por ID. A troca de ambiente mantém o submenu aberto e retorna à raiz, sem reutilizar IDs de pastas do ambiente anterior.

## Campos e edição

O formulário de nota contém somente:

| Campo | Contrato | Regra |
| --- | --- | --- |
| Título | `title` | Obrigatório, até 200 caracteres, sem espaços nas extremidades |
| Texto | `text` | Opcional, até 100.000 caracteres incluindo a formatação |
| Pasta | `category_id` | Opcional; deve pertencer ao mesmo ambiente |

Ao criar uma nota dentro de uma pasta, essa pasta vem selecionada automaticamente. O usuário pode selecionar outra pasta ou deixar a nota sem pasta. Notas criadas no dashboard ou nos favoritos começam sem pasta. O texto usa o mesmo editor das metas, com negrito, itálico, sublinhado, listas e citação; o servidor preserva apenas o HTML permitido e remove atributos, scripts e conteúdo incorporado. Colar insere texto simples, que pode ser formatado pelo editor.

Criação, consulta e edição usam diálogos Livewire. Cancelar ou pressionar Escape descarta alterações. O salvamento fecha o diálogo e atualiza a listagem. Uma nota também pode ser movida alterando a pasta no formulário. Excluir uma nota exige confirmação e remove também sua marcação de favorito.

## Pastas e hierarquia

Uma pasta tem somente nome (até 100 caracteres) e pasta superior opcional. Pode conter simultaneamente notas e outras pastas. A criação de uma subpasta usa a pasta aberta como superior por padrão. A edição permite renomear e mover a pasta.

O servidor rejeita vínculos com a própria pasta, com qualquer descendente e entre ambientes. A exclusão de uma pasta exige confirmação e preserva suas notas e subpastas diretas, movendo-as para a pasta superior; se não houver superior, vão para a página inicial. Os demais níveis da hierarquia são mantidos.

## Favoritos e permissões

A estrela corresponde a `notes.is_favorite`, atributo compartilhado da nota do ambiente. Não existe favorito por usuário. Marcar ou desmarcar afeta o que todos os membros daquele ambiente veem. Editar o texto ou mover uma nota preserva sua estrela.

- `notes.view`: permite abrir as três páginas, navegar, pesquisar e ler o texto formatado.
- `notes.manage`, junto de `notes.view`: permite criar, editar, mover e excluir notas e pastas, além de marcar e desmarcar favoritos.

O proprietário tem acesso completo. As novas permissões aparecem em Pessoas e acessos para concessão aos demais membros. Todas as ações Livewire revalidam autenticação, participação no ambiente e permissões. Os IDs são consultados dentro do ambiente autorizado, inclusive os enviados pelo formulário, pela URL e pelas ações de favorito. O menu usa a mesma expansão exclusiva de Financeiro e Metas.

## Persistência e validação

A migration `2026_11_01_000002_create_notes_module` cria `note_categories` e `notes`, sempre com `workspace_id`. A exclusão do ambiente remove os registros vinculados. Escritas, mudanças de hierarquia e favoritos usam transações e bloqueio do ambiente para serializar operações concorrentes. O serviço `ManageNotes` concentra as regras; os modelos não permitem preencher `workspace_id` ou `is_favorite` pelo formulário de nota.

As três rotas HTTP são de consulta. As mutações usam os métodos do componente `NotesLibrary`, com a proteção de sessão e CSRF do Livewire. Falta de permissão retorna 403; registros de outro ambiente são tratados como inexistentes (404). Erros dos campos ficam no diálogo sem descartar o conteúdo digitado.

O editor e a sanitização são compartilhados em `rich-text-editor`, `rich-text.js` e `RichText`. Os testes em `NotesModuleTest` cobrem a hierarquia, o vínculo automático, favoritos compartilhados, isolamento entre ambientes, permissões, validação, texto formatado e preservação de conteúdo ao excluir pastas.
