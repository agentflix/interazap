# Memory: Coluna de plano na listagem de inquilinos

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | Ajuste da tela `/platform/tenants` para exibir plano contratado na listagem |
| **Tags** | app, platform, tenants, planos, ui |

---

## Situação
A listagem de inquilinos não exibia qual plano cada tenant estava usando, o que dificultava leitura operacional rápida na tela administrativa.

---

## Decisão / Aprendizado
Adicionar a coluna `Plano` no grid principal com o formato `Nome do plano - R$0,00`, resolvendo o dado via `plan_id` do tenant e catálogo de planos carregado pelo frontend.

Quando não houver vínculo de plano ou o plano não estiver disponível no catálogo, exibir fallback `Plano não definido`.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Exibir apenas `plan_id` | Não é legível para operação/admin |
| Alterar API de tenants para já retornar plano expandido | Exigiria mudança cross-workspace maior para uma necessidade pontual de UI |

---

## Consequências
### Positivas
- Melhora a visibilidade do plano contratado direto na listagem.
- Reduz necessidade de abrir detalhes para validar plano e preço mensal.

### Negativas / Trade-offs
- Introduz uma chamada adicional de listagem de planos na inicialização da página.

---

## Referências
- Arquivos: `app/src/app/pages/platform/tenants/tenants.ts`, `app/src/app/pages/platform/tenants/tenants.html`, `app/src/app/pages/platform/tenants/tenants.spec.ts`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-09.md`
