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
| Saldo realizado | Receitas efetivadas − despesas efetivadas − investimentos efetivados |
| Saldo projetado | Todas as receitas − todas as despesas − todos os investimentos − reserva planejada |
| Saídas pendentes | Despesas e investimentos ainda não efetivados |
| Compromissos fixos | Despesas marcadas como fixas |
| Despesas semanais | Despesas variáveis, efetivadas ou pendentes, agrupadas pela data |

O saldo realizado representa **o movimento líquido do mês**, não um saldo bancário conciliado ou acumulado. Não há contas bancárias nem saldo inicial nesta versão. A reserva planejada não é uma saída realizada e não é criada como investimento automaticamente. Para evitar contar o mesmo dinheiro duas vezes, registre como reserva apenas o valor que ainda será separado além dos aportes já lançados.

As faixas semanais são dias 1–7, 8–14, 15–21, 22–28 e 29 até o fim do mês. Fevereiro pode ter quatro ou cinco faixas. A planilha dividia o mês em quatro blocos; o sistema cobre todos os dias explicitamente. O limite semanal é informado por mês e aplicado a cada faixa, sem desconto automático do saldo projetado, porque não representa uma despesa.

Metas têm progresso manual, independente dos lançamentos. A meta pode ultrapassar 100%; a barra visual termina em 100%, mas o valor integral fica registrado. A criação de uma meta mensal exige o mês. Os gastos fixos são registros independentes por mês. Ao navegar para um mês pela primeira vez, o sistema copia as despesas fixas do mês imediatamente anterior, preservando descrição, valor, categoria e observações. As cópias começam pendentes; o vencimento mantém o dia quando possível ou usa o último dia do mês (por exemplo, 31 de janeiro vira 28 ou 29 de fevereiro). A alteração ou exclusão de uma cópia não modifica os demais meses.

A inicialização é transacional e marcada por uma chave única de ambiente e mês. Reabrir o mês não duplica nem recria gastos excluídos. Alterações posteriores na origem não são propagadas a meses já abertos. Se o mês imediatamente anterior não possuir gastos fixos, nada é copiado; o sistema não preenche lacunas nem busca meses mais antigos. Meses que já possuíam dados antes desta funcionalidade são preservados pela migration.

A navegação oferece mês/ano anterior e seguinte, além do seletor de mês e ano. Para quem pode criar lançamentos, a abertura acontece por POST com CSRF; consultas GET e usuários com apenas leitura nunca criam registros. O primeiro lançamento em um mês também inicializa seus fixos antes de salvar. Um acesso direto por URL GET é apenas consulta; use “Ver mês” para inicializar um mês ainda não aberto. Orçamentos e reservas também são independentes por mês e não são copiados automaticamente.

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

O dashboard é um componente Livewire: navegação de mês/ano, planejamento, criação de categorias, exclusão e alternância de status atualizam os dados na mesma página. O período aparece na URL e é informado ao seletor de ambientes. Gastos fixos mantêm a inicialização idempotente e a independência entre meses.

O formulário de lançamento usa Livewire, validação de campos, estado de salvamento e seleção visual entre receita, despesa e investimento. A validação e a persistência são compartilhadas com os endpoints HTTP existentes. Não há gravação automática de rascunhos.

Metas e pessoas também usam componentes Livewire. Criar metas, atualizar progresso, excluir metas, conceder acesso e editar permissões atualizam as telas sem carregamento completo. As ações de domínio são compartilhadas com os endpoints HTTP tradicionais para manter uma única regra de negócio.

### Configurações e exclusão

Configurações mostra os dados do ambiente. Somente o proprietário pode renomear ou excluir, inclusive nas ações Livewire chamadas diretamente. O modal de exclusão explica os dados que serão removidos e exige o nome exato. Exclusão é definitiva e transacional; não execute em dados reais para testar a interface.

Cada componente revalida autenticação, participação e permissão em suas ações. IDs de ambiente e registros são propriedades `#[Locked]`; parâmetros recebidos em ações sempre passam por consultas limitadas ao ambiente. Isso é necessário porque o endpoint de atualização Livewire é diferente da rota inicial da página.

## Valores monetários

Todos os campos monetários usam `x-money-input`: lançamentos e metas/progresso. A tela apresenta separador de milhar e vírgula decimal, enquanto o estado Livewire permanece decimal com ponto. O componente usa Alpine, suporta colar moeda com R$, preserva centavos e aplica a formatação completa ao sair do campo. O backend continua validando limites e precisão antes de converter para centavos inteiros. Os scripts locais de máscara são carregados antes do Livewire registrar o Alpine.

Execute `npm test` para verificar a máscara, além de `composer test` para os fluxos e regras de persistência.
