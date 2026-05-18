# Changelog

Registro cronológico de TODAS as mudanças no InteraZap.
Atualizado na fase **CONFIRM** do PREVC — uma entrada por task concluída.

## Convenções

- Um arquivo por dia: `YYYY-MM-DD.md`
- Template: `_TEMPLATE.md`
- Registro FACTUAL — O QUE mudou, não POR QUÊ (por quê vai no MEMORY)
- Toda task concluída DEVE gerar entrada
- Toda feature concluída gera entrada de resumo

## Consultar

```bash
# Última entrada
ls -t .context/DOCS/CHANGELOG/2*.md | head -1

# Mudanças em um módulo
grep -r "Chat\|CRM\|Billing" .context/DOCS/CHANGELOG/

# Features desta semana
grep -r "FEAT" .context/DOCS/CHANGELOG/

# Por task
grep -r "TASK-3" .context/DOCS/CHANGELOG/
```
