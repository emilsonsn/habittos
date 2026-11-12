# Rotina diária por semana

A rotina pertence ao ambiente. Membros com acesso ao mesmo ambiente compartilham as atividades e as marcações; não há checks particulares por usuário.

## Uso

A página `/ambientes/{workspace}/rotina` apresenta uma grade de segunda a domingo. Na semana atual, adicione atividades no campo do respectivo dia e marque ou desmarque os checkboxes. As alterações são salvas imediatamente. Cada dia mostra seu total concluído, e a grade mostra o resumo da semana. A grade cresce conforme os itens, sem altura máxima ou rolagem vertical interna: a rolagem vertical é feita pela página. Telas menores permitem rolagem horizontal na grade.

A semana começa na segunda às 00:00 e termina no domingo, no fuso `America/Fortaleza`. A navegação anterior/atual/próxima usa semanas ISO, incluindo a virada do ano e os anos com semana 53. `year` e `week` ficam na URL.

Uma semana nova começa vazia. Na semana atual e nas futuras, quem pode gerenciar a rotina verá **Sincronizar semana anterior** enquanto não houver atividades. Após confirmação, o botão copia os títulos e os dias da semana imediatamente anterior do mesmo ambiente, mantendo a ordem, com todos os checks desmarcados. Não busca semanas mais antigas se a anterior estiver vazia; nesse caso, mostra um aviso. A sincronização não sobrescreve nem duplica uma semana preenchida, mesmo se ela for alterada enquanto o diálogo estiver aberto.

A semana anterior continua disponível para consulta com suas marcações intactas. Semanas passadas são apenas para consulta. Nas futuras, é possível sincronizar enquanto estiverem vazias; adicionar, editar, remover e marcar atividades continua disponível somente na semana atual. Consultar uma semana não cria atividades: semanas não preenchidas permanecem vazias.

Remover uma atividade a exclui da semana atual e preserva o histórico anterior. A remoção exige confirmação. Para editar o texto, clique no lápis à direita da atividade e depois em **Salvar**. **Cancelar** (ou Escape) descarta a edição. O lápis fica acima do botão de remoção. A edição mantém o dia e o check atual, altera somente a atividade da semana atual e preserva as cópias de outras semanas. Ao navegar entre semanas, a edição aberta é descartada.

## Persistência

- `routine_tasks`: definição da atividade por ambiente, dia (`Weekday`), título e datas de criação/encerramento da definição (`starts_on` e `ends_before`). Essas datas não geram atividades automaticamente.
- `routine_activities`: cópia semanal do título e do dia, com início da semana e `completed_at`. A combinação de tarefa e semana é única. Cada atividade sincronizada recebe uma definição própria.

Criação, edição, remoção e sincronização são transacionais e serializadas pelo ambiente. A sincronização verifica novamente se o destino está vazio dentro da transação. Não depende de cron ou de o computador estar ligado na virada da semana. Marcar e desmarcar recebem o estado desejado do checkbox, em vez de inverter o estado no servidor. Todos os IDs são consultados pela relação do ambiente e pela semana selecionada.

## Permissões e limites

- `routine.view`: consultar a rotina e o histórico.
- `routine.manage`: sincronizar semanas atuais ou futuras vazias; adicionar, editar, remover e marcar/desmarcar atividades na semana atual. Também é necessário `routine.view` para acessar a tela.

Título obrigatório, até 200 caracteres, exibido como texto sem HTML. Os dias permitidos vêm de `App\Enums\Weekday`; anos vão de 2000 a 2100. Semanas ISO inexistentes são rejeitadas. Falta de permissão retorna 403 e IDs fora do ambiente ou da semana retornam 404. A troca de ambiente mantém o módulo Rotina diária e passa a exibir somente os registros do ambiente escolhido.

## Validação

```bash
php artisan test --compact tests/Feature/RoutineModuleTest.php
```

A suíte verifica persistência e desmarcação, sincronização com checks zerados, bloqueio de destino preenchido, histórico, semanas não visitadas, remoção, permissões revogadas, isolamento entre ambientes e semanas, validação, semanas ISO na virada do ano e o início da semana no fuso de Fortaleza.
