# Memory: Tela administrativa de Platform Leads sem escopo de tenant

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-07 |
| **Autor** | DEV agent |
| **Contexto** | FEAT-004 — Tela administrativa de leads da plataforma |
| **Tags** | platform, leads, authorization, admin-ui |

---

## Situação
A base já possuía `platform_leads` com captura pública (`POST /api/public/leads`), mas não havia leitura administrativa no painel.

---

## Decisão / Aprendizado
A leitura administrativa foi exposta em `GET /api/platform/leads`, protegida por policy administrativa (`platform.tenants.manage`), e a tela foi criada no grupo Plataforma seguindo o padrão visual de `tenants`.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Reusar endpoint público para leitura | Endpoint público deve permanecer mínimo e sem dados sensíveis |
| Tratar `platform_leads` como tenant-scoped | Modelo representa lead comercial da InteraZap, não dado de tenant cliente |

---

## Consequências
### Positivas
- Time de operação passa a ter visibilidade centralizada dos leads captados pela landing.
- Arquitetura mantém separação clara entre endpoint público de captura e endpoint interno de gestão.

### Negativas / Trade-offs
- Fluxo de mudança de status/conversão de lead ainda não existe (ficou para próxima fase).

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-004-platform-leads-admin-screen.md`
- Task: `.context/DOCS/TASKS/feat-004-platform-leads-admin-screen-tasks.md`
