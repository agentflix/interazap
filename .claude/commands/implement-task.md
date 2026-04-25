# Implement Task (PREVC: Execution)

Uso: `/implement-task [feature] [TASK-NNN]`

## Processo

1. **Ler task completa** (T.A.C.E)
2. **Verificar arquivos** existem ou precisam ser criados
3. **Implementar respeitando:**
    - T: O que foi definido
    - A: Apenas os arquivos listados
    - C: Comportamento antes→depois
4. **Escrever testes** para a mudança
5. **Atualizar documentação** se necessário
6. **Marcar task** como 🔄 Em Progresso

## Gates Parciais (durante implementação)

- Código compila
- Testes passando localmente
- Lint clean
- Rodar @QA e @REVIEW para verificar se a implementação está alinhada com o definido na task, passar o contexto para o subagent do para que ele nao fique perdido e possa validar corretamente a implementação. Enviar lista de arquivos modificados para o subagent.

## Output

```
🔄 TASK-X.Y.Z em progresso

Arquivos modificados:
- [A — Arquivo]

Próximo passo: /validate [feature] [TASK-NNN]
```
