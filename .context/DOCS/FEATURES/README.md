# Features

Documentação de features planejadas e implementadas em InteraZap.

## Fluxo

1. PLANNER (modo PM) cria feature doc a partir de PRD aprovado
2. REVIEWER revisa e aprova feature doc antes de EXECUTION
3. BUILDER implementa tasks da feature
4. REVIEWER confirma e marca feature como ✅

## Convenção de Nome

```
[feature-kebab-case].md
```

Exemplos:
- `chat-externo-webchat.md`
- `scheduling-via-whatsapp.md`
- `painel-operador-realtime.md`

## Template

Ver `_TEMPLATE.md` nesta pasta.

## Status dos Campos

- `[ ] Em planejamento` — feature doc criado, ainda sem tasks
- `[ ] Em execução` — tasks em andamento
- `[x] Concluída` — todas as tasks ✅ e REVIEWER confirmou
