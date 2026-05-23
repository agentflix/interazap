# security-quality-hardening — Decisões

**Tipo:** Aprendizado + Decisão
**Data:** 2026-05-23
**Autor:** PREVEC REVIEWER
**Tags:** tenant-isolation, jobs, queue, security, defense-in-depth

## TASK-3.1.x — TenantScope silencia em contexto de job

**Tipo:** Armadilha
**Data:** 2026-05-23

### Situação

8 jobs Laravel faziam `find()` em modelos `BelongsToTenant` sem where de tenant explícito. Em contexto de queue, `auth()->user()` é null e `TenantContext::get()` é null → `TenantScope::apply()` retorna silenciosamente sem adicionar filtro.

### Decisão / Aprendizado

Qualquer job que usa `find()` / `findOrFail()` em modelo com `BelongsToTenant` DEVE adicionar `->where('tenant_id', $this->tenantId)` explicitamente no construtor. TenantScope não é confiável em job context.

**Padrão A** (job já tem tenantId): adicionar `->where('tenant_id', $this->tenantId)` no find.
**Padrão B** (job sem tenantId): adicionar `private readonly string $tenantId` ao construtor + atualizar todos os dispatch sites.

### Alternativas Consideradas

| Alternativa | Por que descartada |
|---|---|
| Refatorar TenantScope para throw em job context | Risco de regressão em jobs legítimos que não precisam de tenant scope |
| Injetar TenantContext no job antes do find | Mais complexo, não é o padrão do projeto |

### Consequências

- **Positivas:** Defense-in-depth — job nunca acessa dados cross-tenant mesmo que TenantScope falhe
- **Negativas / Trade-offs:** Todo novo job precisa receber tenantId no construtor e propagá-lo
- **Ação necessária:** Em code review, checar se novos jobs com `find()` em BelongsToTenant têm where de tenant

---

## TASK-3.1.5 — AuthRole::INQUILINO_ID em notifySuperAdmins()

**Tipo:** Decisão
**Data:** 2026-05-23

### Situação

Durante implementação de TASK-3.1.5 (AiPromptGuardianJob), BUILDER adicionou `AuthRole::INQUILINO_ID` ao `whereIn` em `notifySuperAdmins()` fora do escopo da T.A.C.E. Review detectou como scope creep e escalou para decisão humana.

### Decisão / Aprendizado

**Manter** — donos de tenant (INQUILINO) devem receber notificações quando guardrail bloqueia prompt AI no seu próprio tenant. Comportamento anterior (só admins de plataforma) era incompleto.

Comportamento após decisão:
```php
->whereIn('role_id', [AuthRole::ADMINISTRADOR_ID, AuthRole::INQUILINO_ID])
```

### Alternativas Consideradas

| Alternativa | Por que descartada |
|---|---|
| Reverter — só ADMINISTRADOR_ID | Dono do tenant não seria notificado de bloqueios no seu tenant |

### Consequências

- **Positivas:** Tenant owners ficam cientes de atividade de guardrail no seu tenant
- **Negativas / Trade-offs:** Volume de notificações aumenta para INQUILINOs
- **Ação necessária:** Verificar se outros jobs de notificação de segurança também devem incluir INQUILINO_ID
