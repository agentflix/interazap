# MEMORY — Conhecimento Relevante e Reutilizável

> MEMORY não é log de ações. Guarda apenas conhecimento que evitará erros futuros.

## O que registrar

- Decisão técnica importante
- Escolha entre alternativas
- Regra de negócio não óbvia
- Padrão de implementação reutilizável
- Armadilha descoberta
- Bug difícil com causa relevante
- Integração com comportamento inesperado
- Convenção importante do projeto

## O que NÃO registrar

- Tasks concluídas
- Alterações triviais
- Logs operacionais
- "Arquivo X foi alterado"

## Formato de cada memória

```yaml
titulo: Nome claro
tipo: Decisão | Aprendizado | Armadilha | Padrão | Regra de negócio
data: YYYY-MM-DD
contexto: Situação que levou à decisão
conhecimento: O que foi aprendido ou decidido
alternativas: Alternativas consideradas (opcional)
consequencias: Impacto da decisão
quando_consultar: Quando voltar a consultar esta memória
referencias: Arquivos, links, decisões relacionadas
```

## Como consultar

Antes de mexer em área unfamiliar, check `.context/DOCS/MEMORY/` para evitar repetir erros.

## Exemplo de memória

```yaml
titulo: Normalização de telefones deve usar E.164 por organização
tipo: Decisão técnica / Regra de negócio
data: 2024-XX-XX
contexto: Chamados, conversas e contatos criavam duplicidades por variação de telefone
conhecimento: Todo telefone deve ser normalizado antes de vincular crm_contacts, conversations e tickets
consequencias: Evita duplicidade e garante que o botão editar contato sempre abra o contato real
quando_consultar: Sempre que mexer em contatos, conversas, tickets, WhatsApp ou CRM
```