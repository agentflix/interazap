---
name: review
description: >
  Revisar código, arquitetura e decisões técnicas.
  Use quando: review de PR, validar implementação, verificar conformidade com padrões.
---

# Review — Revisão de Código e Arquitetura

Revisa código, arquitetura e decisões técnicas.

## Quando usar

- PR precisa de review
- Implementação precisa validar
- Decisão arquitetural precisa validar
- Verificar conformidade com padrões

## Checklist de Review

### Código

- [ ] Nomenclatura segue convenções
- [ ] Sem código duplicado
- [ ] Sem comentários desnecessários
- [ ] Error handling adequado
- [ ] Logging apropriado
- [ ] Sem secrets no código

### Arquitetura (API Laravel)

- [ ] Segue padrão Controller → DTO → Action → Resource
- [ ] Usa BelongsToTenant quando aplicável
- [ ] UUID como primary key
- [ ] Eager loading para evitar N+1
- [ ] $fillable explícito (nunca $guarded = [])
- [ ] final class em Controllers, Actions, DTOs
- [ ] declare(strict_types=1)

### Arquitetura (Gateway NestJS)

- [ ] ValidationPipe com whitelist: true
- [ ] Logger dedicado por controller/service
- [ ] Idempotência em webhook handlers
- [ ] Circuit breaker em chamadas externas
- [ ] Resposta webhook < 150ms

### Testes

- [ ] Testes cobrem cenário principal
- [ ] Sem testes vazios/skipped
- [ ] Mock adequado de dependências
- [ ] Cobertura >= 80%

## Fluxo de Review

```
1. Understand → O que foi alterado?
2. Analyze → Arquivos, módulos afetados
3. Check → Padrões, convenções, riscos
4. Comment → Feedback específico e acionável
5. Approve/Request Changes → Decisão final
```

## Tipos de Review

| Tipo | Quando | Profundidade |
|------|--------|--------------|
| Quick | Bug fix local | 5-10 min |
| Standard | Feature normal | 15-30 min |
| Deep | Mudança arquitetural | 45-60 min |

## Comentário de Review bom

```
[Modulo/Arquivo]:[Linha]

Problema: [O que está errado ou pode melhorar]
Sugestão: [Como resolver, se aplicável]
Severity: [blocking | non-blocking]
```

## Saída esperada

```
Review Result: APPROVE | REQUEST_CHANGES | REJECT
 arquivos alterados: [lista]
 observações: [lista ou "nenhuma"]
 blockers: [lista ou "nenhum"]
```