# Revisar Fase (PREVC: Review de Fase)

Uso: `/review-phase [fase]`

Exemplo: `/review-phase 3` (revisa todas as tasks da Fase 3 — Backend)

## Processo (@REVIEWER)

1. Ler todas as tasks TASK-[fase].*.* no arquivo de tasks
2. Para cada task concluída, verificar:
   - Código segue arquitetura DDD (Domain sem imports de infra)
   - Convenções do workspace respeitadas
   - Testes cobrindo comportamento C (antes→depois)
   - Sem `console.log`, `dd()`, `dump()` esquecidos
   - Sem segredos no código
   - PHPDoc/JSDoc onde aplicável
   - Multi-tenancy correto (se aplicável)
3. Verificar checkpoint de revisão de fase no arquivo de tasks
4. Marcar checkpoint como aprovado ou listar pendências

## Saída Esperada

```
Fase [N] — Review:
  Tasks revisadas: [N]
  Aprovadas: [N]
  Pendências: [lista ou "nenhuma"]
  Checkpoint: [✅ APROVADO / ❌ REPROVADO — motivo]
```
