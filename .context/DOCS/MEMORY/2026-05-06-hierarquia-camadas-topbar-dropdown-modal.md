# Memory: Hierarquia de camadas entre topbar, dropdown e modal

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-06 |
| **Autor** | Codex |
| **Contexto** | Correção visual no app Angular (header x modal) |
| **Tags** | ui, z-index, modal, topbar, dropdown |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Após elevar o `z-index` do header para resolver dropdown atrás do dashboard, o dropdown passou a ficar acima de modais (ex.: tela `chat/auto-reply`), quebrando a hierarquia esperada de overlay.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Padronizar a hierarquia global de camadas:
- Topbar em camada intermediária (`z-40`)
- Dropdowns do topbar permanecem acima do conteúdo comum
- Modal (`af-modal`) com camada global superior (`z-[100]`)

Assim, dropdown não fica atrás de conteúdo e modal sempre prevalece sobre header/dropdown.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter topbar em `z-[60]` | Resolve dashboard, mas quebra modal por ficar à frente do diálogo. |
| Corrigir apenas `chat/auto-reply` | Solução local com alto risco de regressão em outras telas. |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Hierarquia visual consistente em toda a aplicação.
- Menor chance de regressão entre páginas com modais e dropdowns.

### Negativas / Trade-offs
- Componentes que dependiam implicitamente da camada antiga podem exigir revisão pontual futura.

---

## Referências
- Feature: `.context/DOCS/FEATURES/` (não especificada para este ajuste pontual)
- Task: `TASK-UI-ZINDEX-HEADER-MODAL`
