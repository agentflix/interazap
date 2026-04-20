# Feature: Landing Chat Launcher

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo                  | Valor                 |
| ---------------------- | --------------------- |
| **ID**                 | FEAT-041              |
| **Nome**               | Landing Chat Launcher |
| **Bounded Context**    | Chat                  |
| **Complexidade**       | P                     |
| **Prioridade**         | Must                  |
| **Status**             | 🟢 Completed          |
| **Criada em**          | 2026-04-20            |
| **Última atualização** | 2026-04-20            |

---

## Resumo

Adicionar um launcher de chat interno na landing page com ID fixo obrigatório (`interazap-chat-launcher`) para abrir o fluxo de WebChat já existente na FEAT-040, sem criação de backend novo.

---

## Objetivo

Formalizar a aprovação do usuário para disponibilizar um ponto de entrada consistente e rastreável para o chat na landing, garantindo integração estável com scripts externos, automação de QA e analytics por meio de contrato explícito de atributos `data-*`.

---

## Escopo

### Dentro do Escopo ✅

- [ ] Inserir launcher de chat na landing com ID fixo obrigatório `interazap-chat-launcher`.
- [ ] Definir contrato de atributos `data-*` para integração e analytics.
- [ ] Reusar integralmente FEAT-040 para sessão/mensageria do chat (sem backend novo).
- [ ] Garantir comportamento responsivo e acessível do launcher (desktop + mobile).

### Fora do Escopo ❌

- Criar novos endpoints, serviços ou migrations no backend.
- Alterar fluxo de sessão/JWT/WebSocket do WebChat definido na FEAT-040.
- Criar novo canal/provedor de chat.

---

## Dependências

| Feature/Sistema           | Tipo        | Status     | Blocker |
| ------------------------- | ----------- | ---------- | ------- |
| FEAT-040 — WebChat Widget | Bloqueia    | Pronta     | Sim     |
| Landing (frontend)        | É bloqueado | Necessária | Não     |
| Analytics de produto      | É bloqueado | Necessária | Não     |

---

## Reuso Explícito da FEAT-040

Esta feature **não** cria backend novo. O launcher da landing apenas aciona o fluxo existente da FEAT-040:

1. Entrada no WebChat público já implementado.
2. Criação de sessão no contrato atual de webchat.
3. Mensageria e tempo real via infraestrutura existente (JWT + WebSocket + Redis).

Referência principal de reuso: `.context/DOCS/FEATURES/FEAT-040-webchat-widget.md`.

---

## Contrato do Launcher (DOM + data-\*)

### Elemento raiz obrigatório

- `id="interazap-chat-launcher"` (imutável e único por página)

### Atributos obrigatórios (`data-*`)

| Atributo                    | Tipo                                     | Obrigatório | Exemplo           | Finalidade                                       |
| --------------------------- | ---------------------------------------- | ----------- | ----------------- | ------------------------------------------------ |
| `data-iz-chat-launcher`     | string                                   | Sim         | `v1`              | Marca versão do contrato de integração           |
| `data-iz-tenant-id`         | string (uuid/slug)                       | Sim         | `acme`            | Identificar tenant alvo para iniciar sessão      |
| `data-iz-entrypoint`        | string                                   | Sim         | `landing`         | Origem de abertura para analytics                |
| `data-iz-variant`           | string                                   | Não         | `floating-pill-a` | A/B test e segmentação visual                    |
| `data-iz-analytics-context` | string (json compactado/base64 ou chave) | Não         | `campaign=lp_q2`  | Contexto adicional de campanha                   |
| `data-iz-open-mode`         | enum                                     | Não         | `drawer`          | Modo de abertura (`drawer`, `modal`, `redirect`) |

### Eventos de telemetria esperados

| Evento                       | Disparo                | Campos mínimos                                    |
| ---------------------------- | ---------------------- | ------------------------------------------------- |
| `chat_launcher_impression`   | Launcher renderizado   | `entrypoint`, `tenant_id`, `variant`              |
| `chat_launcher_click`        | Clique/tap no launcher | `entrypoint`, `tenant_id`, `variant`, `open_mode` |
| `chat_launcher_open_success` | Chat abriu com sucesso | `entrypoint`, `tenant_id`, `session_status`       |
| `chat_launcher_open_error`   | Falha ao abrir chat    | `entrypoint`, `tenant_id`, `error_code`           |

---

## Critérios de Aceite

| ID     | Critério                                                                                                    | Verificável | Status |
| ------ | ----------------------------------------------------------------------------------------------------------- | ----------- | ------ |
| CA-001 | Existe exatamente 1 elemento na landing com `id="interazap-chat-launcher"`.                                 | [ ]         | ❌     |
| CA-002 | O launcher abre o fluxo de chat reutilizando FEAT-040, sem chamada para endpoints novos.                    | [ ]         | ❌     |
| CA-003 | O elemento inclui todos os `data-*` obrigatórios do contrato.                                               | [ ]         | ❌     |
| CA-004 | Eventos `chat_launcher_impression` e `chat_launcher_click` são emitidos com payload mínimo.                 | [ ]         | ❌     |
| CA-005 | Em falha de abertura, usuário recebe feedback não-bloqueante e evento `chat_launcher_open_error` é emitido. | [ ]         | ❌     |
| CA-006 | Launcher atende UX responsiva (desktop/mobile) sem sobrepor conteúdo crítico da landing.                    | [ ]         | ❌     |
| CA-007 | Launcher é acessível por teclado (Tab/Enter/Espaço) e possui rótulo acessível.                              | [ ]         | ❌     |
| CA-008 | Não há exposição de segredo/token em atributo HTML, URL pública ou logs do browser.                         | [ ]         | ❌     |

---

## Tasks

| Task ID    | Descrição                                                                    | Status |
| ---------- | ---------------------------------------------------------------------------- | ------ |
| TASK-041.1 | Planning: consolidar escopo, contrato e reuso FEAT-040                       | ⏳     |
| TASK-041.2 | Review: validar aderência técnica/segurança/UX do contrato do launcher       | ⏳     |
| TASK-041.3 | Execution (FRONTEND): implementar launcher na landing com ID fixo e `data-*` | ⏳     |
| TASK-041.4 | Validation (QA): validar critérios funcionais/técnicos/UX/segurança          | ⏳     |
| TASK-041.5 | Confirm (DOC): registrar CHANGELOG, MEMORY e atualizar project-state.yaml    | ⏳     |

---

## Notas

- Decisões de FEAT-040 sobre JWT/WebSocket/tenant devem ser preservadas sem alteração de contrato de backend.
- Qualquer ampliação de escopo (ex.: launcher multiponto, personalização por tenant em runtime) deve gerar nova feature ou aditivo formal.
