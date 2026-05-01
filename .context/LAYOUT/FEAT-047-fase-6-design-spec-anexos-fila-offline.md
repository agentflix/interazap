# FEAT-047 Fase 6 - Design Spec (TASK-047.12 e TASK-047.13)

## Metadados

- Feature: FEAT-047 mobile app capacitor
- Fase: 6 (anexos + fila offline)
- Data: 2026-04-27
- Autor: DESIGNER
- Status: pronto para handoff ao FRONTEND

## Gap de contexto registrado

- Arquivo esperado e nao encontrado: `.claude/skills/senior-cognition/SKILL.md`
- Impacto: sem bloqueio para o design spec desta fase
- Mitigacao aplicada: execucao com as skills disponiveis e obrigatorias lidas (`design`, `frontend-flow`, `angular-architect`, `coding-guidelines`)

## Objetivo de UX da fase

- TASK-047.12: fluxo de anexos no chat mobile com menu claro (Tirar foto, Galeria, Arquivo), fallback seguro e mensagens de permissao negada.
- TASK-047.13: experiencia de fila offline com status visivel por mensagem, feedback de reconexao e flush FIFO perceptivel.

## Inventario de componentes reutilizaveis (shared/components)

| Necessidade UX                 | Componente existente                           | Caminho                                                                                  | Observacao de uso                                                  |
| ------------------------------ | ---------------------------------------------- | ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| Acao principal/acao secundaria | `AfButtonComponent`                            | `app/src/app/shared/components/button/`                                                  | Base para CTA de retry, reenviar e confirmar envio                 |
| Acao iconica no composer       | `AfIconButtonComponent`                        | `app/src/app/shared/components/icon-button/`                                             | Trigger do menu de anexos e acoes compactas                        |
| Menu mobile de anexos          | `AfSheetComponent`                             | `app/src/app/shared/components/sheet/`                                                   | Melhor padrao para mobile que dropdown flutuante                   |
| Menu alternativo desktop/web   | `AfDropdownMenuComponent`                      | `app/src/app/shared/components/dropdown-menu/`                                           | Fallback para viewport maior sem abrir sheet                       |
| Upload manual fallback         | `AfFileInputComponent`                         | `app/src/app/shared/components/file-input/`                                              | Usar quando plugin nativo indisponivel/negado                      |
| Estado de conectividade        | `OfflineBannerComponent` + `AfBannerComponent` | `app/src/app/shared/components/offline-banner/`, `app/src/app/shared/components/banner/` | Banner persistente no topo do chat enquanto offline                |
| Erro contextual                | `AfAlertComponent`                             | `app/src/app/shared/components/alert/`                                                   | Permissao negada, falha de acesso a camera/galeria/arquivo         |
| Status curto por item          | `AfStatusBadgeComponent`                       | `app/src/app/shared/components/status-badge/`                                            | Rotulo de estado da fila (`offline`, `warning`, `online`, `error`) |
| Tag de contagem                | `AfBadgeComponent`                             | `app/src/app/shared/components/badge/`                                                   | Contador de pendencias na barra do composer                        |
| Carregamento local             | `AfSpinnerComponent`                           | `app/src/app/shared/components/spinner/`                                                 | Spinner de flush por mensagem                                      |
| Acao com loading               | `AfLoadingButtonComponent`                     | `app/src/app/shared/components/loading-button/`                                          | Botao de retry/flush manual com bloqueio visual                    |
| Vazio sem itens                | `AfEmptyStateComponent`                        | `app/src/app/shared/components/empty-state/`                                             | Estado sem pendencias na fila                                      |
| Placeholder de lista           | `AfSkeletonComponent`                          | `app/src/app/shared/components/skeleton/`                                                | Carregamento inicial da lista de pendencias                        |

Regra de composicao:

- Nao criar novo componente para menu de anexos ou estado offline nesta fase.
- Reusar `AfSheetComponent` e `OfflineBannerComponent` como base.

---

## Design Spec - TASK-047.12 (Fluxo de anexos mobile)

### Layout

- Zona de composicao continua no rodape do chat.
- Trigger de anexo permanece no canto esquerdo do composer (icone plus/paperclip).
- Ao tocar no trigger em mobile, abrir bottom sheet com 3 acoes em pilha vertical:
    1. Tirar foto
    2. Galeria
    3. Arquivo
- Ao negar permissao, exibir alerta inline logo acima do composer (nao modal bloqueante).
- Em fallback (plugin indisponivel), abrir `AfFileInputComponent` oculto por acao programatica.

### Component Map

| Elemento                  | Componente a usar                                  | Notas                                                                 |
| ------------------------- | -------------------------------------------------- | --------------------------------------------------------------------- |
| Botao de abrir anexos     | `AfIconButtonComponent`                            | Variante ghost, tamanho sm/md conforme densidade do composer          |
| Menu de opcoes mobile     | `AfSheetComponent`                                 | Titulo: "Anexar"; fecha por backdrop, botao fechar e gesto toque fora |
| Itens de acao do sheet    | `AfButtonComponent`                                | Variante ghost/full width, icone a esquerda, label textual clara      |
| Aviso de permissao negada | `AfAlertComponent`                                 | Variante warning/danger conforme tipo de bloqueio                     |
| Fallback para arquivo     | `AfFileInputComponent`                             | Aceita filtro por tipo conforme escolha do usuario                    |
| Progresso de envio        | `AfSpinnerComponent` ou `AfLoadingButtonComponent` | Spinner no item sendo enviado                                         |

### Hierarquia visual

1. Acao primaria: selecionar origem do anexo (sheet com 3 opcoes).
2. Feedback de bloqueio/permissao: alerta logo acima do composer.
3. Estado de envio: spinner discreto no item em upload.

### Estados (obrigatorios)

| Estado  | Comportamento esperado                                                                             |
| ------- | -------------------------------------------------------------------------------------------------- |
| Default | Composer pronto, trigger de anexo ativo, sem alerta visivel                                        |
| Loading | Ao escolher opcao, mostrar progresso de abertura do picker e upload                                |
| Empty   | Usuario abre fluxo e cancela selecao; nenhuma mudanca permanente no composer                       |
| Error   | Permissao negada, plugin indisponivel ou upload falho; mostrar alerta com acao de tentar novamente |

### Regras de interacao

| Elemento         | Hover/press                   | Focus                                | Active                     |
| ---------------- | ----------------------------- | ------------------------------------ | -------------------------- |
| Trigger de anexo | feedback visual de press      | ring de foco padrao do design system | abre/fecha sheet           |
| Item do sheet    | fundo neutro elevado no toque | foco visivel para acessibilidade     | executa acao e fecha sheet |
| Alerta de erro   | sem animacao agressiva        | foco no botao de acao quando aparece | opcao de retry             |

### Tokens visuais

- Spacing: `p-2`, `p-4`, `gap-2`, `gap-4`.
- Tipografia: titulo do sheet em heading (`text-base font-semibold`), itens em body (`text-sm`).
- Cores: usar tokens sem hex hardcoded (`bg-neutral-*`, `text-neutral-*`, estados semanticos do componente).

### Responsive

- `<768px`: obrigatorio usar `AfSheetComponent` para opcoes de anexo.
- `>=768px`: permitido `AfDropdownMenuComponent` com as mesmas 3 opcoes.

### O que NAO fazer

- Nao usar menu custom absoluto no rodape para mobile.
- Nao bloquear o chat inteiro com modal para erro de permissao.
- Nao misturar labels ambiguas (ex.: "Midia" sem explicar camera/galeria/arquivo).

---

## Design Spec - TASK-047.13 (UX de fila offline)

### Layout

- Banner de conectividade fixo no topo da area de conversa quando offline/reconectando.
- Cada mensagem outbound offline recebe estado visual pendente no proprio item.
- Rodape do composer exibe contador agregado de fila pendente.
- Ao reconectar, feedback de flush em andamento e atualizacao progressiva item a item (FIFO).

### Component Map

| Elemento                     | Componente a usar                                           | Notas                                                      |
| ---------------------------- | ----------------------------------------------------------- | ---------------------------------------------------------- |
| Banner offline/reconexao     | `OfflineBannerComponent` (internamente `AfBannerComponent`) | Mensagem persistente enquanto sem rede                     |
| Estado de mensagem pendente  | `AfStatusBadgeComponent` + status no bubble                 | `warning/offline` durante fila; `online` apos envio        |
| Contador de fila no composer | `AfBadgeComponent`                                          | Ex.: "3 pendentes" proximo ao botao enviar                 |
| Flush manual                 | `AfLoadingButtonComponent`                                  | Acao "Tentar agora" quando online com fila pendente        |
| Erro de flush                | `AfAlertComponent`                                          | Exibir erro nao bloqueante e permitir retry                |
| Fila vazia                   | `AfEmptyStateComponent`                                     | Exibir somente quando usuario abrir painel/detalhe da fila |
| Carregamento da fila         | `AfSkeletonComponent` + `AfSpinnerComponent`                | Inicializacao da fila persistida no boot da tela           |

### Hierarquia visual

1. Estado de rede global (banner).
2. Estado por mensagem (pendente/enviando/enviada/erro).
3. Acao do usuario (retry/flush manual).

### Estados (obrigatorios)

| Estado  | Comportamento esperado                                                        |
| ------- | ----------------------------------------------------------------------------- |
| Default | Online, sem itens pendentes, composer normal                                  |
| Loading | Reconectou e iniciou flush FIFO; mensagens mudam para "enviando" em sequencia |
| Empty   | Fila carregada e sem mensagens pendentes                                      |
| Error   | Falha no flush de um ou mais itens; manter item em fila com opcao de retry    |

### Regras de interacao

| Elemento          | Hover/press                   | Focus                              | Active                                     |
| ----------------- | ----------------------------- | ---------------------------------- | ------------------------------------------ |
| Banner offline    | sem acao intrusiva            | foco no botao retry se existir     | some ao voltar online                      |
| Mensagem pendente | indicacao visual discreta     | tooltip/label acessivel com estado | atualiza para enviado sem reordenar thread |
| Botao flush/retry | loading bloqueia duplo clique | ring de foco                       | dispara novo flush FIFO                    |

### Regras de comportamento visual (FIFO)

- Ordem visual de envio deve respeitar a ordem de enfileiramento.
- Durante flush, apenas 1 item fica em estado "enviando" por vez.
- Se item falhar, ele permanece marcado como erro, e os proximos seguem politica definida pelo backend (registrar na UI se continuou ou pausou).
- Ao sucesso, remover badge de pendencia imediatamente e manter historico da mensagem no thread.

### Tokens visuais

- Spacing: `p-2`, `p-4`, `gap-2`, `gap-4`.
- Tipografia: labels de estado em `text-xs`.
- Cores semanticas: warning para pendente, info para reconectando, success para enviado, danger para erro.

### Responsive

- Mobile primeiro: banner compacto com uma linha + acao secundaria.
- Em telas maiores, permitir detalhes extras de fila (contador + ultima tentativa) sem alterar semantica.

### O que NAO fazer

- Nao esconder mensagem pendente sem indicacao.
- Nao reordenar mensagens para "simular" sucesso.
- Nao usar toast como unica fonte de status offline (estado precisa ser persistente em tela).

---

## Matriz consolidada de estados por fluxo

| Fluxo         | Default                        | Loading                          | Empty                                          | Error                                                  |
| ------------- | ------------------------------ | -------------------------------- | ---------------------------------------------- | ------------------------------------------------------ |
| Anexos mobile | Composer pronto e menu fechado | Abrindo picker e subindo arquivo | Usuario cancelou selecao sem anexos            | Permissao negada / plugin indisponivel / upload falhou |
| Fila offline  | Online sem pendencias          | Flush FIFO apos reconexao        | Sem itens em fila apos leitura da persistencia | Falha de envio mantendo item pendente                  |

---

## Criterios de aceitacao UI testaveis

### TASK-047.12 - anexos

1. Em viewport mobile, tocar no trigger de anexo abre `AfSheetComponent` com exatamente 3 opcoes: Tirar foto, Galeria, Arquivo.
2. Ao negar permissao de camera, aparece `AfAlertComponent` com mensagem clara e acao de tentativa/fallback.
3. Se plugin nativo falhar, fluxo cai para `AfFileInputComponent` sem quebrar o composer.
4. Durante upload, o item mostra estado de loading visivel (spinner ou botao loading) ate concluir.
5. Cancelar selecao no picker nao gera erro visual persistente e nao envia arquivo.

### TASK-047.13 - fila offline

1. Com rede offline, `OfflineBannerComponent` fica visivel e novas mensagens outbound entram em estado pendente.
2. Mensagens pendentes exibem status visual por item (badge/estado) sem sumir da thread.
3. Ao reconectar, UI inicia flush FIFO: itens trocam de estado um por vez na ordem correta.
4. Se um item falhar no flush, ele permanece marcado como erro/pendente e o usuario tem acao de retry.
5. Com fila vazia, estado empty e exibido no painel/detalhe da fila (quando aberto), sem interferir na conversa ativa.

---

## Handoff para FRONTEND

- Priorizar reuso de `AfSheetComponent`, `OfflineBannerComponent`, `AfAlertComponent`, `AfStatusBadgeComponent` e `AfBadgeComponent`.
- Evitar manter menu custom absoluto no composer para mobile; substituir por sheet.
- Garantir estados obrigatorios (default/loading/empty/error) nos dois fluxos antes de implementar detalhes de logica.
- Manter consistencia com chat atual: estado por mensagem continua no thread, mas com semantica clara para fila offline.
