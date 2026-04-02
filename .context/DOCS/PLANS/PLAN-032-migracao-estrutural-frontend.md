# PLAN-032-migracao-estrutural-frontend — Migração Estrutural de Componentes Angular

## Objetivo

Padronizar 100% dos componentes Angular do frontend (`app/src/app/`) seguindo o modelo definido em `app/src/app/pages/crm/contacts/` (golden model). Isso inclui:

1. **HTML externo**: Extrair todo `template:` inline para arquivos `.html` separados (`templateUrl`)
2. **SCSS externo**: Extrair todo `styles:` inline para arquivos `.scss` separados (`styleUrl`)
3. **Model files**: Mover todas interfaces, types, enums e constantes exportadas dentro de componentes para arquivos `{nome}.model.ts`
4. **jsDoc obrigatório**: Adicionar documentação JSDoc na classe, em todos os `@Input`, `@Output`, signals públicos e métodos públicos — conforme padrão do CRM contacts

## Módulo relacionado

Frontend (todos os módulos: Shared | AI | Auth | Billing | Chat | Configuration | CRM | Dashboard | Platform | Reports | Public | Admin | Settings)

## PRD relacionado (se existir): N/A

---

## Diagnóstico (evidências coletadas)

### Situação atual

| Métrica | Quantidade | Observação |
|---|---|---|
| Componentes com `template:` inline em `pages/` | ~136 | 53 já migrados para `templateUrl` |
| Componentes com `template:` inline em `shared/` | ~117 | 1 HTML externo existente |
| Arquivos com `styles:` inline em `pages/` | 2 | `chat-message-media`, `agenda-calendar-view` |
| Arquivos com `styles:` inline em `shared/` | 6 | `modal`, `data-table`, `marquee`, `scroll-area`, `title-bar`, `update-notification` |
| Arquivos `.model.ts` em `pages/` | 3 | `chat-realtime`, `chat-recorder`, `platform-plan` |
| Arquivos `.model.ts` em `shared/models/` | 9 | Padrão correto |
| Componentes sem `/** */` algum em `pages/` | ~50 | Faltam jsDoc de classe e membros |
| Arquivos `.types.ts` (nome errado, deve ser `.model.ts`) | 3 | `crm-negotiation.types.ts`, `chat-quick-answer.types.ts`, `media-preview.types.ts` |

### Golden model (referência)

`app/src/app/pages/crm/contacts/crm-contacts.ts`:
- ✅ jsDoc no topo da classe e em todos os membros públicos
- ✅ Helpers/types em arquivo separado (`crm-contacts.helpers.ts`)
- ✅ `ChangeDetectionStrategy.OnPush`
- ❌ Template ainda inline (será migrado neste plano)
- ❌ Sem `.model.ts` explícito

### Padrão alvo por componente

```
{nome}.ts         → Decorador + lógica pura (sem template, sem tipos exportados)
{nome}.html       → templateUrl: './{nome}.html'
{nome}.scss       → styleUrl: './{nome}.scss' (somente se houver estilos)
{nome}.model.ts   → Interfaces, types, enums, constantes exportadas do componente
```

#### Exemplo: componente com `template:` inline

**Antes (`crm-contacts.ts` — fragmento):**
```typescript
/**
 * Contacts CRM page — CRUD for contacts.
 */
@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [...],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <af-crud-page ...>
      ...
    </af-crud-page>
  `,
})
export class Contacts implements OnInit { ... }
```

**Depois (`crm-contacts.ts`):**
```typescript
/**
 * Contacts CRM page — CRUD for contacts.
 */
@Component({
  selector: 'app-contacts',
  standalone: true,
  imports: [...],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-contacts.html',
})
export class Contacts implements OnInit { ... }
```

**`crm-contacts.html`** (conteúdo extraído verbatim):
```html
<af-crud-page ...>
  ...
</af-crud-page>
```

#### Exemplo: jsDoc em interfaces/model

**`crm-negotiations.model.ts`:**
```typescript
/**
 * Supported status filter values for negotiations list.
 */
export type NegotiationStatusFilter = 'all' | 'open' | 'won' | 'lost';

/**
 * UI state for kanban column display.
 */
export interface NegotiationKanbanColumn {
  /** Unique funnel stage identifier */
  id: string;
  /** Display name of the stage */
  title: string;
  /** Total value of negotiations in this stage */
  totalValue: number;
}
```

#### Regra para `.types.ts` existentes

Renomear para `.model.ts`:
- `crm-negotiation.types.ts` → `crm-negotiation.model.ts`
- `chat-quick-answer.types.ts` → `chat-quick-answer.model.ts`
- `media-preview.types.ts` → `media-preview.model.ts`

---

## Escopo

### Incluído

- Extração de `template:` → `templateUrl` + arquivo `.html` para **todos** os componentes em `pages/` e `shared/`
- Extração de `styles:` → `styleUrl` + arquivo `.scss` para os 8 arquivos identificados
- Criação de `{nome}.model.ts` para componentes com interfaces/types/enums/constantes exportadas que ainda não possuem o arquivo
- Renomeação de `.types.ts` existentes para `.model.ts`
- jsDoc em classes de componentes e interfaces públicas (todos os `@Input`, `@Output`, signals públicos, métodos públicos)
- Atualização de imports após renomeação de `.types.ts` → `.model.ts`

### Excluído

- Refatoração de lógica de negócio, signals ou state management
- Adição de novos comportamentos ou features
- Migração de serviços, guards, pipes (fora do escopo de componentes)
- Criação de stories ou testes novos
- Modificação de shared models em `shared/models/*.model.ts` (já corretos)
- Arquivos `.helpers.ts` (convenção aceita, não renomear)
- Arquivos `.store.ts` (não são componentes)
- Arquivos `.spec.ts` (não precisam de adaptação)

---

## Technical Approach

### Skills obrigatórias para o executor

| Skill | Arquivo |
|---|---|
| design | `.claude/skills/design/SKILL.md` |
| frontend-flow | `.claude/skills/frontend-flow/SKILL.md` |
| angular-architect | `.claude/skills/angular-architect/SKILL.md` |
| coding-guidelines | `.claude/skills/coding-guidelines/SKILL.md` |

### Estratégia de execução

Esta migração é **puramente estrutural** — sem mudança de comportamento. A ordem de entregas segue a prioridade de impacto:

1. **Shared primeiro**: componentes em `shared/` são usados por todo o app — migração aqui desbloqueia verificações visuais em toda a base
2. **Por módulo**: cada módulo de página em entrega independente (paralelizável com sub-agentes)
3. **Convenção de nomeação**: o arquivo `.html` deve ter o mesmo nome-base do `.ts` (ex: `crm-contacts.ts` → `crm-contacts.html`)
4. **jsDoc mínimo obrigatório**: toda classe de componente deve ter um bloco `/** */` de descrição. Membros públicos (inputs, outputs, signals, métodos públicos) com `/** */` inline

### Checklist por componente (executor deve seguir)

```
Para cada arquivo .ts com template: inline:
  [ ] Extrair conteúdo do template para {nome}.html (mesmo diretório)
  [ ] Substituir `template: \`...\`` por `templateUrl: './{nome}.html'`
  [ ] Se houver styles: [...], criar {nome}.scss e usar styleUrl: './{nome}.scss'
  [ ] Identificar interfaces/types/enums/const exportados no .ts:
      [ ] Se existir → criar {nome}.model.ts e mover para lá
      [ ] Atualizar imports nos consumidores
  [ ] Adicionar/completar jsDoc na classe (bloco /** */ acima de export class)
  [ ] Adicionar /** */ em todos os @Input, @Output, signals e métodos públicos
  [ ] Rodar gate:all para verificar sem regressões
```

---

## Etapas propostas

1. **Setup — Regra ESLint para detectar template inline** (prevenção de regressão)
2. **Migração Shared Components** (117 componentes em `shared/`)
   - 2a. `shared/components/` — grupo layout/nav (modal, tabs, title-bar, etc.)
   - 2b. `shared/components/` — grupo inputs (text-input, select, phone, etc.)
   - 2c. `shared/components/` — grupo tabela/lista/actions (data-table, crud-page, etc.)
   - 2d. `shared/components/` — grupo utilitários restantes
3. **Migração CRM Module** (golden model + demais)
   - 3a. `crm/contacts/` (golden model — extrair HTML)
   - 3b. `crm/negotiations/`, `crm/funnels/`, `crm/agenda/`, etc.
4. **Migração Chat Module**
   - 4a. `chat/components/` principais
   - 4b. `chat/chat-sidebar/` e subcomponentes
5. **Migração AI Module** (20+ componentes)
6. **Migração Auth Module** (10+ componentes)
7. **Migração módulos restantes** (Dashboard, Reports, Billing, Platform, Configuration, Public, Admin, Settings)
8. **Renomear `.types.ts` → `.model.ts`** + atualizar imports

---

## Entregas derivadas

**Entregas:** 8 | **Tasks:** 14

| Entrega | Descrição | Tasks | Esforço | Status |
|---------|-----------|-------|---------|--------|
| 1 | Setup ESLint rule + documentar padrão | TASK-032.1.1 | XS | todo |
| 2 | Shared Components — todos os grupos | TASK-032.2.1 - TASK-032.2.4 | M | todo |
| 3 | CRM Module | TASK-032.3.1 - TASK-032.3.2 | S | todo |
| 4 | Chat Module | TASK-032.4.1 - TASK-032.4.2 | S | todo |
| 5 | AI Module | TASK-032.5.1 | S | todo |
| 6 | Auth Module | TASK-032.6.1 | S | todo |
| 7 | Módulos restantes (Dashboard, Reports, Billing, Platform, Configuration, Public, Admin, Settings) | TASK-032.7.1 | S | todo |
| 8 | Renomear .types.ts → .model.ts + atualizar imports | TASK-032.8.1 | XS | todo |

---

## Arquivos a Modificar (amostra por entrega)

### Entrega 1 — ESLint Rule

| Arquivo | Ação | Caminho |
|---|---|---|
| `eslint.config.js` | modificar | `app/eslint.config.js` |

### Entrega 2 — Shared Components (amostra)

| Arquivo | Ação | Caminho |
|---|---|---|
| `modal.ts` | modificar (adicionar templateUrl, styleUrl, jsDoc) | `app/src/app/shared/components/modal/modal.ts` |
| `modal.html` | criar | `app/src/app/shared/components/modal/modal.html` |
| `modal.scss` | criar | `app/src/app/shared/components/modal/modal.scss` |
| `data-table.ts` | modificar | `app/src/app/shared/components/data-table/data-table.ts` |
| `data-table.html` | criar | `app/src/app/shared/components/data-table/data-table.html` |
| `data-table.scss` | criar | `app/src/app/shared/components/data-table/data-table.scss` |
| *(+ ~115 pares .ts/.html para demais componentes shared)* | criar/modificar | `app/src/app/shared/components/*/` |

### Entrega 3 — CRM Module (amostra)

| Arquivo | Ação | Caminho |
|---|---|---|
| `crm-contacts.ts` | modificar (templateUrl, jsDoc) | `app/src/app/pages/crm/contacts/crm-contacts.ts` |
| `crm-contacts.html` | criar | `app/src/app/pages/crm/contacts/crm-contacts.html` |
| `negotiations.ts` | modificar | `app/src/app/pages/crm/negotiations/negotiations.ts` |
| `negotiations.html` | criar | `app/src/app/pages/crm/negotiations/negotiations.html` |
| `negotiations.model.ts` | criar (extrair NegotiationStatusFilter e interfaces) | `app/src/app/pages/crm/negotiations/negotiations.model.ts` |
| *(+ demais componentes crm/agenda, crm/funnels, crm/proposals, etc.)* | criar/modificar | `app/src/app/pages/crm/*/` |

### Entrega 4 — Chat Module (amostra)

| Arquivo | Ação | Caminho |
|---|---|---|
| `chatbot.ts` | modificar | `app/src/app/pages/chat/chatbot/chatbot.ts` |
| `chatbot.html` | criar | `app/src/app/pages/chat/chatbot/chatbot.html` |
| `chat-message-media.component.ts` | modificar (templateUrl + styleUrl) | `app/src/app/pages/chat/components/chat-message-media/` |
| `chat-message-media.component.scss` | criar | `app/src/app/pages/chat/components/chat-message-media/` |
| `chat-quoted-message.model.ts` | criar (extrair interfaces) | `app/src/app/pages/chat/components/chat-quoted-message/` |
| *(+ demais componentes chat)* | criar/modificar | `app/src/app/pages/chat/*/` |

### Entrega 8 — Renomear .types.ts → .model.ts

| Arquivo | Ação | Caminho |
|---|---|---|
| `crm-negotiation.types.ts` | renomear → `.model.ts` + atualizar imports | `app/src/app/pages/chat/chat-sidebar/crm-section/crm-negotiation.model.ts` |
| `chat-quick-answer.types.ts` | renomear → `.model.ts` + atualizar imports | `app/src/app/pages/chat/types/chat-quick-answer.model.ts` |
| `media-preview.types.ts` | renomear → `.model.ts` + atualizar imports | `app/src/app/pages/chat/types/media-preview.model.ts` |

---

## Paralelização

As entregas 3 a 7 são **independentes entre si** após a entrega 2 (shared) e podem ser executadas em paralelo com múltiplos agentes `@FRONTEND`:

```
Entrega 1 → Entrega 2 → [Entregas 3, 4, 5, 6, 7 em paralelo] → Entrega 8
```

| Rodada | Entregas | Agentes sugeridos |
|---|---|---|
| 1 | 1 (ESLint setup) | @FRONTEND |
| 2 | 2a, 2b (Shared inputs + layout) | @FRONTEND × 2 paralelo |
| 3 | 2c, 2d (Shared tabela + utilitários) | @FRONTEND × 2 paralelo |
| 4 | 3, 4, 5 (CRM, Chat, AI) | @FRONTEND × 3 paralelo |
| 5 | 6, 7, 8 (Auth, Resto, Types rename) | @FRONTEND × 3 paralelo |

---

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Breaking import paths ao renomear `.types.ts` | Média | Alto | Rodar TSC estrito após cada rename + busca global de imports |
| Template extraído com indentação incorreta | Baixa | Médio | Copiar verbatim sem reformatar; rodar `pnpm run gate:all` |
| Componente com múltiplos blocos `styles: []` | Baixa | Baixo | Concatenar em único `.scss`, manter ordem |
| jsDoc incompleto em membros privados | Alta | Baixo | jsDoc é opcional em membros privados; foco em públicos |
| Componente usa `host: { ... }` que referencia variável do .ts | Baixa | Médio | Não afetar decoradores `host:`, apenas `template:` e `styles:` |

### Dependências

- Gates devem passar (`pnpm run gate:all`) após cada entrega antes de iniciar a próxima
- Entrega 2 (Shared) deve ser concluída antes das entregas 3-7 (pois os componentes shared são importados em todos os módulos)
- Entrega 8 (renomear `.types.ts`) deve ser a última (acoplamento de imports)

---

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Alta (volume de arquivos) / Baixa (risco por arquivo) |
| Camadas afetadas | Frontend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Sim — todos os módulos frontend |
| Estimativa total de arquivos | ~253 `.ts` + ~253 `.html` criados + ~14 `.scss` criados + ~12 `.model.ts` criados |

---

## Critérios de sucesso

- [ ] Zero componentes com `template:` inline em `pages/` e `shared/`
- [ ] Zero componentes com `styles:` inline em `pages/` e `shared/`
- [ ] Nenhum arquivo `.types.ts` remanescente (renomeados para `.model.ts`)
- [ ] Todo componente com `@Component` possui jsDoc de classe `/** */`
- [ ] Todo `@Input`, `@Output` e signal público possui `/** */`
- [ ] `pnpm run gate:all` passa sem erros após cada entrega
- [ ] Nenhuma mudança de comportamento ou visual (migração puramente estrutural)
