# Memory: Conversão de lead para tenant com bootstrap síncrono e rollback total

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | FEAT-005 — Conversão de Lead em Tenant + Exportação CSV |
| **Tags** | platform, leads, transaction, bootstrap, crm |

---

## Situação
Era necessário converter um `platform_lead` em tenant operacional completo (tenant + owner + contato CRM), com confirmação imediata para o admin e sem risco de estado parcial.

---

## Decisão / Aprendizado
A conversão foi implementada de forma **síncrona e transacionada** em `PlatformLeadAdminActions::convert()`, atualizando o status do lead para `converted` apenas dentro da mesma transação que cria tenant, usuário owner, bootstrap inicial e contato CRM.

Aprendizado relevante: o bootstrap depende da existência do segmento `GENERAL` em `ai_prompt_segments`; sem ele a conversão falha e o rollback preserva o estado original do lead.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Converter via job assíncrono | Não atende necessidade de feedback imediato para o admin no ato da conversão |
| Atualizar lead antes e criar tenant fora de transação | Risco de inconsistência (lead convertido sem tenant/usário/contato completos) |

---

## Consequências
### Positivas
- Fluxo previsível e atômico: sem estados parciais em falha.
- Sem duplicação de regra de negócio, com reuso de `PlatformTenantActions`, `AuthUserActions` e `CRMContactActions`.
- Endpoint consegue sinalizar conflitos de reconversão (`409`) de forma explícita.

### Negativas / Trade-offs
- Conversão depende de pré-condições de catálogo AI/segmento (`GENERAL`) no ambiente.
- Fluxo síncrono aumenta tempo de resposta em relação a abordagem assíncrona.

---

## Referências
- Feature: `FEAT-005`
- Arquivos: `api/src/Domain/Platform/Actions/PlatformLeadAdminActions.php`, `api/src/Domain/Platform/Http/Controllers/PlatformLeadAdminController.php`
