# habittos

Monolito Laravel 13 com Livewire 4, Alpine.js, Blade e CSS próprio, em português. Organização financeira em ambientes privados ou compartilhados, com base para outros módulos da vida.

## Executar localmente

Requisitos: PHP 8.3+, Composer, extensões usuais do Laravel e PDO SQLite (ou driver do banco escolhido). Node.js 22+ é opcional, usado apenas para formatar o frontend. A aplicação não depende de CDN, fontes externas ou build JavaScript.

```bash
composer run setup
composer run dev
```

Abra http://localhost:8000 e crie uma conta. O cadastro cria um ambiente privado com categorias iniciais. O banco padrão é SQLite; para usar MySQL ou PostgreSQL, configure `DB_*` no `.env` antes das migrations. Nunca versione `.env` ou o banco local.

Em uma instalação já configurada, use `composer install` e `php artisan migrate`. Gere a chave somente na primeira instalação; trocar `APP_KEY` invalida dados criptografados e sessões existentes.

## O que está disponível

- Cadastro, login, logout, senhas com hash, CSRF, regeneração de sessão e limite de tentativas.
- Criação de ambientes e participação em vários ambientes pela mesma conta.
- Inclusão de contas existentes por e-mail, alteração de permissões e remoção de acesso. Não há envio de convites por e-mail nesta versão.
- Receitas, despesas e investimentos, com criação, edição, exclusão, data, categoria, observações, status de efetivação e marcador de compromisso fixo.
- Dashboard por mês, saldo realizado e projetado, despesas por categoria e acompanhamento semanal.
- Planejamento mensal de reserva e limite semanal, com categorias próprias por ambiente.
- Metas financeiras anuais e mensais, com valor desejado e atualização manual de progresso.
- Interface própria com navegação Livewire, dropdown de ambientes com busca e teclado, criação em modal, estados de carregamento e layout responsivo.
- Configurações do ambiente com alteração do nome e exclusão exclusiva do proprietário mediante confirmação pelo nome.

Os módulos de metas começam com objetivos monetários. Objetivos não financeiros e outros módulos de hábitos podem ser adicionados sem alterar a associação dos dados aos ambientes.

## Acessos

Todos os registros de domínio pertencem a um `Workspace`. O usuário tem acesso por meio da associação `user_workspace`. Não existe ambiente global nem acesso implícito aos ambientes de outra pessoa.

O proprietário tem acesso completo e não pode ser removido ou ter suas permissões reduzidas. Ele pode delegar administração por meio de `members.manage`. Um administrador delegado pode adicionar e gerenciar membros comuns, mas apenas o proprietário pode conceder administração, alterar administradores ou removê-los. Não há transferência de propriedade nesta versão.

| Permissão | Ação |
| --- | --- |
| `finance.view` | Ver dashboard e lançamentos |
| `finance.create` | Criar lançamentos |
| `finance.update` | Editar lançamentos |
| `finance.delete` | Excluir lançamentos |
| `budget.manage` | Alterar planejamento e criar categorias |
| `goals.view` | Ver metas |
| `goals.manage` | Criar metas, atualizar progresso e excluir |
| `members.manage` | Gerenciar acessos de membros comuns |

As permissões são independentes. Para alguém consultar e editar finanças, conceda tanto `finance.view` quanto `finance.update`. Para administrar metas, normalmente conceda `goals.view` e `goals.manage`. As telas ocultam ações não permitidas, e o middleware também bloqueia requisições diretas.

A autorização consulta a associação em cada requisição. Retirar uma permissão tem efeito na próxima requisição, inclusive em sessões já abertas. Um ambiente inacessível retorna 404; um membro sem a permissão necessária recebe 403. IDs de lançamentos, categorias e metas são validados dentro do ambiente da URL.

## Regras financeiras

Valores são armazenados em **centavos inteiros**. Formulários usam números com até duas casas decimais, convertidos sem multiplicação por ponto flutuante. A apresentação usa reais, vírgula decimal e ponto de milhar.

A data de referência determina o mês do lançamento. Não há uma segunda data de liquidação nesta versão: ao marcar um lançamento como efetivado, o saldo realizado muda no mês de referência original.

| Indicador | Cálculo no mês selecionado |
| --- | --- |
| Receitas | Todas as receitas, recebidas ou pendentes |
| Despesas | Todas as despesas, pagas ou pendentes |
| Investimentos | Todos os aportes, efetivados ou pendentes |
| Saldo disponível (indicador principal) | Receitas recebidas − despesas pagas − aportes efetivados − reserva separada |
| Saldo projetado | Todas as receitas − todas as despesas − todos os investimentos − reserva planejada |
| Saídas pendentes | Despesas e investimentos ainda não efetivados |
| Compromissos fixos | Despesas marcadas como fixas |
| Despesas semanais | Despesas variáveis, efetivadas ou pendentes, agrupadas pela data |

O indicador principal é o saldo disponível, que desconta a reserva do resultado efetivado do mês. Receitas pendentes não aumentam esse valor, e saídas pendentes só entram na projeção. A reserva é tratada como dinheiro separado e indisponível para gastos. O cálculo ainda é baseado nos registros do mês, sem conciliação bancária ou acumulação entre meses. Não há contas bancárias nem saldo inicial nesta versão. A reserva é descontada do saldo disponível e da projeção, mas não cria um lançamento de investimento automaticamente. Para evitar contar o mesmo dinheiro duas vezes, registre como reserva apenas o valor que ainda será separado além dos aportes já lançados.

O ciclo sempre tem quatro faixas semanais contadas a partir do início configurado: as três primeiras têm sete dias e a quarta cobre o restante. Ciclos de transição com menos de 21 dias usam faixas menores, sem sobreposição; faixas sem dias são identificadas no gráfico. O limite mensal é o orçamento para gastos variáveis: todas as receitas menos despesas fixas, aportes e reserva planejada, incluindo registros pendentes. O limite por semana é esse orçamento dividido por 4 e não diminui com os gastos variáveis. O limite ajustado desconta também todas as despesas variáveis e divide o saldo restante pelas semanas restantes, incluindo a atual; os gastos da semana vigente já estão descontados e não são subtraídos novamente. Os dois limites ficam juntos no rodapé do gráfico. As divisões são feitas em centavos e arredondadas para baixo; saldos negativos resultam em limite zero. Ciclos futuros começam com quatro semanas disponíveis e ciclos encerrados mostram “Mês encerrado” no limite ajustado. A semana vigente considera as datas do ciclo, inclusive quando ele atravessa o mês do calendário. O saldo acumulado investido não é subtraído, pois é patrimônio, não um aporte do mês.

Metas têm progresso manual, independente dos lançamentos. A meta pode ultrapassar 100%; a barra visual termina em 100%, mas o valor integral fica registrado. A criação de uma meta mensal exige o mês. Os gastos fixos são registros independentes por mês. Ao navegar para um mês pela primeira vez, o sistema copia as receitas e despesas fixas do mês imediatamente anterior, preservando descrição, valor, categoria e observações. As cópias começam pendentes; o vencimento avança um mês do calendário, sem ultrapassar seu último dia (por exemplo, 31 de janeiro vira 28 ou 29 de fevereiro). Se ficar fora do ciclo de destino, é limitado ao primeiro ou último dia desse ciclo. A alteração ou exclusão de uma cópia não modifica os demais meses.

A inicialização é transacional e marcada por uma chave única de ambiente e mês. Reabrir o mês não duplica nem recria gastos excluídos. Alterações posteriores na origem não são propagadas a meses já abertos. Se o mês imediatamente anterior não possuir receitas ou despesas fixas, nada é copiado; o sistema não preenche lacunas nem busca meses mais antigos. Meses que já possuíam dados antes desta funcionalidade são preservados pela migration.

A navegação oferece mês/ano anterior e seguinte, além do seletor de mês e ano. Para quem pode criar lançamentos, a abertura acontece por POST com CSRF; consultas GET e usuários com apenas leitura nunca criam registros. O primeiro lançamento em um mês também inicializa seus fixos antes de salvar. Um acesso direto por URL GET é apenas consulta; use “Ver mês” para inicializar um mês ainda não aberto. Orçamentos e reservas também são independentes por mês e não são copiados automaticamente.

## Início do mês financeiro

No bloco do mês de referência, o lápis ao lado do período abre um seletor de data. O usuário escolhe manualmente o início daquele mês, dentro do respectivo mês do calendário. O padrão é sempre dia 01, independente das escolhas anteriores. Não há cálculo de dia útil ou feriados. A configuração pertence ao ambiente e mês, e exige `budget.manage` também no servidor.

O ciclo inclui a data escolhida e termina na véspera do início do próximo mês. Por exemplo: outubro começa em 05/10 e novembro em 03/11; o ciclo de outubro cobre 05/10 a 02/11. Enquanto novembro não for configurado, começa em 01/11 e outubro termina em 31/10. Alterar um início também redefine o fim do ciclo anterior; os intervalos são consecutivos, sem lacunas ou sobreposição. O aviso no formulário deixa esse efeito explícito.

Lançamentos, totais, categorias, fixos, gráficos e limites seguem o ciclo. As datas dos lançamentos existentes são preservadas; apenas o período em que aparecem muda. Reservas e saldo investido continuam vinculados ao mês de referência. Metas anuais e mensais mantêm os seus próprios períodos. Ao abrir o financeiro sem escolher mês ou ao salvar um lançamento, a navegação identifica o ciclo correspondente à data. A cópia de fixos consulta o ciclo anterior e mantém sua inicialização única por ambiente e mês, sem reinicializar meses já abertos ao mudar datas.

## Referência da planilha

Foi analisada a estrutura das 12 abas mensais da planilha fornecida, iniciando em outubro de 2026, com leitura detalhada do primeiro mês. Os dados pessoais não foram copiados para código, seeds ou banco do sistema.

| Organização original | Implementação inicial |
| --- | --- |
| Renda base e extra | Lançamentos de receita com descrição livre |
| Compromissos fixos, pago e observação | Despesas com marcador de fixo, status e observações |
| Distribuição mensal e reserva | Planejamento mensal de reserva |
| Disponível e gasto por semana | Limite semanal e despesas por faixa de dias |
| Categorias nas colunas | Categorias configuráveis por ambiente |
| Total investido | Lançamentos de investimento |
| Saldo final | Saldo projetado com regras explícitas acima |
| Valores e anotações de cartão | Sem submódulo de cartões nesta etapa |

A planilha contém registros sem data e anotações de cartão que misturam limites, gastos e ajustes. Por isso esta etapa não faz uma importação automática nem presume datas ou saldos dessas anotações.

## Organização do código

- `routes/web.php`: rotas, autenticação e permissões por operação.
- `app/Enums/Permission.php`: catálogo central de permissões.
- `app/Http/Middleware/WorkspaceAccess.php`: isolamento e autorização de ambiente.
- `app/Http/Controllers`: fluxos web de autenticação, ambientes, financeiro e metas.
- `app/Http/Requests/EntryRequest.php`: validação e preparação dos lançamentos.
- `app/Services/CreateWorkspace.php`: criação transacional do ambiente e das categorias.
- `app/Services/OpenFinancialMonth.php`: cópia transacional e idempotente dos gastos fixos entre meses.
- `app/Services/FinancialSummary.php`: cálculo dos indicadores financeiros.
- `app/Services/Money.php`: conversão e apresentação de valores.
- `app/Models`: modelos Eloquent e relacionamentos.
- `app/Livewire`: componentes de ambiente, configurações, dashboard financeiro, formulário de lançamentos, metas e pessoas.
- `resources/views/livewire`: views dos componentes. `resources/views/layout.blade.php`: estrutura da aplicação.
- `public/css/app.css`: fundamentos de layout; `public/css/interface.css`: identidade visual e componentes; `public/app.js`: comportamento global mínimo.
- `app/Services/SelectWorkspace.php`, `SaveEntry.php` e `EntryRules.php`: seleção, persistência e validação compartilhadas pelos componentes e endpoints HTTP.
- `database/migrations`: estrutura e restrições do banco.
- `tests/Feature/HabittosTest.php`: testes de segurança, fluxos e cálculos.

Para um novo módulo, vincule seus registros a `workspace_id`, acrescente as permissões ao enum, proteja suas rotas e consulte os registros pela relação do ambiente. Evite buscar um registro globalmente por ID antes de verificar seu ambiente. As rotas web permanecem com proteção CSRF padrão do Laravel.

## Testes e formatação

```bash
composer test
vendor/bin/pint --test
npm ci
npm run format:check
```

Para aplicar formatação: `vendor/bin/pint` e `npm run format`. CSS e JavaScript usam Prettier; os templates Blade usam indentação de quatro espaços; PHP segue o preset Laravel do Pint.

Os testes usam SQLite em memória e não alteram o banco local. Cobrem cadastro, autenticação, isolamento de ambientes e registros, permissões de leitura/escrita, revogação de acesso, proteção do proprietário, restrições de administração, validação de valores, CRUD, metas e cálculo financeiro incluindo o último dia do mês.

## Dados fictícios opcionais

```bash
php artisan db:seed --class=DemoSeeder
```

Disponível apenas com `APP_ENV=local`. Cria `demo@habittos.test` com uma senha aleatória exibida uma vez no terminal, um ambiente e registros fictícios no mês atual. Não substitui nem copia os dados da planilha. Se a conta já existir, o comando não altera nada. O seeder padrão não cria dados.

## Limites desta primeira etapa e evolução

Ainda não há importação XLSX, recorrências além da cópia mensal de fixos, parcelas, cartões/faturas, transferências, contas bancárias, exportação, trilha de auditoria, convite por e-mail, verificação de e-mail ou recuperação de senha. Metas não financeiras e hábitos também ficam para a evolução dos módulos.

A exclusão de lançamentos e metas é definitiva após a confirmação da interface. A exclusão de um ambiente exige que o proprietário digite o nome exato na área de configurações e remove os dados vinculados por cascata. As contas dos participantes e os outros ambientes permanecem. Não há exclusão de contas nesta versão. Para produção, configure HTTPS, banco persistente, backups com restauração testada, `APP_DEBUG=false`, credenciais de ambiente próprias e políticas de sessão adequadas. A aplicação foi validada localmente; não foi publicada nem integrada a bancos ou provedores financeiros.

## Interface e interações

A navegação entre páginas usa `wire:navigate`. O framework atualiza o conteúdo e a URL sem um carregamento completo. Livewire entrega Alpine.js junto de seus assets locais; não adicione outra instância de Alpine nem uma dependência de CDN. Não há Bootstrap no projeto.

A identidade visual usa índigo como cor principal, navegação em azul profundo e superfícies claras. Os estilos estão separados entre fundamentos (`app.css`) e identidade/componentes (`interface.css`). O carregamento de ações, as mensagens de resultado e os estados vazios fazem parte dos componentes.

### Ambientes

O antigo menu/listagem de ambientes foi removido da navegação. O dropdown da barra lateral permite buscar e trocar ambientes, exibe a seleção atual e funciona por teclado. O botão + ao lado abre o modal de criação. Criar um ambiente já abre seu financeiro. A página inicial oferece orientação sem duplicar a lista de ambientes.

A troca lista somente ambientes autorizados e revalida a participação no servidor. Ela preserva o módulo atual e o mês/ano do financeiro, ou abre outro módulo permitido no destino. Se nenhum estiver liberado, retorna ao início. A seleção fica na sessão; abrir uma URL autorizada atualiza o ambiente ativo. Acesso removido provoca retorno a um ambiente disponível. Usuários com somente leitura não inicializam meses ao trocar.

### Financeiro

O dashboard é um componente Livewire: navegação de mês/ano, planejamento, criação de categorias, exclusão e alternância de status atualizam os dados na mesma página. O período aparece na URL e é informado ao seletor de ambientes. Receitas e despesas fixas mantêm a inicialização idempotente e a independência entre meses.

O formulário de lançamento usa Livewire, validação de campos, estado de salvamento e seleção visual entre receita, despesa e investimento. A validação e a persistência são compartilhadas com os endpoints HTTP existentes. Na criação, “Salvar” volta ao financeiro; “Salvar e lançar outro” confirma o registro na própria tela, limpa todos os campos para os padrões de criação e devolve o foco à descrição. Erros de validação preservam os valores preenchidos. A edição mantém apenas o salvamento com retorno. Não há gravação automática de rascunhos.

Metas e pessoas também usam componentes Livewire. Criar metas, atualizar progresso, excluir metas, conceder acesso e editar permissões atualizam as telas sem carregamento completo. As ações de domínio são compartilhadas com os endpoints HTTP tradicionais para manter uma única regra de negócio.

### Configurações e exclusão

Configurações mostra os dados do ambiente. Somente o proprietário pode renomear ou excluir, inclusive nas ações Livewire chamadas diretamente. O modal de exclusão explica os dados que serão removidos e exige o nome exato. Exclusão é definitiva e transacional; não execute em dados reais para testar a interface.

Cada componente revalida autenticação, participação e permissão em suas ações. IDs de ambiente e registros são propriedades `#[Locked]`; parâmetros recebidos em ações sempre passam por consultas limitadas ao ambiente. Isso é necessário porque o endpoint de atualização Livewire é diferente da rota inicial da página.

## Receitas fixas, despesas fixas e saldo investido

O financeiro mostra receitas fixas e gastos fixos em seções de largura inteira, uma abaixo da outra, com totais e status separados. As listas têm altura máxima de 360 px e rolagem interna, mantendo os títulos e totais visíveis. A área de rolagem também pode ser acessada pelo teclado. Para registrar salário, escolha Receita e marque “Lançamento fixo mensal”. A abertura do próximo mês copia receitas e despesas fixas uma única vez como pendentes, preservando os meses anteriores. Investimentos não são copiados pelo marcador de fixo.

O planejamento mensal também aceita o saldo acumulado investido. Esse valor é um retrato do patrimônio informado para o mês, separado dos aportes. Quando informado, o cartão de investimentos destaca o saldo acumulado e identifica os aportes do mês. O saldo acumulado nunca é subtraído do saldo realizado ou projetado; apenas lançamentos de aporte representam saídas. Ele não é copiado automaticamente aos meses seguintes.

Uma carga pontual da planilha pode usar datas provisórias quando a origem não possui datas completas. Nesses casos, a origem e o critério ficam nas observações de cada lançamento. Valores consolidados e seus detalhes não devem ser importados como saídas simultaneamente. Relatórios e cópias privadas de segurança ficam em `storage/app/private/imports`, ignorado pelo Git. Não há importador XLSX genérico na interface.

## Indicadores de planejamento

A seção “Planos para o mês” mostra limite mensal, reserva planejada para investimento e saldo acumulado investido. O lápis abre a edição de um único indicador, com Salvar e Cancelar; Escape cancela. A ação grava apenas aquele valor no ambiente e mês selecionados. Trocar de período descarta a edição ainda não salva. A permissão `budget.manage` é exigida no servidor e controla a exibição do lápis.

Os limites mensal e semanal são automáticos e não possuem edição manual, inclusive no servidor. Os antigos valores cadastrados permanecem no banco para preservar dados históricos, mas não participam dos cálculos. A reserva permanece editável e descontada do saldo disponível; o saldo acumulado investido permanece editável e não é tratado como aporte.

## Formulário simplificado e valores monetários

Novo lançamento mostra descrição, valor e categoria. O painel “Avançado” reúne tipo, data, pagamento, recorrência e observações. Os padrões de criação são despesa, data de hoje no fuso da aplicação, pago, não fixo e observações nulas. Abrir o formulário a partir de outro mês não altera a data de hoje. A edição preserva os dados já existentes; observações vazias ou compostas apenas de espaços são salvas como null. Erros nos campos avançados abrem o painel para correção.

Todos os campos monetários usam `x-money-input`: lançamentos, metas/progresso e indicadores do planejamento. A tela apresenta separador de milhar e vírgula decimal, enquanto o estado Livewire permanece decimal com ponto. O componente usa Alpine, suporta colar moeda com R$, preserva centavos e aplica a formatação completa ao sair do campo. O backend continua validando limites e precisão antes de converter para centavos inteiros. Os scripts locais de máscara são carregados antes do Livewire registrar o Alpine.

Execute `npm test` para verificar a máscara, além de `composer test` para os fluxos e regras de persistência.
