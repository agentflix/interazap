# Memory: Composer condicional Meta WhatsApp — decisões de implementação

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-29 |
| **Autor** | FRONTEND |
| **Contexto** | FEAT-050 Sprint 4 (D1–D7) — Composer com janela 24h |
| **Tags** | angular, signals, meta-whatsapp, composer, 24h-window |

---

## Situação
O frontend precisava adaptar o composer do chat para 3 modos distintos dependendo do provider da instância (Meta vs outros) e do status da janela 24h. O `Called` (ticket) não expunha `provider` diretamente — apenas `instance_id`.

---

## Decisão / Aprendizado

1. **Mapeamento instance_id → provider no ChatStore**
   - Como o `Called` só tem `instance_id`, o `ChatStore` carrega a lista de instâncias via `InstanceService` no constructor e mantém um `instanceProviders` signal (mapa id → provider).
   - O `composerMode` é um `computed` que consulta esse mapa + `windowStatus`. Isso evita N chamadas de API e mantém a reatividade pura.

2. **TemplateSelector como shared component**
   - O componente foi movido para `shared/components/template-selector/` e seu export centralizado em `shared/components/index.ts`. Isso desbloqueou o reuso tanto no modal de nova conversa (existente) quanto no composer (novo).

3. **AfPopover não aceita `[open]`/(closed)**
   - O componente usa o padrão `<ng-content select="[trigger]">` e `<ng-content select="[content]">`. Não é possível controlar abertura/fechamento por binding externo. A solução foi usar o trigger nativo do componente.

4. **AfBanner espera `[message]` input**
   - Tentativa de usar conteúdo inline (`<af-banner>texto</af-banner>`) falhou. O componente só renderiza via input `[message]`.

5. **Reabertura de janela via WS sem polling**
   - Ao invés de polling, o `ChatRealtimeListenerService` agora expõe `incomingMessage$` (Subject). O `chat.ts` se inscreve e invalida o cache + re-checa status apenas quando o inbound pertence ao ticket selecionado.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Adicionar `provider` ao `Called` no backend | Fora do escopo do Sprint 4 (frontend-only). Seria melhor a longo prazo, mas exigiria alterar `ChatTicketResource` e re-testar toda a lista de tickets. |
| Buscar provider da instância a cada troca de ticket | Ineficiente — geraria N chamadas desnecessárias. O mapa em memória resolve com 1 chamada no boot. |
| Usar modal para template picker em modo `mixed` | O plano DESIGNER especifica popover (desktop) e sheet (mobile). Modal seria muito intrusivo para uma ação secundária dentro da janela. |

---

## Consequências

### Positivas
- Composer 100% reativo via signals — sem subscriptions manuais vazadas.
- Reutilização do `TemplateSelectorComponent` evita duplicação de código.
- UX defensiva: em `template-only` o textarea simplesmente não existe, impedindo erro 422 do backend.

### Negativas / Trade-offs
- O mapa de instâncias é carregado 1x no constructor do `ChatStore`. Se uma instância for criada/editada em tempo real, o mapa pode ficar desatualizado até refresh da página.
- O ` Called` interface teve que ser estendida com `instance_id` no frontend. Se o backend remover esse campo, o composer quebra.

---

## Atualização pós-Review (23:15)

O REVIEWER identificou 2 bugs críticos que foram corrigidos:

1. **Ordenação de parâmetros do template**: `localeCompare()` faz ordenação lexicográfica (`param_0, param_1, param_10, param_2...`). Corrigido para ordenação numérica via `parseInt`.

2. **TemplateSelector sem botão submit**: O método `submit()` existia mas nunca era chamado do HTML. Adicionado botão "Enviar template" que chama `submit()`. Sem isso, o fluxo completo de envio de template pelo composer condicional estava inoperante.

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-050-meta-message-templates.md`
- Task: `.context/DOCS/TASKS/FEAT-050-tasks.md`
- Plan: `.context/PLANS/FEAT-050-templates.md`
