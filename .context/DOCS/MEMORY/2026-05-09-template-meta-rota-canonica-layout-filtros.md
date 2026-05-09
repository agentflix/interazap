# Memory: Rota canônica template-meta e layout fixo de filtros em Templates Meta

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-09 |
| **Autor** | DEV agent |
| **Contexto** | Correção visual e padronização semântica da tela de templates da Meta no App Angular |
| **Tags** | app, chat, templates-meta, roteamento, ui |

---

## Situação
A tela `/chat/templates` apresentava filtros encavalando por uso de `flex-wrap`, causando desalinhamento visual com o campo de busca no `af-crud-page`. Em paralelo, a nomenclatura da feature estava divergente entre URL/arquivos (`templates`) e o nome de negócio adotado (`template-meta`).

---

## Decisão / Aprendizado
Padronizar o domínio de navegação e arquivos para `template-meta` e manter os filtros em uma única linha (`flex items-center gap-2`) para evitar quebra e desalinhamento.

A decisão inclui rename físico de diretórios/arquivos e atualização de rotas, menu e navegação interna para impedir inconsistência entre UI, URL e estrutura de código.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter `/chat/templates` e só ajustar CSS | Mantém dívida semântica e dificulta descoberta/manutenção futura |
| Resolver só com ajustes no container pai (`items-end`) | Não remove causa raiz de quebra dos filtros em duas linhas |

---

## Consequências
### Positivas
- URL, menu e estrutura de arquivos passam a refletir o mesmo conceito (`template-meta`).
- Layout dos filtros fica estável, sem sobreposição/encavalamento.
- Testes da página principal permanecem coerentes com as novas rotas.

### Negativas / Trade-offs
- Qualquer bookmark/integracão interna apontando para `/chat/templates` precisa ser atualizado.

---

## Referências
- Arquivos: `app/src/app/pages/chat/template-meta/template-meta-page/*`, `app/src/app/pages/chat/template-meta/template-form-page/*`, `app/src/app/pages/chat/template-meta/helpers/*`, `app/src/app/app.routes.ts`, `app/src/app/layout/components/sidenav/menu-config.ts`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-09.md`
