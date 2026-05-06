# Memory: Filtro de contatos por empresa no formulário de negociação

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | Codex (DEBUG) |
| **Contexto** | Correção do bug de seleção de contato em negociações CRM |
| **Tags** | crm, negotiations, contacts, frontend, backend |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Ao selecionar `crm_company_id` no formulário de negociação, o campo `contact_id` ficava sem opções em cenários onde os contatos da empresa não estavam entre os primeiros registros carregados no client (limite/paginação), bloqueando criação/edição da negociação.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Foi adotada busca reativa server-side de contatos por empresa selecionada, com filtro explícito no endpoint (`crm_company_id`, aceitando alias `company_id` no request). Também foi reforçado o fallback de vínculo no frontend para `crm_company_id` -> `company.id` -> `company_id`.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter somente filtro client-side em lista global de contatos | Pode omitir contatos por paginação/limite e reproduzir o bug em tenants grandes |
| Aumentar `per_page` global sem filtro por empresa | Escala pior, não garante consistência funcional e aumenta payload desnecessariamente |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Campo de contato passa a refletir com precisão os contatos da empresa selecionada.
- Menor risco de regressão por inconsistência entre payload de contato e relacionamentos carregados.
- Backend com filtro reutilizável para outras telas.

### Negativas / Trade-offs
- Nova chamada HTTP ao trocar empresa no formulário.
- Fluxo depende de disponibilidade do endpoint de contatos no momento da seleção.

---

## Referências
- Feature: `.context/DOCS/FEATURES/` (bugfix operacional em CRM)
- Task: `.context/DOCS/TASKS/` (task de correção de bug de negociação)
