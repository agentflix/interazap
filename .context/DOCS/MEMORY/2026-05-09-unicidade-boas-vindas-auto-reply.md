# Memory: Unicidade de regra de boas-vindas no Auto Reply por tenant

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-09 |
| **Autor** | Codex |
| **Contexto** | Chat Auto Reply (`is_welcome`) |
| **Tags** | chat, auto-reply, multi-tenant, consistencia, banco |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Era possível manter múltiplas regras com `is_welcome=true` no mesmo tenant. No runtime, o responder usava `latest()->first()`, então apenas uma regra era aplicada e as demais ficavam ativas sem efeito prático.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Aplicar proteção em duas camadas:
- Camada de domínio: ao criar/editar uma regra com `is_welcome=true`, desativar as demais do tenant dentro de transação com `lockForUpdate`.
- Camada de banco: criar índice único parcial por `tenant_id` onde `is_welcome=true`, após backfill para manter somente a regra mais recente.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Apenas lógica no backend (sem constraint) | Não protege contra concorrência externa ou escrita fora da action. |
| Apenas constraint no banco (sem ajuste na action) | Gera erro de integridade sem transição automática esperada de UX. |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Consistência garantida: no máximo uma regra de boas-vindas ativa por tenant.
- Menor risco de comportamento órfão/invisível no runtime.

### Negativas / Trade-offs
- Introduz acoplamento a índice parcial no banco para esse comportamento.

---

## Referências
- Feature: plano de execução "Garantir apenas uma mensagem de boas-vindas ativa por tenant"
- Task: `TASK-CHAT-WELCOME-1..5`
