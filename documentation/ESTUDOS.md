# Estudos por ambiente

O módulo fica abaixo de Metas no sidebar. Roadmaps, etapas, tópicos, sessões, materiais, progresso e indicadores pertencem ao ambiente. A pessoa que registrou uma sessão é identificada no banco, mas o histórico é compartilhado com quem possui acesso ao ambiente.

## Navegação e permissões

- Visão geral: `/ambientes/{workspace}/estudos`.
- Roadmaps: `/ambientes/{workspace}/estudos/roadmaps`; `?roadmap={id}` abre a trilha.
- Biblioteca: `/ambientes/{workspace}/estudos/biblioteca`.
- Histórico: `/ambientes/{workspace}/estudos/historico`.

`studies.view` permite consultar essas páginas e abrir os arquivos. `studies.manage`, junto de `studies.view`, permite cadastrar, editar, excluir, ordenar e registrar estudos. As permissões são verificadas novamente em cada ação. Trocar de ambiente mantém o submenu e retorna à raiz, sem reutilizar IDs de outro ambiente.

## Roadmaps e tópicos

Um roadmap tem nome, cor, status, descrição com formatação e prazo opcional. As etapas organizam seus tópicos. Ao abrir uma trilha, a tela apresenta um quadro visual livre, com fundo pontilhado, etapas conectadas e tópicos compactos. A alternativa em lista mantém a ordenação persistente das etapas e dos tópicos. Arrastar um tópico entre etapas mantém a posição em que ele foi solto, dentro do mesmo roadmap. As alças também aceitam as setas para cima e para baixo para ordenar dentro da lista.

Tópicos têm status, anotações com formatação e um campo curto “De onde continuar”. O progresso do roadmap é calculado pela proporção de tópicos concluídos; sem tópicos, é zero. O status da trilha é uma escolha de planejamento independente desse percentual. O checkbox conclui o tópico; desmarcá-lo retorna a Em andamento.

Os formulários usam `x-create-form-actions`, com Salvar e Salvar e cadastrar novo na criação. Ao continuar, o formulário e o editor são limpos, o contexto é preservado e o foco volta ao primeiro campo. Erros mantêm os dados preenchidos. A ação de cadastrar outro é rejeitada durante uma edição.

## Quadro visual

As etapas podem ser posicionadas livremente pela alça do cabeçalho, com coordenadas salvas no ambiente ao soltar. As setas do teclado, com foco na alça, movem a etapa em 16 unidades; Shift aumenta o passo para 64. A ordem de planejamento dos tópicos continua independente da posição dos cartões.

Arraste o fundo para navegar, use a rolagem para deslocar o quadro e Ctrl/Cmd + rolagem para ampliar ou reduzir em torno do cursor. Os controles também oferecem zoom, enquadramento da trilha e expansão do quadro. Esc cancela uma conexão ou movimento em andamento, fecha os detalhes ou recolhe o quadro expandido. A navegação por pan e zoom funciona para quem possui somente consulta.

“Conectar” permite escolher duas etapas e criar ramificações. As conexões acompanham as posições dos cartões; selecionar uma linha permite removê-la. Não são permitidas conexões entre roadmaps ou ambientes diferentes, nem de uma etapa para ela mesma. Conexões duplicadas são ignoradas. Ciclos são permitidos em um quadro livre.

“Organizar” distribui as etapas pelas ligações, acomoda ramificações e considera a altura de cada cartão. As posições organizadas são salvas. Etapas sem posição salva recebem uma disposição automática. A migration conecta etapas já existentes conforme sua ordem; uma nova etapa recebe inicialmente uma conexão da última etapa da trilha. Essas ligações podem ser alteradas sem mudar tópicos ou registros de estudo.

Selecionar um tópico abre seus detalhes em um painel lateral, com anotações, ponto de continuação, materiais, registro de sessão e edição. Os botões de criar etapas e tópicos permanecem disponíveis. Falhas ao salvar posições restauram a disposição anterior e apresentam uma mensagem; todas as gravações verificam novamente as permissões. Posições de uma organização são salvas atomicamente.

O quadro usa `public/js/study-canvas.js`, Alpine e SVG, sem dependências externas. O estado visual de pan/zoom é local à visita; posições e conexões pertencem ao ambiente. Os testes de geometria e interação ficam em `tests/Frontend/study-canvas.test.js`.

## Sessões de estudo

“Estudei hoje” registra um tópico, a data, a duração opcional (1 a 1.440 minutos), uma observação e o ponto de continuação. A data padrão é hoje em `America/Fortaleza`; datas futuras são rejeitadas. A sessão coloca tópicos e roadmaps ainda não iniciados em andamento, sem concluir o assunto automaticamente.

O histórico mostra cada sessão com o título do tópico no momento do registro. Excluir um tópico ou etapa preserva suas sessões e materiais; o vínculo ao tópico é removido. Excluir um roadmap remove suas etapas, tópicos e sessões, após confirmação; os materiais são preservados na biblioteca do ambiente.

A visão geral mostra sessões e minutos informados na semana de segunda a domingo. “Continuar de onde parei” prioriza a sessão mais recente com tópico e roadmap ainda ativos; na ausência dela, escolhe um tópico disponível. Trilhas pausadas e concluídas ficam fora dessa sugestão.

## Materiais e arquivos

Um material contém um link HTTP/HTTPS ou um arquivo. Pode pertencer à biblioteca geral, a um roadmap ou a um tópico. Seu status é Para ver, Em uso ou Finalizado. A biblioteca pesquisa títulos e filtra por roadmap, tipo e status.

São permitidos arquivos de até 10 MB: PDF, JPEG, PNG, WebP, TXT, CSV, DOC/DOCX, XLS/XLSX, PPT/PPTX e ZIP. O arquivo fica no disco privado `local`, sob `studies/{workspace_id}`, com nome gerado pelo sistema. Não é criado um endereço público de storage. A rota `/ambientes/{workspace}/estudos/arquivos/{material}` verifica ambiente e permissão a cada requisição; `?preview=1` permite visualização de PDF e imagens seguras, e os demais tipos são baixados. As respostas usam `nosniff` e cache privado sem armazenamento.

Editar metadados sem enviar outro arquivo preserva o anexo. Substituir o arquivo ou trocá-lo por um link remove o arquivo anterior após a gravação; falhas removem um eventual upload novo. Excluir o material apaga seu arquivo. Excluir o ambiente pela configuração apaga também seus anexos de estudo.

## Estrutura e validação

- `StudyArea`: interface Livewire e estado dos formulários.
- `ManageStudies`: validação, autorização, gravação e ordenação transacional por ambiente.
- `StudyController`: páginas e entrega autenticada de arquivos.
- `study_roadmaps`, `study_steps`, `study_topics`, `study_sessions`, `study_materials`, `study_connections`: persistência com chaves estrangeiras.
- `StudyModuleTest`: navegação, permissões, isolamento, cadastros consecutivos, sessões, ordenação, arquivos e exclusões.

Anotações e descrições são sanitizadas no servidor pelo editor compartilhado. Revisões agendadas, temporizador e vínculos com metas, rotinas e notas existentes são evoluções futuras; nesta versão, as anotações ficam dentro do tópico e a duração é registrada manualmente.
