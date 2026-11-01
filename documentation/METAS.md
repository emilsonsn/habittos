# Metas pessoais

Metas pertencem a um ambiente e não têm vínculo automático com receitas, despesas ou investimentos. As categorias são próprias das metas, com as opções Saúde, Profissional e Vida pessoal e as demais já existentes no ambiente.

## Páginas e navegação no tempo

O menu **Metas** contém quatro páginas:

- **Dashboard**: `/ambientes/{workspace}/metas`.
- **Metas anuais**: `/ambientes/{workspace}/metas/anuais`.
- **Metas mensais**: `/ambientes/{workspace}/metas/mensais`.
- **Metas semanais**: `/ambientes/{workspace}/metas/semanais`.

Anuais navegam entre anos, mensais entre meses e semanais entre semanas ISO (segunda a domingo). Os controles anterior/próximo atravessam a virada do ano; “Ano atual”, “Mês atual” e “Semana atual” retornam ao período da data de hoje em America/Fortaleza. O tempo selecionado fica na URL (`year`, `month` ou `week`).

A criação usa o período aberto na página. O formulário não exibe nem permite alterar período, ano, mês ou semana. O backend define esses valores a partir do contexto da página, inclusive quando o estado enviado pelo formulário é modificado. A edição também mantém o contexto temporal da meta.

## Tabela, kanban e popup

Cada página de metas alterna entre tabela e kanban, preservando os filtros. A ordenação padrão é por prioridade: Alta, Média e Baixa, tanto na tabela quanto dentro de cada coluna do kanban. Em caso de empate, o prazo mais próximo vem primeiro, seguido das metas sem prazo e, por fim, do ID para manter uma ordem estável.

A **tabela** exibe, nesta ordem, Nome da meta, Status, Prioridade, Progresso e Prazo. Possui paginação de 15 registros e rolagem horizontal em telas menores. Clicar no nome da meta abre seus dados no popup.

O **kanban** possui as colunas Não iniciado, Em andamento, Concluído e Não realizado, nesta ordem. Cada cartão exibe apenas prioridade, categoria, título e percentual. Arrastar e soltar entre colunas altera o status e preserva o progresso. O comportamento usa eventos de ponteiro para mouse e toque; Alt com as setas esquerda/direita é a alternativa por teclado. Clique ou Enter abre o popup preenchido. Soltar fora das colunas cancela o movimento.

O **popup** mostra, inicialmente, Nome da meta, Descrição, Status, Prioridade e Categoria (select). A seção **Avançado** contém Prazo, Feito (%) e vínculo opcional com uma meta maior. A exclusão fica no popup de edição, com confirmação. Cancelar ou pressionar Escape não grava alterações. Quem só tem permissão de consulta pode abrir os detalhes, com os campos desabilitados.

Filtros de busca por nome, status, prioridade, categoria e intervalo inclusivo de prazo são os mesmos nas duas visualizações e ficam na URL. O painel começa recolhido nas páginas anuais, mensais e semanais e pode ser aberto pelo botão Mostrar filtros, ao lado da contagem de metas. O botão informa a quantidade de filtros ativos; ocultar o painel preserva os filtros aplicados. Limpar filtros preserva o período aberto. Um intervalo de prazo inválido mostra erro e nenhum resultado até a correção.

## Campos e regras

| Campo | Contrato | Regra |
| --- | --- | --- |
| Nome da meta | `title` | Obrigatório, até 200 caracteres |
| Descrição | `description` | Opcional; HTML formatado com até 20.000 caracteres, incluindo marcação |
| Prioridade | `priority` | `high` (Alta), `medium` (Média), `low` (Baixa) |
| Progresso | derivado de `progress_percent` | Mesmo percentual de Feito (%), exibido com barra |
| Categoria | `category` | Opcional, até 80 caracteres; independente das categorias financeiras |
| Status | `status` | `not_started` (Não iniciado), `in_progress` (Em andamento), `completed` (Concluído), `not_done` (Não realizado); novas metas começam como Não iniciado |
| Prazo | `due_date` | Data opcional `YYYY-MM-DD` |
| Feito (%) | `progress_percent` | 0 a 100, até duas casas decimais; começa em zero |
| Meta maior | `parent_id` | Vínculo opcional com uma meta de período maior do mesmo ambiente |

Toda meta tem escala de 0 a 100%. Status e progresso são independentes, conforme os dados exportados: uma meta concluída pode ter progresso menor que 100. Não há soma automática de progresso entre metas vinculadas.

O tempo (`period`, `year`, `month`, `week`) pertence ao contexto de navegação, não aos campos editáveis do popup. O ano varia de 2000 a 2100; semanas são validadas conforme o ano ISO. O prazo é independente do tempo de planejamento: uma meta anual de 2026 pode ter prazo em 2027.

Metas são independentes por padrão. Mensais podem se vincular a uma anual; semanais podem se vincular a uma anual ou mensal. Não são permitidos ciclos, vínculo com a própria meta nem vínculos entre ambientes. Excluir uma meta preserva as menores e remove apenas o vínculo.

## Dashboard

O dashboard agrega somente o ano e ambiente selecionados: quantidade de metas, concluídas, em andamento, progresso médio, resumo por período, próximos prazos, prazos vencidos, categorias e status. Cada meta conta uma vez, mesmo quando vinculada a outra. A grade mantém os painéis alinhados, com gráfico de rosca por status (quantidade, percentual e detalhes ao passar o mouse), barras de progresso médio por categoria e barras empilhadas de status por prioridade. Os resumos anuais, mensais e semanais mostram quantidade, progresso médio e status. As listas de prazos usam rolagem interna; seus links preservam o mês ou a semana da meta. Em telas menores, os painéis se reorganizam em uma coluna.

## Permissões e endpoints

`goals.view` permite consultar as quatro páginas, detalhes e filtros. `goals.manage` permite criar, editar, excluir e mover no kanban. As ações verificam novamente autenticação, participação no ambiente e permissões, inclusive em atualizações Livewire. Os IDs são sempre consultados dentro do ambiente autorizado.

Os endpoints HTTP usam a mesma camada de domínio:

- `POST /ambientes/{workspace}/metas`: cria uma meta.
- `PUT /ambientes/{workspace}/metas/{goal}`: edita uma meta com os campos do contrato.
- `DELETE /ambientes/{workspace}/metas/{goal}`: exclui uma meta.

Esses endpoints recebem os campos de planejamento e contexto temporal, redirecionando de volta com mensagem de resultado. Erros de validação retornam com os erros, IDs inacessíveis retornam 404 e falta de permissão retorna 403. O contrato financeiro anterior foi removido.

## Importação do Notion

Use o CSV completo (`_all.csv`) para incluir também as metas que não aparecem na visualização filtrada:

```bash
php artisan goals:import-notion '/caminho/Metas_all.csv' --workspace=1 --year=2026
```

O comando administrativo local exige ambiente e ano explícitos e executa as regras de domínio como proprietário do ambiente. Cada linha vira uma meta anual, incluindo concluídas e não realizadas. Nome, prioridade, categoria, status, prazo e Feito (%) são preservados. Progresso é apresentado a partir de Feito (%). Colunas extras do arquivo são ignoradas, e o arquivo original não é copiado para o código-fonte.

A importação é transacional: uma linha inválida desfaz a operação inteira. Uma chave formada por origem Notion, período anual, ano e nome normalizado evita duplicar uma reimportação no mesmo ambiente. Reimportar atualiza as metas importadas correspondentes; outros anos e ambientes são independentes.

## Substituição da estrutura anterior

A migration de reconstrução apaga a tabela financeira antiga de metas e cria a estrutura de planejamento pessoal. Ela não altera lançamentos, orçamentos ou outros dados financeiros. Os registros financeiros antigos de metas não são convertidos. Antes de aplicar em outra instalação com dados existentes, faça uma cópia da tabela antiga. O rollback recria o esquema antigo, mas não recupera registros apagados; a recuperação depende do backup. As migrations seguintes removem colunas transitórias de instalações que receberam a primeira versão do novo módulo.

## Descrição formatada

A descrição aparece no popup de criação, edição e consulta. O editor oferece negrito, itálico, sublinhado, listas com marcadores, listas numeradas e citação. A formatação é preservada ao salvar e reabrir; cancelar descarta alterações. Colar insere texto simples, que pode ser formatado no editor.

O servidor aceita apenas parágrafos, quebras de linha, divisões, negrito, itálico, sublinhado, tachado, listas e citações, removendo atributos, scripts, imagens, links e conteúdo incorporado. Descrições vazias são salvas como `null`. Quem possui apenas `goals.view` vê o texto formatado sem editor. A importação do Notion preserva descrições já cadastradas. A migration `2026_11_01_000001_add_description_to_goals` adiciona o campo sem alterar as metas existentes.
