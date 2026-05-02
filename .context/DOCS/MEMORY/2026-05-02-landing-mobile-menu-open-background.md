# Memory: Landing mobile precisa de estados visuais explicitos

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 📚 Aprendizado |
| **Data** | 2026-05-02 |
| **Autor** | Codex |
| **Contexto** | Correções visuais da landing pública em mobile |
| **Tags** | landing, mobile, navbar, tailwind, acessibilidade |

---

## Situação

No mobile, ao abrir o menu no topo da landing, os links eram exibidos diretamente sobre o hero. Como o `navbar` só recebia fundo após `scrollY > 50`, o menu aberto no topo ficava sem superfície visual própria e o texto se misturava com os elementos da primeira dobra.

Também foi identificado que o card escuro "Autopilot com IA" da seção de pilares usava `.pillar-card` junto com classes Tailwind de gradiente. A regra CSS `.pillar-card { background: white; }` prevalecia e deixava texto branco sobre fundo branco.

---

## Decisão / Aprendizado

Estados visuais móveis da landing devem ser explícitos, não dependentes de combinação acidental entre Tailwind e CSS local. Foi adicionado `navbar-menu-open` com o mesmo fundo/backdrop de `navbar-scrolled`, e o conteúdo do menu passou a renderizar em um painel com `bg-primary-light/95`, borda, sombra e blur. O botão também sincroniza `aria-expanded` para refletir o estado real do menu.

Para o card escuro, foi adicionada a classe CSS `pillar-card-dark`, com gradiente definido no mesmo bloco de CSS local que controla `.pillar-card`. Assim o card mantém o fundo verde em todos os tamanhos e não depende da ordem de geração das classes do Tailwind CDN.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Aplicar fundo permanente no `nav` desde o topo | Mudaria a direção visual do hero desktop/mobile mesmo quando o menu está fechado. |
| Depender apenas de `navbar-scrolled` | Não resolve o bug no topo, que é exatamente antes do threshold de scroll. |
| Adicionar só opacidade nos links | Melhoraria pouco a leitura, mas continuaria sem painel/contraste consistente. |
| Manter o gradiente do card apenas via Tailwind | Continuava sujeito à precedência da regra local `.pillar-card { background: white; }`. |

---

## Consequências

### Positivas
- Menu mobile fica legível no topo e durante scroll.
- Estado visual aberto não depende de posição da página.
- Acessibilidade melhora com `aria-expanded` e `aria-controls`.
- Card "Autopilot com IA" mantém contraste correto em mobile.

### Negativas / Trade-offs
- A validação ficou manual conforme solicitado pelo usuário nesta correção visual.

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-048-landing-page-lead-capture.md`
- Landing: `landing/index.html`
