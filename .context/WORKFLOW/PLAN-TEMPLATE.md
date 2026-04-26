# PLAN-XXX — [Nome da Feature]

> Template obrigatório para PLANs de features M/G.
> Features P (simples) podem ir direto para tasks T.A.C.E sem PLAN.

---

## 1. Objetivo

[O quê e por quê — máximo 5 linhas. Sem código de implementação aqui.]

---

## 2. Arquitetura

[Diagrama ASCII da solução — sem código de implementação]

```
Exemplo:
  HTTP POST /api/webchat/close
          │
          ▼
  WebChatCloseController
          │
          ▼
  UpdateChatTicketAction  ──►  ChatActivityBroadcastService
          │
          ▼
  ChatTicket (DB)
```

---

## 3. Contratos de Interface

> Apenas assinaturas — **sem corpo de método, sem lógica de runtime**.
> Código completo vai nas tasks T.A.C.E.

### Backend (PHP/Laravel)

```php
// Apenas assinaturas — sem corpo
interface ExampleAction {
    public function execute(string $tenantId, string $id): ExampleDTO|null;
    public function updateStatus(Model $model, string $status): Model;
}
```

### Frontend (Angular/TypeScript)

```typescript
// Apenas o modelo de dados e assinaturas de service
interface ExampleModel {
    id: string;
    status: 'open' | 'closed';
    closedAt?: string | null;
}

// Assinatura do método — sem implementação
closeTicket(): Observable<ExampleCloseResponse>;
```

---

## 4. Arquivos a Criar / Editar

| Arquivo | Operação | Agente | Task |
|---------|----------|--------|------|
| `api/src/Domain/X/Http/Controllers/ExampleController.php` | CRIAR | BACKEND | TASK-XXX.1 |
| `api/src/Domain/X/Routes/example.php` | EDITAR | BACKEND | TASK-XXX.1 |
| `app/src/app/pages/x/services/example.service.ts` | EDITAR | FRONTEND | TASK-XXX.2 |

---

## 5. API Endpoints

| Método | Rota | Auth | Agente | Task |
|--------|------|------|--------|------|
| POST | `/api/example/action` | JWT session | BACKEND | TASK-XXX.1 |
| GET | `/api/example/:id` | Sanctum | BACKEND | TASK-XXX.2 |

---

## 6. Tasks Derivadas

> Apenas ID + descrição de 1 linha. Detalhes vão no arquivo de tasks T.A.C.E.

| Task | Camada | Descrição |
|------|--------|-----------|
| TASK-XXX.1 | Backend | Criar controller e rota do endpoint de ação |
| TASK-XXX.2 | Backend | Expandir Action para emitir evento de broadcast |
| TASK-XXX.3 | Frontend | Adicionar estado e método no service |
| TASK-XXX.4 | Frontend | Atualizar componente com UI de confirmação |
| TASK-XXX.5 | Frontend | Sincronizar store do atendente via realtime |
| TASK-XXX.6 | QA | Testes backend (feature + unit) |
| TASK-XXX.7 | QA | Testes frontend (service + componente + store) |

---

## 7. Riscos

| Risco | Probabilidade | Mitigação |
|-------|--------------|-----------|
| [Risco 1] | Alta/Média/Baixa | [Como mitigar] |
| [Risco 2] | Alta/Média/Baixa | [Como mitigar] |

---

## 8. Notas de Implementação

> Regras de negócio importantes, casos de borda, decisões arquiteturais que afetam múltiplas tasks.

- [Nota 1]
- [Nota 2]

---

> ⚠️ **Código de implementação completo vai nas tasks T.A.C.E, não aqui.**
> O PLAN define **O QUÊ** e os contratos. As tasks T.A.C.E definem **O COMO**.
> Subagentes de execução (BACKEND, FRONTEND, DBA) recebem apenas a task específica — não leem o PLAN inteiro.
